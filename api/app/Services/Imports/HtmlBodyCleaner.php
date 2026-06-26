<?php

namespace App\Services\Imports;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Cleans raw HTML scraped from event sources into safe, semantic HTML
 * suitable for storage and rendering with Tailwind Typography (prose).
 *
 * Keeps:  p, h2–h6, ul, ol, li, blockquote, strong, em, br, a[href]
 * Drops:  script, style, noscript, nav, form, iframe, h1 (it's the title)
 * Unwraps: div, section, article, span, figure, figcaption, header, footer
 * Converts: b → strong, i → em
 */
class HtmlBodyCleaner
{
    private const BLOCK = ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote'];
    private const INLINE = ['strong', 'em'];
    private const TRANSFORM = ['b' => 'strong', 'i' => 'em', 'u' => 'em'];
    private const UNWRAP = ['div', 'section', 'article', 'main', 'span', 'figure', 'figcaption', 'header', 'footer', 'aside', 'table', 'tbody', 'thead', 'tr', 'td', 'th'];
    private const DROP = ['script', 'style', 'noscript', 'form', 'nav', 'iframe', 'button', 'input', 'select', 'textarea', 'h1', 'img', 'picture', 'video', 'audio', 'canvas', 'svg'];

    /**
     * Cleans the inner HTML of all nodes matched by $expression.
     * Multiple matched nodes are joined as sibling content.
     */
    public function cleanFromXPath(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            $html = $this->cleanInner($node);
            if ($html !== '') {
                $parts[] = $html;
            }
        }

        return $this->postProcess(implode("\n", $parts));
    }

    /**
     * Cleans the inner content of a single DOM node.
     */
    public function cleanInner(DOMNode $node): string
    {
        $output = '';
        foreach ($node->childNodes as $child) {
            $output .= $this->processNode($child);
        }

        return $this->postProcess($output);
    }

    /**
     * Cleans an HTML string — useful for sanitising AI-generated HTML output.
     * Wraps the fragment in a temporary root, parses, and cleans.
     */
    public function cleanHtmlString(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        return $this->cleanFromXPath($xpath, '//body');
    }

    /**
     * Converts plain text (with \n\n paragraph breaks) to basic HTML.
     * Used as a fallback when no HTML structure is available.
     */
    public function fromPlainText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];
        $html = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            $lines = explode("\n", $para);
            $lines = array_map(fn (string $l) => htmlspecialchars(trim($l), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $lines);
            $html .= '<p>' . implode('<br>', $lines) . "</p>\n";
        }

        return trim($html);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function processNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars((string) ($node->nodeValue ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROP, true)) {
            return '';
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'a') {
            return $this->processAnchor($node);
        }

        // b → strong, i → em
        if (isset(self::TRANSFORM[$tag])) {
            $tag = self::TRANSFORM[$tag];
        }

        if (in_array($tag, self::UNWRAP, true)) {
            return $this->processChildren($node);
        }

        if (in_array($tag, self::BLOCK, true) || in_array($tag, self::INLINE, true)) {
            $inner = $this->processChildren($node);
            if (trim(strip_tags($inner)) === '') {
                return '';
            }

            return "<{$tag}>{$inner}</{$tag}>\n";
        }

        // Unknown element — unwrap
        return $this->processChildren($node);
    }

    private function processChildren(DOMNode $node): string
    {
        $output = '';
        foreach ($node->childNodes as $child) {
            $output .= $this->processNode($child);
        }

        return $output;
    }

    private function processAnchor(DOMNode $node): string
    {
        $href = $node->attributes?->getNamedItem('href')?->nodeValue ?? '';
        $inner = $this->processChildren($node);

        if (trim($inner) === '') {
            return '';
        }

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return $inner;
        }

        $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "<a href=\"{$safeHref}\">{$inner}</a>";
    }

    private function postProcess(string $html): string
    {
        // Collapse runs of blank lines to a single newline
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;

        return trim($html);
    }
}
