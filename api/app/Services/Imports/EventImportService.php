<?php

namespace App\Services\Imports;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\Files\FileManager;
use App\Services\Geocoding\GoogleMapsLinkResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventImportService
{
    public function __construct(
        private readonly EventListingService $listService,
        private readonly EventDetailService $detailService,
        private readonly ImportedCanalNameResolver $canalNameResolver,
        private readonly ImportedCanalManager $canalManager,
        private readonly ImportedVenueManager $venueManager,
        private readonly EventRepository $eventRepository,
        private readonly FileManager $fileManager,
        private readonly PdfConverterService $pdfConverter,
        private readonly GoogleMapsLinkResolver $mapsLinkResolver = new GoogleMapsLinkResolver(),
    ) {
    }

    /**
     * @return array{imported:int,updated:int,skipped:int,errors:int,processed:int}
     */
    public function importFromListing(string $listingUrl, int $maxPages = 1, ?int $limit = null, bool $force = false): array
    {
        $articleUrls = $this->listService->listArticleUrls($listingUrl, $maxPages, $limit);
        $summary = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed' => 0,
        ];

        foreach ($articleUrls as $articleUrl) {
            try {
                $result = $this->importArticle($articleUrl, $force);
                $summary[$result]++;
            } catch (\Throwable $e) {
                // Tolerate a single unparseable/unreachable article: log the real
                // cause (which URL, which exception) so it's actionable, then keep
                // going. Without this the exception was swallowed and the scheduler
                // only saw a generic "exit code 1" with no clue what failed.
                $summary['errors']++;
                Log::error('Event import failed for article.', [
                    'listing_url' => $listingUrl,
                    'article_url' => $articleUrl,
                    'exception' => $e,
                ]);
            } finally {
                $summary['processed']++;
            }
        }

        return $summary;
    }

    public function importArticle(string $articleUrl, bool $force = false): string
    {
        // Cheap early exit: if this exact source URL was already imported and the
        // resulting event has everything it needs, skip before doing any expensive
        // work (detail page fetch, AI canal/venue detection, PDF conversion).
        if (! $force) {
            $alreadyImported = Event::query()->where('orginal_source', $articleUrl)->first();
            if ($alreadyImported instanceof Event && $this->isComplete($alreadyImported)) {
                return 'skipped';
            }
        }

        $detail = $this->detailService->extract($articleUrl);

        // Convert any PDF attachments: extract text (for canal/venue/date detection) and page images
        $pdfText    = '';
        $pdfResults = []; // [['result' => PdfConvertResult, 'name' => string, 'source_url' => string]]
        foreach ((array) ($detail['attachments'] ?? []) as $attachment) {
            $url  = is_string($attachment['url'] ?? null) ? trim((string) $attachment['url']) : '';
            $name = is_string($attachment['name'] ?? null) ? (string) $attachment['name'] : basename((string) parse_url($url, PHP_URL_PATH));
            $urlPath = strtolower((string) parse_url($url, PHP_URL_PATH));
            if ($url === '' || !str_ends_with($urlPath, '.pdf')) {
                continue;
            }
            $result = $this->pdfConverter->convertFromUrl($url);
            if ($result === null || $result->fullText === '') {
                continue;
            }
            $pdfText .= "\n\n" . $result->fullText;
            $pdfResults[] = ['result' => $result, 'name' => $name, 'source_url' => $url];
        }

        $rawBodyText    = (string) ($detail['body_text'] ?? strip_tags((string) $detail['body']));
        $enrichedBodyText = $pdfText !== '' ? $rawBodyText . $pdfText : $rawBodyText;

        // A date found without an explicit clock time (e.g. a "Sobota 4. júla
        // 2026:" heading in front of a multi-time program list) is only a
        // guess, not a confirmed start — treat it as "not found" so the AI
        // fallback gets a chance to read the actual program and confirm the
        // real start/end time.
        $startAtPrecise = (bool) ($detail['start_at_precise'] ?? true);

        $resolvedCanal = $this->canalNameResolver->resolve(
            $detail['source_url'],
            (string) $detail['title'],
            $enrichedBodyText,
            startAtFound: $detail['start_at'] !== null && $startAtPrecise,
            referenceDate: $detail['published_at_source'] ?? now(),
        );

        $canal = $this->canalManager->resolveOrCreate(
            $resolvedCanal['name'],
            $resolvedCanal['detected_name'],
            $resolvedCanal['source_origin'],
        );

        // A Google Maps pin in the article (e.g. "presne tu: https://maps.app.goo.gl/…")
        // is the most reliable venue location — read it straight from the link, checking
        // both the explicit link list and the body text.
        $mapCoords = $this->mapsLinkResolver->fromUrls((array) ($detail['links'] ?? []));
        if ($mapCoords['latitude'] === null) {
            $mapCoords = $this->mapsLinkResolver->fromText($enrichedBodyText);
        }

        $venue = $this->venueManager->resolveOrDetect(
            $canal,
            $resolvedCanal['detected_venue_name'] ?? null,
            $resolvedCanal['detected_venue_city'] ?? null,
            $resolvedCanal['detected_venue_street'] ?? null,
            $mapCoords['latitude'] ?? null,
            $mapCoords['longitude'] ?? null,
        );

        $systemOwner = $this->canalManager->systemOwner();
        $existingEvent = $this->findExistingEvent($canal->id, $detail);

        $body = $this->appendRelevantLinksToBody(
            (string) $detail['body'],
            (array) ($detail['link_items'] ?? []),
            (array) ($detail['attachments'] ?? [])
        );

        // Enrich body with PDF text if existing body is very short
        if ($pdfText !== '' && mb_strlen(strip_tags($body)) < 300) {
            $safeText = htmlspecialchars(trim($pdfText), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = rtrim($body) . "\n<p>" . nl2br($safeText) . "</p>";
        }

        // Precise regex dates take priority. An imprecise (date-only) regex
        // match defers to the AI-confirmed time when available, falling back
        // to the original guess only if AI could not confirm it either.
        $regexStartAt = $startAtPrecise ? $detail['start_at'] : null;
        $regexEndAt   = $startAtPrecise ? $detail['end_at'] : null;

        $startAt = $regexStartAt ?? $resolvedCanal['ai_start_at'] ?? $detail['start_at'];
        $endAt   = $regexEndAt ?? $resolvedCanal['ai_end_at'] ?? ($startAt !== null ? $startAt->copy()->addHours(2) : null);
        $isComplete = $startAt !== null && $endAt !== null && trim($body) !== '';

        $payload = [
            'name' => Str::limit((string) $detail['title'], 250, ''),
            'body' => $body,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'registration_deadline_at' => $detail['registration_deadline_at'],
            'status' => $isComplete ? ModelStatus::Published->value : ModelStatus::Draft->value,
            'published_at' => $isComplete ? now() : null,
            'website' => $this->resolveEventWebsite((array) $detail['links'], (string) $detail['source_url']),
            'orginal_source' => (string) $detail['source_url'],
            'email' => $resolvedCanal['ai_email'] ?? null,
            'phone' => $resolvedCanal['ai_phone'] ?? null,
            'venue_id' => $venue->id,
            'canal_id' => $canal->id,
            'user_id' => $systemOwner->id,
            'meta' => [
                'import' => [
                    'source' => 'external_source',
                    'source_origin' => $resolvedCanal['source_origin'],
                    'detected_canal_name' => $resolvedCanal['detected_name'],
                    'detected_venue_name' => $resolvedCanal['detected_venue_name'] ?? null,
                    'imported_at' => now()->toIso8601String(),
                    'published_at_source' => $detail['published_at_source']?->toIso8601String(),
                    'links' => $detail['links'],
                    'link_items' => $detail['link_items'] ?? [],
                    'image_urls' => $detail['image_urls'],
                    'attachments' => $detail['attachments'] ?? [],
                ],
                'raw_text' => $detail['body_text'] ?? strip_tags((string) $detail['body']),
            ],
        ];

        if ($existingEvent instanceof Event) {
            if (! $force && $this->isComplete($existingEvent)) {
                return 'skipped';
            }

            $existingEvent->update($payload);
            $event = $existingEvent->fresh();
            $status = 'updated';
        } else {
            $event = $this->eventRepository->create($payload);
            $status = 'imported';
        }

        $this->syncImages($event, (array) $detail['image_urls'], (string) $detail['source_url']);
        $this->syncAttachments($event, (array) ($detail['attachments'] ?? []), (string) $detail['source_url']);
        $this->syncPdfPageImages($event, $pdfResults);

        return $status;
    }

    /**
     * An imported event is considered "complete" once it has everything a
     * re-import would otherwise try to fill in: it's published, has a date
     * range, and a resolved venue. Complete events are skipped on subsequent
     * import runs (unless $force is set) to avoid redundant scraping/AI/PDF work.
     */
    private function isComplete(Event $event): bool
    {
        return $event->status === ModelStatus::Published
            && $event->start_at !== null
            && $event->end_at !== null
            && $event->venue_id !== null;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function findExistingEvent(int $canalId, array $detail): ?Event
    {
        $sourceUrl = (string) ($detail['source_url'] ?? '');
        if ($sourceUrl !== '') {
            $event = Event::query()
                ->where('canal_id', $canalId)
                ->where('orginal_source', $sourceUrl)
                ->first();

            if ($event instanceof Event) {
                return $event;
            }
        }

        $title = trim((string) ($detail['title'] ?? ''));
        $startAt = $detail['start_at'] ?? null;

        if ($title === '' || $startAt === null) {
            return null;
        }

        return Event::query()
            ->where('canal_id', $canalId)
            ->where('slug', Str::slug($title))
            ->where('start_at', $startAt)
            ->first();
    }

    /**
     * @param array<int, string> $links
     */
    private function resolveEventWebsite(array $links, string $sourceUrl): string
    {
        $sourceHost = (string) parse_url($sourceUrl, PHP_URL_HOST);

        foreach ($links as $link) {
            $host = (string) parse_url($link, PHP_URL_HOST);
            if ($host === '' || $host === $sourceHost) {
                continue;
            }

            if (preg_match('/\.(jpg|jpeg|png|webp|gif|pdf)(\?.*)?$/i', $link)) {
                continue;
            }

            return Str::limit($link, 150, '');
        }

        return Str::limit($sourceUrl, 150, '');
    }

    /**
     * @param array<int, array{result: PdfConvertResult, name: string, source_url: string}> $pdfResults
     */
    private function syncPdfPageImages(Event $event, array $pdfResults): void
    {
        if ($pdfResults === []) {
            return;
        }

        $existingSourceUrls = $event->files()
            ->get()
            ->map(fn ($file) => is_array($file->meta) ? ($file->meta['source_pdf_url'] ?? null) : null)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        foreach ($pdfResults as ['result' => $result, 'name' => $name, 'source_url' => $sourceUrl]) {
            if (in_array($sourceUrl, $existingSourceUrls, true)) {
                continue;
            }

            // Note: the original PDF itself is already kept as a separate attachment
            // by syncAttachments() (via RemoteAttachmentPersister, which generates its
            // own preview through the same ImageVariantGenerator pipeline) — storing it
            // again here would duplicate the file. This only adds the per-page images.
            foreach ($result->pages as $page) {
                $pageNumber   = (int) ($page['page'] ?? 1);
                $uploadedFile = $this->pdfConverter->pageToUploadedFile($page, $name, $pageNumber);
                if ($uploadedFile === null) {
                    continue;
                }

                try {
                    $this->fileManager->storeForEvent(
                        $event,
                        $uploadedFile,
                        FileType::IMAGE,
                        'public',
                        null,
                        false,
                        [
                            'source'        => 'pdf_conversion',
                            'source_pdf_url' => $sourceUrl,
                            'page'           => $pageNumber,
                        ]
                    );
                } finally {
                    @unlink($uploadedFile->getPathname());
                }
            }
        }
    }

    /**
     * @param array<int, string> $imageUrls
     */
    private function syncImages(Event $event, array $imageUrls, string $articleUrl): void
    {
        $normalizedUrls = array_values(array_unique(array_filter(array_map(function ($url) {
            return is_string($url) && trim($url) !== '' ? trim($url) : null;
        }, $imageUrls))));

        $existingImageFiles = $event->files()
            ->where('type', FileType::IMAGE->value)
            ->get();

        foreach ($existingImageFiles as $file) {
            $meta = is_array($file->meta) ? $file->meta : [];
            $sourceArticleUrl = is_string($meta['article_url'] ?? null) ? trim((string) $meta['article_url']) : '';
            $sourceUrl = is_string($meta['source_url'] ?? null) ? trim((string) $meta['source_url']) : '';

            if ($sourceArticleUrl !== $articleUrl || $sourceUrl === '') {
                continue;
            }

            if (in_array($sourceUrl, $normalizedUrls, true)) {
                continue;
            }

            $this->fileManager->delete($file);
        }

        if ($normalizedUrls === []) {
            return;
        }

        $existingSourceUrls = $event->files()
            ->get()
            ->map(fn ($file) => is_array($file->meta) ? ($file->meta['source_url'] ?? null) : null)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        $attachments = array_values(array_filter(array_map(function (string $url) use ($existingSourceUrls) {
            if (in_array($url, $existingSourceUrls, true)) {
                return null;
            }

            $path = (string) parse_url($url, PHP_URL_PATH);
            $filename = basename($path);

            return [
                'url' => $url,
                'name' => $filename !== '' ? $filename : 'remote-image.jpg',
            ];
        }, $normalizedUrls)));

        if ($attachments === []) {
            return;
        }

        $this->fileManager->storeRemoteForEvent(
            $event,
            $attachments,
            FileType::IMAGE,
            'public',
            null,
            false,
            [
                'source' => 'external_import',
                'article_url' => $articleUrl,
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    private function syncAttachments(Event $event, array $attachments, string $articleUrl): void
    {
        if ($attachments === []) {
            return;
        }

        $existingSourceUrls = $event->files()
            ->get()
            ->map(fn ($file) => is_array($file->meta) ? ($file->meta['source_url'] ?? null) : null)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        $imageAttachments = [];
        $fileAttachments = [];

        foreach ($attachments as $attachment) {
            $url = is_string($attachment['url'] ?? null) ? trim((string) $attachment['url']) : '';
            if ($url === '' || in_array($url, $existingSourceUrls, true)) {
                continue;
            }

            $normalized = [
                'url' => $url,
                'name' => $attachment['name'] ?? basename((string) parse_url($url, PHP_URL_PATH)) ?: 'remote-attachment',
                'link_text' => $attachment['link_text'] ?? null,
            ];

            if ($this->isImageAttachment($normalized)) {
                $imageAttachments[] = $normalized;
                continue;
            }

            $fileAttachments[] = $normalized;
        }

        if ($imageAttachments !== []) {
            $this->fileManager->storeRemoteForEvent(
                $event,
                $imageAttachments,
                FileType::IMAGE,
                'public',
                null,
                false,
                [
                    'source' => 'external_import_attachment',
                    'article_url' => $articleUrl,
                ]
            );
        }

        if ($fileAttachments !== []) {
            $this->fileManager->storeRemoteForEvent(
                $event,
                $fileAttachments,
                FileType::FILE,
                'public',
                null,
                false,
                [
                    'source' => 'external_import_attachment',
                    'article_url' => $articleUrl,
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function isImageAttachment(array $attachment): bool
    {
        $name = strtolower((string) ($attachment['name'] ?? ''));
        $url = strtolower((string) ($attachment['url'] ?? ''));
        $haystack = $name . ' ' . $url;

        return preg_match('/\.(jpg|jpeg|png|webp|gif)(\b|$)/i', $haystack) === 1;
    }

    /**
     * @param array<int, array<string, mixed>> $linkItems
     * @param array<int, array<string, mixed>> $attachments
     */
    private function appendRelevantLinksToBody(string $body, array $linkItems, array $attachments): string
    {
        $attachmentUrls = array_values(array_filter(array_map(
            fn ($attachment) => is_string($attachment['url'] ?? null) ? trim((string) $attachment['url']) : null,
            $attachments
        )));

        $relevantLinks = [];

        foreach ($linkItems as $linkItem) {
            $url = is_string($linkItem['url'] ?? null) ? trim((string) $linkItem['url']) : '';
            if ($url === '' || in_array($url, $attachmentUrls, true)) {
                continue;
            }

            if (preg_match('/\.(jpg|jpeg|png|webp|gif|pdf)(\?.*)?$/i', $url)) {
                continue;
            }

            $text = is_string($linkItem['text'] ?? null) ? trim((string) $linkItem['text']) : '';
            $label = $this->linkLabel($text, $url);
            $key = mb_strtolower($label . '|' . $url);

            $relevantLinks[$key] = ['label' => $label, 'url' => $url];
        }

        if ($relevantLinks === []) {
            return $body;
        }

        $items = '';
        foreach ($relevantLinks as $link) {
            $safeHref  = htmlspecialchars($link['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safeLabel = htmlspecialchars($link['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $items    .= "<li><a href=\"{$safeHref}\">{$safeLabel}</a></li>\n";
        }

        return rtrim($body) . "\n<h2>Odkazy</h2>\n<ul>\n{$items}</ul>";
    }

    private function linkLabel(string $text, string $url): string
    {
        if ($text !== '' && ! filter_var($text, FILTER_VALIDATE_URL)) {
            return Str::limit($text, 120, '');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return $host !== '' ? $host : 'Odkaz';
    }
}
