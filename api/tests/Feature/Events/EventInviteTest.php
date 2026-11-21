<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Enums\SlideTheme;
use App\Enums\SlideVariant;
use App\Services\Questions\SlideComposer;
use App\Services\Questions\SlideSpec;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Pozvánka na akciu ako obrázok s QR kódom (panel Propagácia).
 */
class EventInviteTest extends EventSetupTest
{
    private function publish(): void
    {
        $this->app['auth']->forgetGuards(); // náhľad aj stiahnutie idú bez prihlásenia

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'name' => 'Koncert v parku',
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(2),
        ]);
    }

    #[Test]
    public function every_invite_format_renders_in_its_own_size(): void
    {
        $this->publish();

        foreach ([
            SlideVariant::Square,
            SlideVariant::Story,
            SlideVariant::Slide,
            SlideVariant::Poster,
        ] as $variant) {
            $response = $this->get("/api/events/{$this->futureEvent->id}/invite.png?variant={$variant->value}&theme=bold");

            $response->assertOk()->assertHeader('Content-Type', 'image/png');
            [$width, $height] = getimagesizefromstring((string) $response->getContent());

            $this->assertSame([$variant->width(), $variant->height()], [$width, $height], $variant->value);
        }
    }

    #[Test]
    public function download_has_readable_filename(): void
    {
        $this->publish();

        $response = $this->get("/api/events/{$this->futureEvent->id}/invite.png?variant=story&download=1");

        $response->assertOk();
        $this->assertSame(
            'attachment; filename="pozvanka-koncert-v-parku-story.png"',
            $response->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function draft_event_has_no_invite_and_unknown_options_are_rejected(): void
    {
        $this->get("/api/events/{$this->futureEvent->id}/invite.png")->assertNotFound();

        $this->publish();

        $this->get("/api/events/{$this->futureEvent->id}/invite.png?variant=billboard")->assertStatus(422);
        $this->get("/api/events/{$this->futureEvent->id}/invite.png?theme=neon")->assertStatus(422);
    }

    #[Test]
    public function invite_content_leads_to_the_event(): void
    {
        $this->publish();

        // Akcia s aktívnym typom lístka — QR vedie rovno na registráciu.
        $this->futureEvent->setAttribute('has_active_ticket_types', true);

        $content = app(SlideComposer::class)->composeInvite($this->futureEvent);

        $this->assertSame('Koncert v parku', $content->title);
        $this->assertStringEndsWith("/akcie/{$this->futureEvent->id}?z=pozvanka#registracia", $content->qrUrl);
        // Pod QR krátka adresa bez schémy a bez kotvy — prepisuje sa rukou.
        $this->assertStringEndsWith("/akcie/{$this->futureEvent->id}", $content->url);
        $this->assertStringNotContainsString('://', $content->url);
        $this->assertSame(__('events.invite.cta_signup'), $content->cta);
    }

    #[Test]
    public function link_preview_image_is_rendered_once_and_cached(): void
    {
        $this->publish();

        $first = $this->get("/api/events/{$this->futureEvent->id}/og.png");
        $first->assertOk()->assertHeader('Content-Type', 'image/png');
        [$width, $height] = getimagesizefromstring((string) $first->getContent());
        $this->assertSame([1200, 630], [$width, $height]);

        // Druhé čítanie (ďalší crawler) príde z cache — ten istý obrázok.
        $this->assertSame($first->getContent(), $this->get("/api/events/{$this->futureEvent->id}/og.png")->getContent());

        // Pozvánka náhľad odkazu ako formát neponúka a snímka otázok tiež nie.
        $this->get("/api/events/{$this->futureEvent->id}/invite.png?variant=og")->assertStatus(422);
    }

    #[Test]
    public function draft_event_has_no_link_preview_image(): void
    {
        $this->get("/api/events/{$this->futureEvent->id}/og.png")->assertNotFound();
    }

    #[Test]
    public function existing_slide_layouts_are_unchanged(): void
    {
        // Pozvánka pridala formáty na výšku; široká snímka a štvorec nástenky
        // otázok musia ostať pixel po pixeli tam, kde boli.
        $slide = SlideSpec::for(SlideVariant::Slide, SlideTheme::Dark);
        $square = SlideSpec::for(SlideVariant::Square, SlideTheme::Dark);

        $this->assertSame([670, 515, 81], [$slide->cardW, $slide->qrSize, $slide->pad]);
        $this->assertSame([454, 349, 81], [$square->cardW, $square->qrSize, $square->pad]);
    }
}
