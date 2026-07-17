<?php

namespace App\Services\Imports;

use Carbon\Carbon;
use Illuminate\Support\Str;

class EventDetailService
{
	private const SOURCE_TIMEZONE = 'Europe/Bratislava';

	public function __construct(
		private readonly ImportPageFetcher $pageFetcher,
		private readonly \App\Services\Imports\VyveskaRssService $vyveskaRssService,
		private readonly HtmlBodyCleaner $htmlCleaner = new HtmlBodyCleaner(),
	)
	{
	}

	/**
	 * @return array{
	 *   title:string,
	 *   body:string,
	 *   start_at:?Carbon,
	 *   end_at:?Carbon,
	 *   start_at_precise:bool,
	 *   registration_deadline_at:?Carbon,
	 *   published_at_source:?Carbon,
	 *   links:array<int,string>,
	 *   link_items:array<int,array{url:string,text:?string}>,
	 *   image_urls:array<int,string>,
	 *   attachments:array<int,array<string,mixed>>,
	 *   source_url:string
	 * }
	 */
	public function extract(string $sourceUrl): array
	{
		$html = $this->pageFetcher->fetch($sourceUrl);

		if ($this->isTkkbsUrl($sourceUrl)) {
			return $this->extractTkkbsDetail($html, $sourceUrl);
		}

		if ($this->isVyveskaUrl($sourceUrl)) {
			return $this->extractVyveskaDetail($html, $sourceUrl);
		}

		return $this->extractEcavDetail($html, $sourceUrl);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extractEcavDetail(string $html, string $sourceUrl): array
	{
		$xpath      = $this->createXPath($html);
		$main       = $this->firstNode($xpath, '//*[@id="content"] | //main');
		$title      = $this->firstNodeText($xpath, '//h1');
		$bodyText   = $this->normalizeWhitespace($main?->textContent ?? '');
		// The article container excludes the date/author/category list that sits
		// beside it and would otherwise land in the body. $bodyText stays on the
		// wider container so date extraction keeps seeing the whole page.
		$article    = $this->firstNode($xpath, '//*[contains(@class, "entry-content")]') ?? $main;
		$body       = $article
			? $this->htmlCleaner->cleanInner($article)
			: $this->htmlCleaner->fromPlainText($bodyText);
		$linkItems  = $this->extractLinkItems($xpath, $sourceUrl, '//*[@id="content"]//a[@href] | //main//a[@href]');
		$links      = array_values(array_unique(array_column($linkItems, 'url')));
		$images     = $this->extractImages($xpath, $sourceUrl, '//*[@id="content"]//img[@src] | //main//img[@src]');
		$attachments = $this->extractAttachments($xpath, $sourceUrl, '//*[@id="content"]//a[@href] | //main//a[@href]');
		$media      = $this->normalizeEcavMedia($images, $attachments);
		[$startAt, $endAt] = $this->extractDateRangeFromText($bodyText);

		return [
			'title'                    => Str::limit($title !== '' ? $title : 'Imported event', 250, ''),
			'body'                     => $body,
			'body_text'                => $bodyText,
			'start_at'                 => $startAt,
			'end_at'                   => $endAt,
			'start_at_precise'         => true,
			'registration_deadline_at' => $this->extractRegistrationDeadline($bodyText),
			'published_at_source'      => $this->extractFirstDateTimeFromText($bodyText),
			'links'                    => $links,
			'link_items'               => $linkItems,
			'image_urls'               => $media['image_urls'],
			'attachments'              => $media['attachments'],
			'source_url'               => $sourceUrl,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extractTkkbsDetail(string $html, string $sourceUrl): array
	{
		$xpath     = $this->createXPath($html);
		$title     = $this->firstNodeText($xpath, '//title | //h1');
		$bodyText  = $this->normalizeWhitespace($this->joinNodeTexts($xpath, '//*[contains(@class, "clatext")]//p | //*[contains(@class, "clatext")]'));
		$body      = $this->htmlCleaner->cleanFromXPath($xpath, '//*[contains(@class, "clatext")]');
		$linkItems = $this->extractLinkItems($xpath, $sourceUrl, '//*[contains(@class, "clatext")]//a[@href]');
		$links     = array_values(array_unique(array_column($linkItems, 'url')));
		$images    = $this->extractImages($xpath, $sourceUrl, '//img[@src]');

		if ($body === '') {
			$body = $this->htmlCleaner->fromPlainText($bodyText);
		}

		[$startAt, $endAt, $startAtPrecise] = $this->extractTkkbsDateRange($bodyText);

		return [
			'title'                    => Str::limit(preg_replace('/\s*-\s*TK\s*KBS$/iu', '', $title) ?? $title, 250, ''),
			'body'                     => $body,
			'body_text'                => $bodyText,
			'start_at'                 => $startAt,
			'end_at'                   => $endAt,
			'start_at_precise'         => $startAtPrecise,
			'registration_deadline_at' => null,
			'published_at_source'      => $this->extractTkkbsPublishedAt($bodyText),
			'links'                    => $links,
			'link_items'               => $linkItems,
			'image_urls'               => $images,
			'attachments'              => [],
			'source_url'               => $sourceUrl,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extractVyveskaDetail(string $html, string $sourceUrl): array
	{
		$xpath = $this->createXPath($html);

		// The 2026 Výveska redesign renders each event as the first <article>
		// under <main id="main">. The trailing "Ďalšie podujatia" block reuses
		// <article class="… border-b …"> for related events and must stay out of
		// the body, media and dates — every selector below is anchored to this
		// first article and, for the description, to its .vv-prose container.
		$article   = self::VYVESKA_ARTICLE;
		$prose     = $article . '//div[' . self::hasClass('vv-prose') . ']';

		$title     = $this->firstNodeText($xpath, $article . '//h1 | //h1');
		$proseNode = $this->firstNode($xpath, $prose);
		$body      = $proseNode ? $this->htmlCleaner->cleanInner($proseNode) : '';

		// The info box ("Kedy:", "Kde:", "Organizátor:", "Kategórie:") carries the
		// labelled fields the venue/organizer extractor reads, so it is prepended
		// to the description in the plain-text body. Related events are excluded.
		$infoText  = $this->extractVyveskaInfoText($xpath);
		$proseText = $this->normalizeWhitespace($proseNode?->textContent ?? '');
		$bodyText  = trim($infoText . "\n\n" . $proseText);

		$linkItems   = $this->extractLinkItems($xpath, $sourceUrl, $prose . '//a[@href]');
		$links       = array_values(array_unique(array_column($linkItems, 'url')));
		// Featured image is a direct child of the article; the rest live inside
		// the description. The "Kde" pin icon and related thumbnails sit elsewhere
		// and are intentionally not matched.
		$images      = $this->extractImages($xpath, $sourceUrl, $article . '/img[@src] | ' . $prose . '//img[@src]');
		$attachments = $this->extractAttachments($xpath, $sourceUrl, $prose . '//a[@href]');

		$headerDate  = $this->extractVyveskaHeaderDate($xpath);
		$kedy        = $this->vyveskaInfoRowValue($xpath, 'Kedy');
		[$startAt, $endAt] = $this->extractVyveskaDateRange($kedy, $headerDate);

		$rssItem = $this->vyveskaRssService->findByUrl($sourceUrl);

		$startAt ??= $rssItem['start_at'] ?? null;
		$endAt ??= $rssItem['end_at'] ?? null;

		if ($body === '') {
			$body = $this->htmlCleaner->fromPlainText($bodyText);
		}

		return [
			'title'                    => Str::limit($title !== '' ? $title : 'Imported event', 250, ''),
			'body'                     => $body,
			'body_text'                => $bodyText,
			'start_at'                 => $startAt,
			'end_at'                   => $endAt,
			'start_at_precise'         => true,
			'registration_deadline_at' => null,
			'published_at_source'      => $rssItem['published_at'] ?? null,
			'links'                    => $links,
			'link_items'               => $linkItems,
			'image_urls'               => $images,
			'attachments'              => $attachments,
			'source_url'               => $sourceUrl,
		];
	}

	/**
	 * XPath to the event's own <article> (the first one under <main id="main">),
	 * excluding the "Ďalšie podujatia" related-event cards nested below it.
	 */
	private const VYVESKA_ARTICLE = '(//main[@id="main"]//article)[1]';

	/**
	 * Whitespace-safe class-token match for use inside an XPath predicate.
	 */
	private static function hasClass(string $class): string
	{
		return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
	}

	/**
	 * The "DD.MM.YYYY" shown in the date header above the <h1>. Carries the day
	 * for the time-only "Kedy" variants ("15:30", "18:00 - 19:00").
	 */
	private function extractVyveskaHeaderDate(\DOMXPath $xpath): ?string
	{
		$spans = $xpath->query(self::VYVESKA_ARTICLE . '/div[' . self::hasClass('justify-between') . ']//span');

		foreach ($spans ?: [] as $span) {
			$text = $this->normalizeWhitespace($span->textContent ?? '');
			if (preg_match('/\d{1,2}\.\d{1,2}\.\d{4}/', $text, $matches)) {
				return $matches[0];
			}
		}

		return null;
	}

	/**
	 * Value of a single info-box row (e.g. "Kedy", "Kde"), read from the sky-blue
	 * box below the title. Returns '' when the row is absent.
	 */
	private function vyveskaInfoRowValue(\DOMXPath $xpath, string $label): string
	{
		$rows = $xpath->query(self::VYVESKA_ARTICLE . '//div[' . self::hasClass('bg-sky') . ']//p');

		foreach ($rows ?: [] as $row) {
			$text = $this->normalizeWhitespace($row->textContent ?? '');
			if (preg_match('/^' . preg_quote($label, '/') . '\s*:?\s*(.*)$/iu', $text, $matches)) {
				return trim($matches[1]);
			}
		}

		return '';
	}

	/**
	 * Info-box lines prepended to the plain-text body. Only "Kedy" (a date
	 * fallback for the AI when the regex parse fails) and "Kde" (read by the
	 * venue extractor) are kept. "Organizátor" is deliberately dropped: Výveska
	 * is an aggregator, so its events stay under one host-named "vyveska.sk"
	 * canal rather than being split per third-party organizer. The trailing
	 * region middot on the "Kde" line ("… · Trnavský") is stripped so it does
	 * not leak into the extracted venue name.
	 */
	private function extractVyveskaInfoText(\DOMXPath $xpath): string
	{
		$rows = $xpath->query(self::VYVESKA_ARTICLE . '//div[' . self::hasClass('bg-sky') . ']//p');
		$lines = [];

		foreach ($rows ?: [] as $row) {
			$text = $this->normalizeWhitespace($row->textContent ?? '');

			if (preg_match('/^Kde\s*:/iu', $text)) {
				$lines[] = preg_replace('/\s*·\s*\S+\s*$/u', '', $text) ?? $text;
			} elseif (preg_match('/^Kedy\s*:/iu', $text)) {
				$lines[] = $text;
			}
		}

		return implode("\n", $lines);
	}

	private function createXPath(string $html): \DOMXPath
	{
		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();

		return new \DOMXPath($document);
	}

	private function firstNode(\DOMXPath $xpath, string $expression): ?\DOMNode
	{
		$nodes = $xpath->query($expression);

		return $nodes !== false && $nodes->length > 0 ? $nodes->item(0) : null;
	}

	private function firstNodeText(\DOMXPath $xpath, string $expression): string
	{
		return $this->normalizeWhitespace($this->firstNode($xpath, $expression)?->textContent ?? '');
	}

	private function joinNodeTexts(\DOMXPath $xpath, string $expression): string
	{
		$nodes = $xpath->query($expression);
		$parts = [];

		foreach ($nodes ?: [] as $node) {
			$text = $this->normalizeWhitespace($node->textContent ?? '');
			if ($text !== '') {
				$parts[] = $text;
			}
		}

		return implode("\n\n", array_values(array_unique($parts)));
	}

	/**
	 * @return array<int, string>
	 */
	private function extractLinks(\DOMXPath $xpath, string $baseUrl, string $expression): array
	{
		return array_values(array_unique(array_column($this->extractLinkItems($xpath, $baseUrl, $expression), 'url')));
	}

	/**
	 * @return array<int, array{url:string,text:?string}>
	 */
	private function extractLinkItems(\DOMXPath $xpath, string $baseUrl, string $expression): array
	{
		$nodes = $xpath->query($expression);
		$linkItems = [];

		foreach ($nodes ?: [] as $node) {
			$href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
			$absoluteUrl = $this->absoluteUrl($baseUrl, $href);
			if ($absoluteUrl === null) {
				continue;
			}

			$key = strtolower($absoluteUrl);
			if (isset($linkItems[$key])) {
				continue;
			}

			$text = $this->normalizeWhitespace($node->textContent ?? '');
			$linkItems[$key] = [
				'url' => $absoluteUrl,
				'text' => $text !== '' ? $text : null,
			];
		}

		return array_values($linkItems);
	}

	/**
	 * @return array<int, string>
	 */
	private function extractImages(\DOMXPath $xpath, string $baseUrl, string $expression): array
	{
		$nodes = $xpath->query($expression);
		$images = [];

		foreach ($nodes ?: [] as $node) {
			$src = trim((string) $node->attributes?->getNamedItem('src')?->nodeValue);
			$absoluteUrl = $this->absoluteUrl($baseUrl, $src);
			if ($absoluteUrl !== null && ! $this->shouldIgnoreImageUrl($absoluteUrl, $baseUrl)) {
				$images[] = $absoluteUrl;
			}
		}

		return array_values(array_unique($images));
	}

	private function shouldIgnoreImageUrl(string $imageUrl, string $baseUrl): bool
	{
		if ($this->isTkkbsUrl($baseUrl)) {
			// The TK KBS header logo is served under /image/tkkbs/tkkbs_logo.*
			// (historically .gif, now .svg). Ignore it regardless of extension so
			// it never pollutes the event's image list.
			$path = strtolower((string) parse_url($imageUrl, PHP_URL_PATH));

			return preg_match('#/image/tkkbs/tkkbs_logo\.[a-z0-9]+$#', $path) === 1;
		}

		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function extractAttachments(\DOMXPath $xpath, string $baseUrl, string $expression): array
	{
		$nodes = $xpath->query($expression);
		$attachments = [];

		foreach ($nodes ?: [] as $node) {
			$href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
			$linkText = $this->normalizeWhitespace($node->textContent ?? '');
			$absoluteUrl = $this->absoluteUrl($baseUrl, $href);

			if ($absoluteUrl === null || ! $this->isAttachmentUrl($absoluteUrl, $linkText)) {
				continue;
			}

			$key = strtolower($absoluteUrl);
			if (isset($attachments[$key])) {
				continue;
			}

			$attachments[$key] = array_filter([
				'url' => $absoluteUrl,
				'name' => $this->attachmentNameFromUrl($absoluteUrl, $linkText),
				'link_text' => $linkText !== '' ? $linkText : null,
			]);
		}

		return array_values($attachments);
	}

	/**
	 * @param array<int, string> $images
	 * @param array<int, array<string, mixed>> $attachments
	 * @return array{image_urls: array<int, string>, attachments: array<int, array<string, mixed>>}
	 */
	private function normalizeEcavMedia(array $images, array $attachments): array
	{
		$imageMap = [];

		foreach ($images as $imageUrl) {
			$key = $this->ecavImageKey($imageUrl);
			$imageMap[$key] = $imageUrl;
		}

		$filteredAttachments = [];

		foreach ($attachments as $attachment) {
			$url = is_string($attachment['url'] ?? null) ? trim((string) $attachment['url']) : '';
			if ($url === '') {
				continue;
			}

			if (! $this->isImageFileUrl($url)) {
				$filteredAttachments[] = $attachment;
				continue;
			}

			$key = $this->ecavImageKey($url);
			$existing = $imageMap[$key] ?? null;

			if ($existing === null || $this->shouldPreferEcavImageUrl($url, $existing)) {
				$imageMap[$key] = $url;
			}
		}

		return [
			'image_urls' => array_values(array_unique(array_values($imageMap))),
			'attachments' => $filteredAttachments,
		];
	}

	/**
	 * @return array{0:?Carbon,1:?Carbon}
	 */
	private function extractDateRangeFromText(string $text): array
	{
		$normalized = $this->normalizeWhitespace($text);

		if (preg_match('/(\d{1,2})\.\s*[–-]\s*(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})/iu', $normalized, $matches)) {
			$month = $this->slovakMonthToNumber($matches[3]);
			if ($month !== null) {
				$startAt = $this->sourceDateTime((int) $matches[4], $month, (int) $matches[1], 0, 0, 0);
				$endAt = $this->sourceDateTime((int) $matches[4], $month, (int) $matches[2], 23, 59, 59);

				return [$startAt, $endAt];
			}
		}

		// "DD. Month YYYY" optionally followed by an explicit Slovak start time
		// ("o 12.00 hod.", "o 12:00", "o 12.00 h"). The time uses a dot or colon
		// separator — Slovak prose commonly writes "o 12.00 hod." rather than
		// "12:00" — and must be captured so the start is not defaulted to 00:00.
		if (preg_match('/(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})(?:\s+o\s+(\d{1,2})[:.](\d{2}))?/iu', $normalized, $matches)) {
			$month = $this->slovakMonthToNumber($matches[2]);
			if ($month !== null) {
				$hasTime = isset($matches[4]) && $matches[4] !== '';
				$startAt = $this->sourceDateTime(
					(int) $matches[3],
					$month,
					(int) $matches[1],
					$hasTime ? (int) $matches[4] : 0,
					$hasTime ? (int) $matches[5] : 0,
					0,
				);

				return [$startAt, null];
			}
		}

		return [null, null];
	}

	private function extractRegistrationDeadline(string $text): ?Carbon
	{
		if (! preg_match('/Uz[aá]vierka\s+prihl[aá][šs]ok\s*:\s*(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})/iu', $text, $matches)) {
			return null;
		}

		$month = $this->slovakMonthToNumber($matches[2]);
		if ($month === null) {
			return null;
		}

		return $this->sourceDateTime((int) $matches[3], $month, (int) $matches[1], 23, 59, 59);
	}

	private function extractFirstDateTimeFromText(string $text): ?Carbon
	{
		if (! preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{2,4})(?:\s+(\d{1,2}):(\d{2}))?/', $text, $matches)) {
			return null;
		}

		$year = (int) $matches[3];
		if ($year < 100) {
			$year += 2000;
		}

		return $this->sourceDateTime(
			$year,
			(int) $matches[2],
			(int) $matches[1],
			isset($matches[4]) ? (int) $matches[4] : 0,
			isset($matches[5]) ? (int) $matches[5] : 0,
			0,
		);
	}

	private function extractTkkbsPublishedAt(string $text): ?Carbon
	{
		if (! preg_match('/\b\d{1,2}\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+\d{4}\s+\d{1,2}:\d{2}\b/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
			return null;
		}

		$match = $matches[0][0];
		if (! preg_match('/(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})\s+(\d{1,2}):(\d{2})/iu', $match, $parts)) {
			return null;
		}

		$month = $this->slovakMonthToNumber($parts[2]);
		if ($month === null) {
			return null;
		}

		return $this->sourceDateTime((int) $parts[3], $month, (int) $parts[1], (int) $parts[4], (int) $parts[5], 0);
	}

	/**
	 * @return array{0:?Carbon,1:?Carbon,2:bool} Third element is true only when
	 *   an explicit clock time was matched alongside the date — a bare date
	 *   fallback (e.g. a "Sobota 4. júla 2026:" heading in front of a
	 *   multi-time program list) is unreliable as the event's actual start
	 *   and is flagged false so the caller can let AI confirm/refine it.
	 */
	private function extractTkkbsDateRange(string $text): array
	{
		// "DD. Month YYYY o HH:MM" — event date+time with Slovak preposition "o"
		// e.g. "29. júna 2026 o 18:00" or "29. júna 2026 o 18.00"
		if (preg_match(
			'/(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})\s+o\s+(\d{1,2})[:.](\d{2})/iu',
			$text,
			$m,
		)) {
			$month = $this->slovakMonthToNumber($m[2]);
			if ($month !== null) {
				return [
					$this->sourceDateTime((int) $m[3], $month, (int) $m[1], (int) $m[4], (int) $m[5], 0),
					null,
					true,
				];
			}
		}

		// Fallback: generic patterns ("DD.–DD. Month YYYY" or "DD. Month YYYY").
		// These never capture a time, so the result may just be a heading
		// (e.g. a program date) rather than the true event start.
		[$startAt, $endAt] = $this->extractDateRangeFromText($text);

		return [$startAt, $endAt, false];
	}

	/**
	 * Parses the info-box "Kedy" value into a start/end range. It comes in three
	 * shapes, two of which rely on the header date for the day:
	 *   - "D.M.YYYY H:i - D.M.YYYY H:i"  → full multi-day range (self-contained)
	 *   - "H:i - H:i"                    → header date + start/end time
	 *   - "H:i"                          → header date + start time, no end
	 *
	 * @return array{0:?Carbon,1:?Carbon}
	 */
	private function extractVyveskaDateRange(string $kedy, ?string $headerDate): array
	{
		$kedy = $this->normalizeWhitespace($kedy);

		// Self-contained "D.M.YYYY H:i - D.M.YYYY H:i" range.
		if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})/u', $kedy, $m)) {
			return [
				$this->sourceDateTime((int) $m[3], (int) $m[2], (int) $m[1], (int) $m[4], (int) $m[5], 0),
				$this->sourceDateTime((int) $m[8], (int) $m[7], (int) $m[6], (int) $m[9], (int) $m[10], 0),
			];
		}

		$day = $month = $year = null;
		if ($headerDate !== null && preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $headerDate, $hm)) {
			[$day, $month, $year] = [(int) $hm[1], (int) $hm[2], (int) $hm[3]];
		}

		if ($year === null) {
			return [null, null];
		}

		// Time range on the header day: "H:i - H:i".
		if (preg_match('/(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2}):(\d{2})/u', $kedy, $m)) {
			return [
				$this->sourceDateTime($year, $month, $day, (int) $m[1], (int) $m[2], 0),
				$this->sourceDateTime($year, $month, $day, (int) $m[3], (int) $m[4], 0),
			];
		}

		// Single start time on the header day: "H:i".
		if (preg_match('/(\d{1,2}):(\d{2})/u', $kedy, $m)) {
			return [$this->sourceDateTime($year, $month, $day, (int) $m[1], (int) $m[2], 0), null];
		}

		// Date known but no time — start at the beginning of the header day.
		return [$this->sourceDateTime($year, $month, $day, 0, 0, 0), null];
	}

	private function sourceDateTime(int $year, int $month, int $day, int $hour, int $minute, int $second): Carbon
	{
		return Carbon::create($year, $month, $day, $hour, $minute, $second, self::SOURCE_TIMEZONE)->utc();
	}

	private function normalizeWhitespace(string $value): string
	{
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = preg_replace('/\h+/u', ' ', $value) ?? $value;
		$value = preg_replace('/\s*\n\s*/u', "\n", $value) ?? $value;

		return trim($value);
	}

	private function isImageFileUrl(string $url): bool
	{
		$path = strtolower((string) parse_url($url, PHP_URL_PATH));

		return preg_match('/\.(jpg|jpeg|png|webp|gif)(\b|$)/i', $path) === 1;
	}

	private function ecavImageKey(string $url): string
	{
		$blobId = $this->extractEcavBlobId($url);

		return $blobId !== null ? 'ecav:' . $blobId : strtolower($url);
	}

	private function shouldPreferEcavImageUrl(string $candidate, string $current): bool
	{
		return $this->isEcavBlobUrl($candidate) && ! $this->isEcavBlobUrl($current);
	}

	private function extractEcavBlobId(string $url): ?string
	{
		$path = (string) parse_url($url, PHP_URL_PATH);

		if (preg_match('#/rails/active_storage/blobs/proxy/([^/]+)/#', $path, $matches)) {
			return $matches[1];
		}

		if (preg_match('#/rails/active_storage/representations/proxy/([^/]+)/#', $path, $matches)) {
			return $matches[1];
		}

		return null;
	}

	private function isEcavBlobUrl(string $url): bool
	{
		$path = (string) parse_url($url, PHP_URL_PATH);

		return str_contains($path, '/rails/active_storage/blobs/proxy/');
	}

	private function isAttachmentUrl(string $url, string $linkText): bool
	{
		$path = strtolower((string) parse_url($url, PHP_URL_PATH));
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
		$fileQuery = strtolower((string) ($query['file'] ?? ''));
		$linkText = Str::lower($linkText);
		$haystack = $path . ' ' . $fileQuery . ' ' . $linkText;

		if (str_contains($path, '/subor.html') && $fileQuery !== '') {
			return true;
		}

		return preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|odt|ods|odp|rtf|zip|jpg|jpeg|png|webp|gif)(\b|$)/i', $haystack) === 1
			|| str_contains($linkText, 'príloha')
			|| str_contains($linkText, 'priloha');
	}

	private function attachmentNameFromUrl(string $url, string $linkText): string
	{
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

		if (isset($query['file']) && is_string($query['file']) && trim($query['file']) !== '') {
			$name = basename($query['file']);
			if ($name !== '') {
				return $name;
			}
		}

		if ($linkText !== '' && str_contains($linkText, '.')) {
			return $linkText;
		}

		$path = basename((string) parse_url($url, PHP_URL_PATH));

		return $path !== '' ? $path : 'remote_attachment';
	}

	private function slovakMonthToNumber(string $month): ?int
	{
		$normalized = Str::lower($this->normalizeWhitespace($month));
		$normalized = trim($normalized, '. ');

		return match ($normalized) {
			'januar', 'januára', 'januarа' => 1,
			'februar', 'februára' => 2,
			'marec', 'marca' => 3,
			'april', 'apríla' => 4,
			'maj', 'mája' => 5,
			'jun', 'júna' => 6,
			'jul', 'júla' => 7,
			'august', 'augusta' => 8,
			'september', 'septembra' => 9,
			'oktober', 'októbra' => 10,
			'november', 'novembra' => 11,
			'december', 'decembra' => 12,
			default => null,
		};
	}

	private function isTkkbsUrl(string $url): bool
	{
		return str_contains((string) parse_url($url, PHP_URL_HOST), 'tkkbs.sk');
	}

	private function isVyveskaUrl(string $url): bool
	{
		return str_contains((string) parse_url($url, PHP_URL_HOST), 'vyveska.sk');
	}

	private function absoluteUrl(string $baseUrl, string $url): ?string
	{
		if ($url === '' || str_starts_with($url, '#') || str_starts_with(strtolower($url), 'javascript:')) {
			return null;
		}

		if (preg_match('#^https?://#i', $url) === 1) {
			return $url;
		}

		$base = parse_url($baseUrl);
		if (! is_array($base) || empty($base['host'])) {
			return null;
		}

		$scheme = (string) ($base['scheme'] ?? 'https');
		$host = (string) $base['host'];

		if (str_starts_with($url, '//')) {
			return $scheme . ':' . $url;
		}

		if (str_starts_with($url, '/')) {
			return $scheme . '://' . $host . $url;
		}

		$basePath = (string) ($base['path'] ?? '/');
		$directory = preg_replace('#/[^/]*$#', '/', $basePath) ?? '/';

		return $scheme . '://' . $host . $directory . $url;
	}
}
