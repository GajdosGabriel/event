<?php

namespace App\Services\Imports;

class EventListingService
{
	public function __construct(private readonly ImportPageFetcher $pageFetcher)
	{
	}

	/**
	 * @return array<int, string>
	 */
	public function listArticleUrls(string $listingUrl, int $maxPages = 1, ?int $limit = null): array
	{
		$urls = [];
		$pageUrl = $listingUrl;

		for ($page = 1; $page <= max(1, $maxPages); $page++) {
			$html = $this->pageFetcher->fetch($pageUrl);

			foreach ($this->extractArticleUrls($html, $pageUrl, $listingUrl) as $url) {
				if (! in_array($url, $urls, true)) {
					$urls[] = $url;
				}

				if ($limit !== null && count($urls) >= $limit) {
					return array_slice($urls, 0, $limit);
				}
			}

			$nextPageUrl = $this->extractNextPageUrl($html, $pageUrl, $listingUrl, $page + 1);
			if ($nextPageUrl === null) {
				break;
			}

			$pageUrl = $nextPageUrl;
		}

		return $limit !== null ? array_slice($urls, 0, $limit) : $urls;
	}

	/**
	 * @return array<int, string>
	 */
	private function extractArticleUrls(string $html, string $currentUrl, string $listingUrl): array
	{
		if ($this->isTkkbsUrl($listingUrl)) {
			$links = $this->extractLinks($html, $currentUrl);
			return array_values(array_filter($links, fn (string $url) => $this->isTkkbsArticleUrl($url)));
		}

		if ($this->isVyveskaUrl($listingUrl)) {
			return $this->extractVyveskaArticleUrls($html, $currentUrl);
		}

		$links = $this->extractLinks($html, $currentUrl);

		return array_values(array_filter($links, fn (string $url) => $this->isEcavArticleUrl($url)));
	}

	private function extractNextPageUrl(string $html, string $currentUrl, string $listingUrl, int $pageNumber): ?string
	{
		if ($this->isTkkbsUrl($listingUrl)) {
			return $this->extractNextTkkbsPageUrl($html, $currentUrl, $pageNumber);
		}

		return null;
	}

	private function extractNextTkkbsPageUrl(string $html, string $currentUrl, int $pageNumber): ?string
	{
		foreach ($this->extractLinks($html, $currentUrl) as $url) {
			if (! str_contains($url, 'search.php')) {
				continue;
			}

			if (preg_match('/[?&]rskolikata=' . preg_quote((string) $pageNumber, '/') . '([&#]|$)/', $url)) {
				return $url;
			}
		}

		return null;
	}

	/**
	 * @return array<int, string>
	 */
	private function extractLinks(string $html, string $baseUrl): array
	{
		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();

		$xpath = new \DOMXPath($document);
		$nodes = $xpath->query('//a[@href]');
		$urls = [];

		foreach ($nodes ?: [] as $node) {
			$href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
			if ($href === '') {
				continue;
			}

			$absoluteUrl = $this->absoluteUrl($baseUrl, $href);
			if ($absoluteUrl === null) {
				continue;
			}

			$urls[] = $absoluteUrl;
		}

		return array_values(array_unique($urls));
	}

	private function isEcavArticleUrl(string $url): bool
	{
		$path = (string) parse_url($url, PHP_URL_PATH);

		return str_starts_with($url, 'https://www.ecav.sk/aktuality/pozvanky/')
			&& $path !== '/aktuality/pozvanky'
			&& $path !== '/aktuality/pozvanky/';
	}

	private function isTkkbsUrl(string $url): bool
	{
		return str_contains((string) parse_url($url, PHP_URL_HOST), 'tkkbs.sk');
	}

	private function isVyveskaUrl(string $url): bool
	{
		return str_contains((string) parse_url($url, PHP_URL_HOST), 'vyveska.sk');
	}

	private function isTkkbsArticleUrl(string $url): bool
	{
		return str_contains($url, 'tkkbs.sk/view.php') && preg_match('/[?&]cisloclanku=\d+/', $url) === 1;
	}

	private function isVyveskaArticleUrl(string $url): bool
	{
		$host = (string) parse_url($url, PHP_URL_HOST);
		$path = (string) parse_url($url, PHP_URL_PATH);

		if (! str_contains($host, 'vyveska.sk')) {
			return false;
		}

		if (! str_ends_with($path, '.html')) {
			return false;
		}

		return ! str_contains($path, '/zoznam-podujati/');
	}

	/**
	 * @return array<int, string>
	 */
	private function extractVyveskaArticleUrls(string $html, string $baseUrl): array
	{
		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();

		$xpath = new \DOMXPath($document);
		$nodes = $xpath->query('//*[@id="content-body"]//h3/a[@href]');
		$urls = [];

		foreach ($nodes ?: [] as $node) {
			$href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
			$absoluteUrl = $this->absoluteUrl($baseUrl, $href);

			if ($absoluteUrl === null || ! $this->isVyveskaArticleUrl($absoluteUrl)) {
				continue;
			}

			$urls[] = $absoluteUrl;
		}

		return array_values(array_unique($urls));
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
