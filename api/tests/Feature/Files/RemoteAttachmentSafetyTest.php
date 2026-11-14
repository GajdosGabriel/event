<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Services\Files\RemoteAttachmentPersister;
use App\Services\Files\RemoteAttachmentRejected;
use App\Support\Url;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prílohy z cudzích webov (RemoteAttachmentPersister::download).
 *
 * Adresu aj obsah má pod kontrolou cudzí server: presmerovanie nesmie
 * zaviesť sťahovanie do vnútornej siete a to, čo sa uloží, sa posudzuje
 * podľa obsahu — nie podľa hlavičky Content-Type ani textu odkazu.
 */
class RemoteAttachmentSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `*.test` sa skutočne nepreloží; tvári sa ako verejný web.
        $this->fakePublicDns();
    }

    private function download(string $url, FileType $type, ?string $name = null, ?string $linkText = null): mixed
    {
        return app(RemoteAttachmentPersister::class)->download(
            array_filter(['url' => $url, 'name' => $name, 'link_text' => $linkText]),
            $type,
        );
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    #[Test]
    public function a_redirect_to_localhost_is_refused_without_being_followed(): void
    {
        Http::fake([
            'https://zdroj.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin/secret.pdf']),
            '*' => Http::response(self::pdf(), 200, ['Content-Type' => 'application/pdf']),
        ]);

        try {
            $this->download('https://zdroj.test/plagat.pdf', FileType::FILE);
            $this->fail('Presmerovanie na 127.0.0.1 malo byť odmietnuté.');
        } catch (RemoteAttachmentRejected) {
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '127.0.0.1'));
    }

    #[Test]
    public function an_internal_address_is_refused_before_any_request(): void
    {
        Url::resolveHostsUsing(fn (string $host) => ['10.0.0.5']);
        Http::fake();

        $this->expectException(RemoteAttachmentRejected::class);

        try {
            $this->download('https://intranet.test/a.pdf', FileType::FILE);
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function html_sent_as_jpeg_is_refused(): void
    {
        Http::fake(['*' => Http::response('<html><script>alert(1)</script></html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->expectException(RemoteAttachmentRejected::class);

        $this->download('https://zdroj.test/foto.jpg', FileType::IMAGE, 'foto.jpg');
    }

    #[Test]
    public function svg_is_not_accepted_as_an_image(): void
    {
        Http::fake(['*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 200, ['Content-Type' => 'image/svg+xml'])]);

        $this->expectException(RemoteAttachmentRejected::class);

        $this->download('https://zdroj.test/logo.svg', FileType::IMAGE, 'logo.svg');
    }

    /** @return array<string, array{string, string, string}> */
    public static function forbiddenFiles(): array
    {
        return [
            'html' => ['stranka.html', '<html><body>ahoj</body></html>', 'text/html'],
            'svg' => ['obrazok.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'image/svg+xml'],
            'html v PDF hlavičke' => ['plagat.pdf', '<html><body>ahoj</body></html>', 'application/pdf'],
            'html ako txt' => ['pozvanka.txt', '<!DOCTYPE html><html><body>ahoj</body></html>', 'text/plain'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenFiles')]
    public function a_forbidden_attachment_is_refused(string $linkText, string $body, string $contentType): void
    {
        Http::fake(['*' => Http::response($body, 200, ['Content-Type' => $contentType])]);

        $this->expectException(RemoteAttachmentRejected::class);

        $this->download('https://zdroj.test/download.php?id=7', FileType::FILE, null, $linkText);
    }

    #[Test]
    public function a_pdf_is_stored_with_the_type_of_its_content(): void
    {
        Http::fake(['*' => Http::response(self::pdf(), 200, ['Content-Type' => 'application/octet-stream'])]);

        $download = $this->download('https://zdroj.test/download.php?id=7', FileType::FILE, null, 'Program.pdf');

        $this->assertSame('application/pdf', $download->mimeType);
        $this->assertSame('pdf', $download->extension);
    }

    /** Odkaz „Prihláška" bez prípony — prípona sa doplní z obsahu. */
    #[Test]
    public function a_missing_extension_is_taken_from_the_content(): void
    {
        Http::fake(['*' => Http::response(self::pdf(), 200, ['Content-Type' => 'application/pdf'])]);

        $download = $this->download('https://zdroj.test/download.php?id=7', FileType::FILE, 'subor.html', 'Prihláška');

        $this->assertSame('pdf', $download->extension);
        $this->assertStringEndsWith('.pdf', $download->safeOriginalName);
    }

    #[Test]
    public function an_image_extension_follows_the_real_format(): void
    {
        Http::fake(['*' => Http::response($this->fakeImage('png'), 200, ['Content-Type' => 'image/jpeg'])]);

        $download = $this->download('https://zdroj.test/foto.jpg', FileType::IMAGE, 'foto.jpg');

        $this->assertSame('image/png', $download->mimeType);
        $this->assertSame('png', $download->extension);
        $this->assertSame('foto.png', $download->safeOriginalName);
    }

    #[Test]
    public function an_oversized_attachment_is_refused(): void
    {
        config(['services.remote_attachments.max_bytes' => 100]);
        Http::fake(['*' => Http::response(self::pdf().str_repeat('x', 200), 200, ['Content-Type' => 'application/pdf'])]);

        $this->expectException(RemoteAttachmentRejected::class);

        $this->download('https://zdroj.test/velky.pdf', FileType::FILE, 'velky.pdf');
    }
}
