<?php

namespace App\Services\Imports;

use Carbon\Carbon;

class VyveskaRssService
{
	private const FEED_URL = 'https://www.vyveska.sk/rss.xml';
	private const SOURCE_TIMEZONE = 'Europe/Bratislava';

	/**
	 * @var array<string, array{title:string, start_at:?Carbon, end_at:?Carbon, published_at:?Carbon}>|null
	 */
	private ?array $items = null;

	public function __construct(private readonly ImportPageFetcher $pageFetcher)
	{
	}

	/**
	 * @return array{title:string, start_at:?Carbon, end_at:?Carbon, published_at:?Carbon}|null
	 */
	public function findByUrl(string $articleUrl): ?array
	{
		$normalizedUrl = $this->normalizeUrl($articleUrl);

		if ($normalizedUrl === null) {
			return null;
		}

		try {
			$items = $this->items();
		} catch (\Throwable) {
			return null;
		}

		return $items[$normalizedUrl] ?? null;
	}

	/**
	 * @return array<string, array{title:string, start_at:?Carbon, end_at:?Carbon, published_at:?Carbon}>
	 */
	private function items(): array
	{
		if ($this->items !== null) {
			return $this->items;
		}

		$xml = $this->pageFetcher->fetch(self::FEED_URL);
		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();

		$xpath = new \DOMXPath($document);
		$itemNodes = $xpath->query('/rss/channel/item');
		$items = [];

		foreach ($itemNodes ?: [] as $itemNode) {
			$link = $this->nodeText($xpath, './link', $itemNode);
			$normalizedUrl = $this->normalizeUrl($link);

			if ($normalizedUrl === null) {
				continue;
			}

			$title = $this->nodeText($xpath, './title', $itemNode);
			$description = $this->nodeText($xpath, './description', $itemNode);
			$category = $this->nodeText($xpath, './category', $itemNode);
			[$startAt, $endAt] = $this->extractDateRange($description . "\n" . $category);

			$items[$normalizedUrl] = [
				'title' => $title,
				'start_at' => $startAt,
				'end_at' => $endAt,
				'published_at' => $this->parsePubDate($this->nodeText($xpath, './pubDate', $itemNode)),
			];
		}

		$this->items = $items;

		return $this->items;
	}

	private function nodeText(\DOMXPath $xpath, string $expression, \DOMNode $contextNode): string
	{
		$nodes = $xpath->query($expression, $contextNode);

		if ($nodes === false || $nodes->length === 0) {
			return '';
		}

		return $this->normalizeWhitespace($nodes->item(0)?->textContent ?? '');
	}

	/**
	 * @return array{0:?Carbon,1:?Carbon}
	 */
	private function extractDateRange(string $text): array
	{
		$normalized = $this->normalizeWhitespace($text);

		if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s*[–-]\s*(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2}):(\d{2})/u', $normalized, $matches)) {
			return [
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5], 0),
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[6], (int) $matches[7], 0),
			];
		}

		if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})/u', $normalized, $matches)) {
			return [
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5], 0),
				$this->sourceDateTime((int) $matches[8], (int) $matches[7], (int) $matches[6], (int) $matches[9], (int) $matches[10], 0),
			];
		}

		if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2})\.(\d{1,2})\.(\d{4})/u', $normalized, $matches)) {
			return [
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5], 0),
				$this->sourceDateTime((int) $matches[8], (int) $matches[7], (int) $matches[6], 23, 59, 59),
			];
		}

		if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s*[–-]\s*(\d{1,2})\.(\d{1,2})\.(\d{4})/u', $normalized, $matches)) {
			return [
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], 0, 0, 0),
				$this->sourceDateTime((int) $matches[6], (int) $matches[5], (int) $matches[4], 23, 59, 59),
			];
		}

		if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2}):(\d{2})/u', $normalized, $matches)) {
			return [
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5], 0),
				$this->sourceDateTime((int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[6], (int) $matches[7], 0),
			];
		}

		return [null, null];
	}

	private function sourceDateTime(int $year, int $month, int $day, int $hour, int $minute, int $second): Carbon
	{
		return Carbon::create($year, $month, $day, $hour, $minute, $second, self::SOURCE_TIMEZONE)->utc();
	}

	private function parsePubDate(string $value): ?Carbon
	{
		if ($value === '') {
			return null;
		}

		try {
			return Carbon::parse($value);
		} catch (\Throwable) {
			return null;
		}
	}

	private function normalizeUrl(string $url): ?string
	{
		$url = trim($url);

		if ($url === '' || ! str_contains((string) parse_url($url, PHP_URL_HOST), 'vyveska.sk')) {
			return null;
		}

		$host = (string) parse_url($url, PHP_URL_HOST);
		$path = (string) parse_url($url, PHP_URL_PATH);

		if ($host === '' || $path === '') {
			return null;
		}

		// Match on the bare slug so the two URL shapes Výveska now mixes — the
		// legacy "…/slug.html" (still used in the RSS feed for many items) and
		// the new "…/slug/" (used on the listing) — resolve to the same key.
		$slug = trim($path, '/');
		$slug = preg_replace('/\.html$/i', '', $slug) ?? $slug;

		return strtolower($host . '/' . $slug);
	}

	private function normalizeWhitespace(string $value): string
	{
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = preg_replace('/\h+/u', ' ', $value) ?? $value;
		$value = preg_replace('/\s*\n\s*/u', "\n", $value) ?? $value;

		return trim($value);
	}
}
