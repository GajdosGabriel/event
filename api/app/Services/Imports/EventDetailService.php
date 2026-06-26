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
		$body       = $main
			? $this->htmlCleaner->cleanInner($main)
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

		return [
			'title'                    => Str::limit(preg_replace('/\s*-\s*TK\s*KBS$/iu', '', $title) ?? $title, 250, ''),
			'body'                     => $body,
			'body_text'                => $bodyText,
			'start_at'                 => null,
			'end_at'                   => null,
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
		$xpath       = $this->createXPath($html);
		$title       = $this->firstNodeText($xpath, '//*[@id="event"]//h1 | //h1');
		$bodyText    = $this->normalizeWhitespace($this->joinNodeTexts($xpath, '//*[@id="event"]//p[not(contains(@class, "creator"))]'));
		$body        = $this->htmlCleaner->cleanFromXPath($xpath, '//*[@id="event"]//p[not(contains(@class, "creator"))]');
		$linkItems   = $this->extractLinkItems($xpath, $sourceUrl, '//*[@id="event"]//a[@href]');
		$links       = array_values(array_unique(array_column($linkItems, 'url')));
		$images      = $this->extractImages($xpath, $sourceUrl, '//*[@id="event"]//img[@src]');
		$attachments = $this->extractAttachments($xpath, $sourceUrl, '//*[@id="event"]//a[@href]');
		$dateText    = $this->firstNodeText($xpath, '//*[@id="event"]//h2//*[contains(@class, "nadpis")] | //*[@id="event"]//h2');
		[$startAt, $endAt] = $this->extractVyveskaDateRange($dateText);
		$creatorText = $this->firstNodeText($xpath, '//*[@id="event"]//p[contains(@class, "creator")][last()]');
		$rssItem     = $this->vyveskaRssService->findByUrl($sourceUrl);

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
			'registration_deadline_at' => null,
			'published_at_source'      => $this->extractVyveskaPublishedAt($creatorText) ?? ($rssItem['published_at'] ?? null),
			'links'                    => $links,
			'link_items'               => $linkItems,
			'image_urls'               => $images,
			'attachments'              => $attachments,
			'source_url'               => $sourceUrl,
		];
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
			return str_ends_with(strtolower((string) parse_url($imageUrl, PHP_URL_PATH)), '/image/tkkbs/tkkbs_logo.gif');
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

		if (preg_match('/(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})/iu', $normalized, $matches)) {
			$month = $this->slovakMonthToNumber($matches[2]);
			if ($month !== null) {
				$startAt = $this->sourceDateTime((int) $matches[3], $month, (int) $matches[1], 0, 0, 0);

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
	 * @return array{0:?Carbon,1:?Carbon}
	 */
	private function extractVyveskaDateRange(string $text): array
	{
		if (! preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2}):(\d{2})/u', $text, $matches)) {
			return [null, null];
		}

		return [
			$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5], 0),
			$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[6], (int) $matches[7], 0),
		];
	}

	private function extractVyveskaPublishedAt(string $text): ?Carbon
	{
		if (! preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})/u', $text, $matches)) {
			return null;
		}

		return $this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5], 0);
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
