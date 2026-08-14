<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Ticket;
use App\Notifications\AttendeeTicketIssued;
use App\Notifications\TicketIssued;
use App\Services\Calendar\IcsGenerator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * „Pridať do kalendára" — verejný `.ics` súbor a jeho príloha v e-maile
 * o vystavenom lístku.
 */
class EventCalendarIcsTest extends EventSetupTest
{
    #[Test]
    public function published_event_is_downloadable_as_ics(): void
    {
        $this->app['auth']->forgetGuards(); // aj neprihlásený hosť si termín zapíše

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'name' => 'Koncert v parku',
            'start_at' => Carbon::parse('2026-09-05 16:00:00'),
            'end_at' => Carbon::parse('2026-09-05 19:30:00'),
        ]);

        $response = $this->get("/api/events/{$this->futureEvent->id}/calendar.ics");

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.ics', (string) $response->headers->get('Content-Disposition'));

        $ics = (string) $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringContainsString('SUMMARY:Koncert v parku', $ics);
        // Termíny sú v DB v UTC, do súboru ich dávame tak isto — bez VTIMEZONE.
        $this->assertStringContainsString('DTSTART:20260905T160000Z', $ics);
        $this->assertStringContainsString('DTEND:20260905T193000Z', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    #[Test]
    public function event_without_end_gets_two_hour_slot(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => Carbon::parse('2026-09-05 16:00:00'),
            'end_at' => null,
        ]);

        $ics = (string) $this->get("/api/events/{$this->futureEvent->id}/calendar.ics")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('DTSTART:20260905T160000Z', $ics);
        $this->assertStringContainsString('DTEND:20260905T180000Z', $ics);
    }

    #[Test]
    public function all_day_event_is_written_as_a_date_range(): void
    {
        $this->app['auth']->forgetGuards();

        // 00:00–23:59 miestneho času = 15.–16. 8. celý deň. V DB (UTC) to je
        // posunuté o dve hodiny, celodennosť sa preto musí posudzovať lokálne.
        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => Carbon::parse('2026-08-14 22:00:00'),
            'end_at' => Carbon::parse('2026-08-16 21:59:59'),
        ]);

        $ics = (string) $this->get("/api/events/{$this->futureEvent->id}/calendar.ics")
            ->assertOk()
            ->getContent();

        // Koniec je v RFC 5545 exkluzívny — deň po poslednom dni podujatia.
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260815', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260817', $ics);
        $this->assertStringNotContainsString('DTSTART:', $ics);
        // Celý deň nemá blokovať kalendár ako „zaneprázdnený".
        $this->assertStringContainsString('TRANSP:TRANSPARENT', $ics);
    }

    #[Test]
    public function public_detail_offers_web_calendars_next_to_the_file(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => Carbon::parse('2026-09-05 16:00:00'),
            'end_at' => Carbon::parse('2026-09-05 19:30:00'),
        ]);

        $links = $this->get("/api/events/{$this->futureEvent->id}")
            ->assertOk()
            ->json('calendar_links');

        $this->assertSame(
            app(IcsGenerator::class)->downloadUrl($this->futureEvent),
            $links['download'],
        );
        $this->assertStringContainsString('dates=20260905T160000Z%2F20260905T193000Z', $links['google']);
        $this->assertStringContainsString('startdt=2026-09-05T16%3A00%3A00Z', $links['outlook']);
        $this->assertStringNotContainsString('allday', $links['outlook']);
    }

    #[Test]
    public function all_day_event_keeps_the_whole_day_in_web_calendars(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => Carbon::parse('2026-08-14 22:00:00'),
            'end_at' => Carbon::parse('2026-08-16 21:59:59'),
        ]);

        $links = $this->get("/api/events/{$this->futureEvent->id}")->assertOk()->json('calendar_links');

        $this->assertStringContainsString('dates=20260815%2F20260817', $links['google']);
        $this->assertStringContainsString('allday=true', $links['outlook']);
        $this->assertStringContainsString('startdt=2026-08-15', $links['outlook']);
        $this->assertStringContainsString('enddt=2026-08-17', $links['outlook']);
    }

    #[Test]
    public function public_detail_without_date_has_no_calendar_links(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => null,
        ]);

        $this->get("/api/events/{$this->futureEvent->id}")
            ->assertOk()
            ->assertJsonPath('calendar_links', null);
    }

    #[Test]
    public function location_does_not_repeat_the_same_name_twice(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published->value]);

        // Miesto pomenované rovnako ako obec — do adresy patrí len raz.
        $venue = $this->futureEvent->venue;
        $venue->update([
            'name' => $venue->municipality?->shortname ?? 'Kežmarok',
            'street' => null,
            'postcode' => null,
        ]);

        $ics = (string) $this->get("/api/events/{$this->futureEvent->id}/calendar.ics")
            ->assertOk()
            ->getContent();

        preg_match('/^LOCATION:(.*)$/m', $ics, $matches);

        $this->assertNotEmpty($matches, 'Podujatie s miestom musí mať LOCATION.');
        $this->assertStringNotContainsString(',', $matches[1]);
    }

    #[Test]
    public function draft_event_has_no_public_calendar(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Draft->value]);

        $this->get("/api/events/{$this->futureEvent->id}/calendar.ics")->assertNotFound();
    }

    #[Test]
    public function event_without_date_has_no_calendar(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => null,
        ]);

        $this->get("/api/events/{$this->futureEvent->id}/calendar.ics")->assertNotFound();
    }

    #[Test]
    public function ticket_email_carries_the_calendar_file_and_links(): void
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => Carbon::parse('2026-09-05 16:00:00'),
            'end_at' => Carbon::parse('2026-09-05 19:30:00'),
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $this->futureEvent->id,
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.com',
        ]);

        $mail = (new TicketIssued($ticket))->toMail($this->user);

        $attachment = collect($mail->rawAttachments)
            ->first(fn (array $item) => str_ends_with((string) $item['name'], '.ics'));

        $this->assertNotNull($attachment, 'E-mail o lístku musí niesť prílohu .ics');
        $this->assertStringContainsString('text/calendar', $attachment['options']['mime']);
        $this->assertStringContainsString('BEGIN:VEVENT', $attachment['data']);

        $this->assertSame(
            app(IcsGenerator::class)->downloadUrl($this->futureEvent),
            $mail->viewData['calendarUrl'],
        );
        $this->assertStringContainsString('calendar.google.com', (string) $mail->viewData['googleUrl']);
        $this->assertStringContainsString('outlook.live.com', (string) $mail->viewData['outlookUrl']);

        // Odkazy musia byť aj v tele — príloha sama o sebe tlačidlo v každom
        // klientovi nevykreslí.
        $html = (string) $mail->render();

        // Markdown musí byť naozaj preložený na odkazy, nie vypísaný ako text.
        $this->assertStringContainsString('<a href="'.$mail->viewData['calendarUrl'].'"', $html);
        $this->assertStringContainsString(__('mail.common.calendar_ics').'</a>', $html);
        $this->assertStringContainsString('calendar.google.com', $html);
        $this->assertStringContainsString('outlook.live.com', $html);
    }

    #[Test]
    public function attendee_email_carries_the_calendar_too(): void
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => Carbon::parse('2026-09-05 16:00:00'),
            'end_at' => Carbon::parse('2026-09-05 19:30:00'),
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $this->futureEvent->id,
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.com',
        ]);

        // Vstupenky (admissions) sem netreba — kalendár visí na podujatí, nie na
        // konkrétnom mieste v objednávke.
        $mail = (new AttendeeTicketIssued($ticket, []))->toMail($this->user);

        $this->assertNotEmpty($mail->rawAttachments);
        $this->assertStringContainsString(
            '<a href="'.$mail->viewData['calendarUrl'].'"',
            (string) $mail->render(),
        );
    }

    #[Test]
    public function ticket_email_for_event_without_date_skips_the_calendar(): void
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => null,
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $this->futureEvent->id,
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.com',
        ]);

        $mail = (new TicketIssued($ticket))->toMail($this->user);

        $this->assertSame([], $mail->rawAttachments);
        $this->assertNull($mail->viewData['calendarUrl']);
    }
}
