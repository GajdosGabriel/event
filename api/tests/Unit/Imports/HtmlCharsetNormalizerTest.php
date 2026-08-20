<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\HtmlCharsetNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlCharsetNormalizerTest extends TestCase
{
    /** mbstring na tomto builde Windows-1250 nepozná — fixtures stavia iconv. */
    private function cp1250(string $utf8): string
    {
        return (string) iconv('UTF-8', 'Windows-1250', $utf8);
    }

    #[Test]
    public function it_converts_windows_1250_declared_only_in_meta_tag(): void
    {
        $slovak = 'V nedeľu sa uskutoční púť mužov.';
        $html = $this->cp1250(
            '<html><head><META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=windows-1250"></head>'
            . '<body><p>' . $slovak . '</p></body></html>'
        );

        $normalized = (new HtmlCharsetNormalizer())->normalize($html, 'text/html');

        $this->assertStringContainsString($slovak, $normalized);
        $this->assertSame(1, preg_match('//u', $normalized));
    }

    #[Test]
    public function it_rewrites_the_meta_charset_so_the_dom_parser_does_not_decode_twice(): void
    {
        $html = $this->cp1250('<html><head><meta charset="windows-1250"></head><body>ľúbozvučná slovenčina</body></html>');

        $normalized = (new HtmlCharsetNormalizer())->normalize($html, null);

        $this->assertStringContainsString('charset="utf-8"', $normalized);
        $this->assertStringNotContainsString('windows-1250', $normalized);
    }

    #[Test]
    public function it_prefers_the_content_type_header_over_the_meta_tag(): void
    {
        $slovak = 'čerešňa';
        $html = $this->cp1250('<html><head><meta charset="utf-8"></head><body>' . $slovak . '</body></html>');

        $normalized = (new HtmlCharsetNormalizer())->normalize($html, 'text/html; charset=windows-1250');

        $this->assertStringContainsString($slovak, $normalized);
    }

    #[Test]
    public function it_falls_back_to_windows_1250_when_the_source_declares_nothing(): void
    {
        $html = $this->cp1250('<html><body>púť mužov</body></html>');

        $normalized = (new HtmlCharsetNormalizer())->normalize($html, 'text/html');

        $this->assertStringContainsString('púť mužov', $normalized);
    }

    #[Test]
    public function it_leaves_utf8_documents_untouched(): void
    {
        $html = '<html><head><meta charset="utf-8"></head><body>púť mužov</body></html>';

        $this->assertSame($html, (new HtmlCharsetNormalizer())->normalize($html, 'text/html; charset=UTF-8'));
    }
}
