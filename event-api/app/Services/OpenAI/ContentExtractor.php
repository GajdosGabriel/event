<?php

namespace App\Services\OpenAI;

class ContentExtractor
{
    public function extract(string $html, string $baseUrl, string $contentId = 'content'): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $element = $dom->getElementById($contentId);
        if (!$element) {
            foreach (['content-body', 'content', 'main-content', 'main'] as $fallbackId) {
                $element = $dom->getElementById($fallbackId);
                if ($element) {
                    break;
                }
            }
        }

        if (! $element) {
            $xpath = new \DOMXPath($dom);
            $element = $this->resolveFallbackElement($xpath, $baseUrl);
        }

        if (!$element) {
            throw new \RuntimeException('Element pre hlavny obsah sa nenasiel');
        }

        $this->removeIgnoredNodes($element, $baseUrl);

        $innerHtml = '';
        foreach ($element->childNodes as $child) {
            $innerHtml .= $dom->saveHTML($child);
        }

        $text = strip_tags($innerHtml);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = $this->sanitizeUtf8(trim((string) $text));

        return [
            'text' => $text,
            'attachments' => $this->extractAttachments($element, $baseUrl),
        ];
    }

    private function resolveFallbackElement(\DOMXPath $xpath, string $baseUrl): ?\DOMElement
    {
        $expressions = [];

        if ($this->isTkkbsUrl($baseUrl)) {
            $expressions[] = "//body//center//table//tr//td[contains(concat(' ', normalize-space(@class), ' '), ' stredblok ')]";
        }

        $expressions[] = "//td[contains(concat(' ', normalize-space(@class), ' '), ' stredblok ')]";

        foreach ($expressions as $expression) {
            $nodes = $xpath->query($expression);
            if (! $nodes instanceof \DOMNodeList || $nodes->length === 0) {
                continue;
            }

            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                return $node;
            }
        }

        return null;
    }

    private function removeIgnoredNodes(\DOMElement $element, string $baseUrl): void
    {
        if (! $this->isTkkbsUrl($baseUrl)) {
            return;
        }

        $document = $element->ownerDocument;
        if (! $document instanceof \DOMDocument) {
            return;
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('.//img[@src]', $element);

        foreach ($nodes ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $src = trim($node->getAttribute('src'));
            if (! $this->isIgnoredTkkbsImage($src, $baseUrl)) {
                continue;
            }

            $node->parentNode?->removeChild($node);
        }
    }

    private function extractAttachments(\DOMElement $element, string $baseUrl): array
    {
        $attachments = [];
        $links = $element->getElementsByTagName('a');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $linkText = $this->sanitizeUtf8($link->textContent);

            if (strpos($href, '.pdf') === false && stripos($linkText, 'priloha') === false) {
                continue;
            }

            $absoluteUrl = $this->toAbsoluteUrl($href, $baseUrl);
            $filename = basename((string) parse_url($absoluteUrl, PHP_URL_PATH));
            if ($filename === '') {
                $filename = 'priloha_' . time() . '.pdf';
            }

            $attachments[] = [
                'url' => $absoluteUrl,
                'name' => $filename,
                'link_text' => trim($linkText),
                'size' => $this->extractFileSizeFromText($linkText),
            ];
        }

        return $attachments;
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): string
    {
        if (strpos($href, 'http') === 0) {
            return $href;
        }

        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $path = str_starts_with($href, '/') ? $href : '/' . $href;

        return "{$scheme}://{$host}{$path}";
    }

    private function extractFileSizeFromText(string $text): ?string
    {
        if (preg_match('/(\d+[,.]?\d*)\s*(kB|MB|GB)/i', $text, $matches)) {
            return $matches[1] . ' ' . $matches[2];
        }

        return null;
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        $converted = @iconv('Windows-1250', 'UTF-8//IGNORE', $value);
        if ($converted === false) {
            $converted = @iconv('ISO-8859-2', 'UTF-8//IGNORE', $value);
        }

        if ($converted === false) {
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
        }

        return $converted === false ? '' : $converted;
    }

    private function isTkkbsUrl(string $url): bool
    {
        return str_contains((string) parse_url($url, PHP_URL_HOST), 'tkkbs.sk');
    }

    private function isIgnoredTkkbsImage(string $src, string $baseUrl): bool
    {
        if ($src === '') {
            return false;
        }

        $absoluteUrl = $this->toAbsoluteUrl($src, $baseUrl);

        return str_ends_with(strtolower($absoluteUrl), '/image/tkkbs/tkkbs_logo.gif');
    }
}
