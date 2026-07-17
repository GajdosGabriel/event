<?php

namespace App\Services\Imports;

use DOMDocument;
use DOMNode;
use DOMXPath;
use SplObjectStorage;

/**
 * Cleans raw HTML scraped from event sources into safe, semantic HTML
 * suitable for storage and rendering with Tailwind Typography (prose).
 *
 * Keeps:  p, h2–h6, ul, ol, li, blockquote, strong, em, br, a[href]
 * Drops:  script, style, noscript, nav, form, iframe, h1 (it's the title)
 * Unwraps: div, section, article, span, figure, figcaption, header, footer
 * Converts: b → strong, i → em
 * Wraps:  loose text left over after unwrapping into p — sources that mark
 *         paragraphs with <br> instead of <p> would otherwise yield one block
 */
class HtmlBodyCleaner
{
    private const BLOCK = ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote'];
    private const INLINE = ['strong', 'em'];
    private const TRANSFORM = ['b' => 'strong', 'i' => 'em', 'u' => 'em'];
    private const UNWRAP = ['div', 'section', 'article', 'main', 'span', 'figure', 'figcaption', 'header', 'footer', 'aside', 'table', 'tbody', 'thead', 'tr', 'td', 'th'];
    private const DROP = ['script', 'style', 'noscript', 'form', 'nav', 'iframe', 'button', 'input', 'select', 'textarea', 'h1', 'img', 'picture', 'video', 'audio', 'canvas', 'svg'];

    /** Nodes the current cleanFromXPath() call must skip; null outside such a call. */
    private ?SplObjectStorage $excluded = null;

    /**
     * Cleans the inner HTML of all nodes matched by $expression.
     * Multiple matched nodes are joined as sibling content.
     *
     * $excludeExpression drops matched nodes and their descendants, which lets
     * callers point $expression at the whole article container instead of
     * hand-picking the tags they want — anything the container holds but the
     * body should not (bylines, date headings) is named once, explicitly.
     * Excluded nodes are skipped, not removed, so the caller's DOM stays intact
     * for the extraction that runs after this.
     */
    public function cleanFromXPath(DOMXPath $xpath, string $expression, ?string $excludeExpression = null): string
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $this->excluded = $this->collectExcluded($xpath, $excludeExpression);

        try {
            $parts = [];
            foreach ($nodes as $node) {
                $html = $this->cleanInner($node);
                if ($html !== '') {
                    $parts[] = $html;
                }
            }

            return $this->postProcess(implode("\n", $parts));
        } finally {
            $this->excluded = null;
        }
    }

    private function collectExcluded(DOMXPath $xpath, ?string $expression): ?SplObjectStorage
    {
        if ($expression === null) {
            return null;
        }

        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $excluded = new SplObjectStorage();
        foreach ($nodes as $node) {
            $excluded->attach($node);
        }

        return $excluded;
    }

    /**
     * Cleans the inner content of a single DOM node.
     */
    public function cleanInner(DOMNode $node): string
    {
        return $this->postProcess($this->renderSegments($this->segments($node)));
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

    /**
     * Flattens a node's children into an ordered list of block and inline
     * segments. Containers are unwrapped in place, so a block nested inside
     * <div>s still surfaces as a block here rather than as loose inline content.
     *
     * @return array<int, array{type: string, html: string}>
     */
    private function segments(DOMNode $node): array
    {
        $segments = [];
        foreach ($node->childNodes as $child) {
            foreach ($this->nodeSegments($child) as $segment) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * @return array<int, array{type: string, html: string}>
     */
    private function nodeSegments(DOMNode $node): array
    {
        if ($this->isExcluded($node)) {
            return [];
        }

        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($node->nodeName);
            $tag = self::TRANSFORM[$tag] ?? $tag;

            if (in_array($tag, self::DROP, true)) {
                return [];
            }

            $isLeaf = in_array($tag, self::BLOCK, true)
                || in_array($tag, self::INLINE, true)
                || in_array($tag, ['br', 'a'], true);

            if (! $isLeaf) {
                return $this->segments($node);
            }

            // A <p> holding consecutive <br> is several paragraphs the source
            // merged into one element — re-split it rather than trust the tag.
            if ($tag === 'p') {
                $html = $this->wrapInlineRun($this->processChildren($node));

                return $html === '' ? [] : [['type' => 'block', 'html' => $html]];
            }

            if (in_array($tag, self::BLOCK, true)) {
                $html = $this->processNode($node);

                return $html === '' ? [] : [['type' => 'block', 'html' => $html]];
            }
        }

        $html = $this->processNode($node);

        return $html === '' ? [] : [['type' => 'inline', 'html' => $html]];
    }

    /**
     * @param array<int, array{type: string, html: string}> $segments
     */
    private function renderSegments(array $segments): string
    {
        $output = '';
        $inlineRun = '';

        foreach ($segments as $segment) {
            if ($segment['type'] === 'inline') {
                $inlineRun .= $segment['html'];
                continue;
            }

            $output .= $this->wrapInlineRun($inlineRun) . $segment['html'];
            $inlineRun = '';
        }

        return $output . $this->wrapInlineRun($inlineRun);
    }

    /**
     * Wraps a run of loose inline content into paragraphs. Sources that never
     * emit <p> separate paragraphs with consecutive <br>, so a run of two or
     * more starts a new paragraph while a single one stays a line break.
     */
    private function wrapInlineRun(string $html): string
    {
        if (trim(strip_tags($html)) === '') {
            return '';
        }

        $output = '';
        foreach (preg_split('/(?:[\s\x{00A0}]*<br>[\s\x{00A0}]*){2,}/u', $html) ?: [$html] as $chunk) {
            $chunk = $this->normalizeInline($chunk);
            if (trim(strip_tags($chunk)) === '') {
                continue;
            }

            $output .= "<p>{$chunk}</p>\n";
        }

        return $output;
    }

    /**
     * Collapses the source's own line wrapping into single spaces, so a
     * paragraph reassembled from bare text nodes reads as one line. Sources use
     * &nbsp; as ordinary spacing, so it collapses too — otherwise it would
     * survive trimming and leave stray blank paragraphs.
     */
    private function normalizeInline(string $html): string
    {
        $html = preg_replace('/[\s\x{00A0}]+/u', ' ', $html) ?? $html;
        // A <br> at either edge carries no meaning once the run is split.
        $html = preg_replace('/^(?:\s*<br>\s*)+|(?:\s*<br>\s*)+$/', '', $html) ?? $html;

        return trim($html);
    }

    private function isExcluded(DOMNode $node): bool
    {
        return $this->excluded !== null && $this->excluded->contains($node);
    }

    private function processNode(DOMNode $node): string
    {
        if ($this->isExcluded($node)) {
            return '';
        }

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

        if (in_array($tag, self::BLOCK, true)) {
            $inner = $this->processChildren($node);
            if (trim(strip_tags($inner)) === '') {
                return '';
            }

            return "<{$tag}>{$inner}</{$tag}>\n";
        }

        if (in_array($tag, self::INLINE, true)) {
            $inner = $this->processChildren($node);
            if (trim(strip_tags($inner)) === '') {
                return '';
            }

            // No trailing newline: inline tags sit mid-sentence, and the extra
            // whitespace would surface as a gap before the following punctuation.
            return "<{$tag}>{$inner}</{$tag}>";
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
