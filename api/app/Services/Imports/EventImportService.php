<?php

namespace App\Services\Imports;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\Files\FileManager;
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
    ) {
    }

    /**
     * @return array{imported:int,updated:int,skipped:int,errors:int,processed:int}
     */
    public function importFromListing(string $listingUrl, int $maxPages = 1, ?int $limit = null): array
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
                $result = $this->importArticle($articleUrl);
                $summary[$result]++;
            } catch (\Throwable) {
                $summary['errors']++;
            } finally {
                $summary['processed']++;
            }
        }

        return $summary;
    }

    public function importArticle(string $articleUrl): string
    {
        $detail = $this->detailService->extract($articleUrl);
        $resolvedCanal = $this->canalNameResolver->resolve(
            $detail['source_url'],
            (string) $detail['title'],
            (string) ($detail['body_text'] ?? strip_tags((string) $detail['body'])),
            startAtFound: $detail['start_at'] !== null,
        );

        $canal = $this->canalManager->resolveOrCreate(
            $resolvedCanal['name'],
            $resolvedCanal['detected_name'],
            $resolvedCanal['source_origin'],
        );

        $venue = $this->venueManager->resolveOrDetect(
            $canal,
            $resolvedCanal['detected_venue_name'] ?? null,
            $resolvedCanal['detected_venue_city'] ?? null,
            $resolvedCanal['detected_venue_street'] ?? null,
        );

        $systemOwner = $this->canalManager->systemOwner();
        $existingEvent = $this->findExistingEvent($canal->id, $detail);

        $body = $this->appendRelevantLinksToBody(
            (string) $detail['body'],
            (array) ($detail['link_items'] ?? []),
            (array) ($detail['attachments'] ?? [])
        );

        // Regex dates take priority; AI fills in only what regex could not find
        $startAt = $detail['start_at'] ?? $resolvedCanal['ai_start_at'];
        $endAt   = $detail['end_at'] ?? $resolvedCanal['ai_end_at'] ?? ($startAt !== null ? $startAt->copy()->addHours(2) : null);
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
            $existingEvent->update($payload);
            $event = $existingEvent->fresh();
            $status = 'updated';
        } else {
            $event = $this->eventRepository->create($payload);
            $status = 'imported';
        }

        $this->syncImages($event, (array) $detail['image_urls'], (string) $detail['source_url']);
        $this->syncAttachments($event, (array) ($detail['attachments'] ?? []), (string) $detail['source_url']);

        return $status;
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
