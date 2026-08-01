<?php

namespace Tests\Feature\Imports;

use App\Services\Imports\PdfConverterService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfConverterServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pdf_converter.url', 'http://converter.test');
        config()->set('services.pdf_converter.token', 'test-token');
    }

    #[Test]
    public function it_does_not_call_the_converter_for_a_non_pdf_upload(): void
    {
        Http::fake();

        $docx = "PK\x03\x04" . str_repeat('x', 200);

        $result = app(PdfConverterService::class)->convertFromBinary($docx, 'document.docx');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_calls_the_converter_for_a_pdf_upload(): void
    {
        Http::fake([
            'converter.test/*' => Http::response([
                'page_count' => 1,
                'pages' => [['page' => 1, 'image' => 'data:image/png;base64,aGk=']],
            ]),
        ]);

        $result = app(PdfConverterService::class)->convertFromBinary($this->pdfBinary(), 'document.pdf');

        $this->assertNotNull($result);
        $this->assertSame(1, $result->pageCount);
        Http::assertSent(fn ($request) => $request->url() === 'http://converter.test/api/pdf-convert');
    }

    #[Test]
    public function it_accepts_a_pdf_whose_header_sits_behind_leading_bytes(): void
    {
        Http::fake([
            'converter.test/*' => Http::response([
                'page_count' => 1,
                'pages' => [['page' => 1, 'image' => 'data:image/png;base64,aGk=']],
            ]),
        ]);

        $result = app(PdfConverterService::class)->convertFromBinary(
            str_repeat("\n", 100) . $this->pdfBinary(),
            'document.pdf',
        );

        $this->assertNotNull($result);
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_reports_the_converter_status_when_the_upload_is_rejected(): void
    {
        // 413 vracia nginx pred konvertorom pri prekročení client_max_body_size.
        // Volajúci to musí vedieť odlíšiť od dočasného výpadku — pri 413 nemá
        // zmysel radiť „skúste znova", ten súbor neprejde nikdy.
        Http::fake([
            'converter.test/*' => Http::response('<html><title>413 Request Entity Too Large</title></html>', 413),
        ]);

        $failureStatus = null;
        $result = app(PdfConverterService::class)
            ->convertFromBinary($this->pdfBinary(), 'velky-plagat.pdf', $failureStatus);

        $this->assertNull($result);
        $this->assertSame(413, $failureStatus);
    }

    #[Test]
    public function failure_status_stays_null_on_a_successful_conversion(): void
    {
        Http::fake([
            'converter.test/*' => Http::response([
                'page_count' => 1,
                'pages' => [['page' => 1, 'image' => 'data:image/png;base64,aGk=']],
            ]),
        ]);

        $failureStatus = 999;
        app(PdfConverterService::class)->convertFromBinary($this->pdfBinary(), 'document.pdf', $failureStatus);

        $this->assertNull($failureStatus);
    }

    private function pdfBinary(): string
    {
        return "%PDF-1.4\n" . str_repeat('x', 200) . "\n%%EOF";
    }
}
