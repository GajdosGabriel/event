<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Support\PublicUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * QR kód pozvánky na akciu (panel Propagácia v správe akcie).
 */
class EventQrTest extends EventSetupTest
{
    #[Test]
    public function published_event_has_png_qr_for_guests(): void
    {
        $this->app['auth']->forgetGuards(); // náhľad je obyčajný <img>, bez prihlásenia

        $this->futureEvent->update(['status' => ModelStatus::Published->value]);

        $response = $this->get("/api/events/{$this->futureEvent->id}/qr.png?size=300");

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));

        $size = getimagesizefromstring((string) $response->getContent());
        // Tichá zóna sa pridáva okolo kódu, takže obrázok je o ňu väčší.
        $this->assertSame($size[0], $size[1]);
        $this->assertGreaterThanOrEqual(300, $size[0]);
    }

    #[Test]
    public function download_flag_forces_attachment_with_readable_filename(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Published->value, 'name' => 'Koncert v parku']);

        $response = $this->get("/api/events/{$this->futureEvent->id}/qr.png?download=1");

        $response->assertOk();
        $this->assertSame(
            'attachment; filename="qr-koncert-v-parku.png"',
            $response->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function published_event_has_svg_qr(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);

        $response = $this->get("/api/events/{$this->futureEvent->id}/qr.svg");

        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', (string) $response->getContent());
    }

    #[Test]
    public function draft_or_trashed_event_has_no_qr(): void
    {
        // Koncept zostáva z fixture — vytlačený kód by viedol na 404.
        $this->get("/api/events/{$this->futureEvent->id}/qr.png")->assertNotFound();
        $this->get("/api/events/{$this->futureEvent->id}/qr.svg")->assertNotFound();

        $this->futureEvent->update(['status' => ModelStatus::Published->value]);
        $this->futureEvent->delete();

        $this->get("/api/events/{$this->futureEvent->id}/qr.png")->assertNotFound();
    }

    #[Test]
    public function invite_url_is_short_and_leads_to_registration_when_reservable(): void
    {
        $base = PublicUrl::absolute(PublicUrl::EVENTS.'/'.$this->futureEvent->id);

        // Príznak z withTicketCtaFlags() — akcia s aktívnym typom lístka.
        $this->futureEvent->setAttribute('has_active_ticket_types', true);
        $this->assertSame($base.'#registracia', PublicUrl::eventInvite($this->futureEvent));
    }
}
