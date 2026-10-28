<?php

namespace Tests\Feature\Tickets;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Canals\CanalMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Emailing v Accounte (ACCOUNT_MAILING): registrácie, ich zrušenie a koniec
 * podujatia sa hlásia do Accountu, kde na ne bežia kampane.
 */
class AccountMailingTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        config()->set('account.url', 'http://account.test');
        config()->set('account.token', 'acc_test');
        config()->set('account.mailing', true);

        Http::fake([
            'account.test/api/v1/mailing/events' => Http::response(['data' => ['queued' => 1]], 202),
            'account.test/api/v1/mailing/events/cancel' => Http::response(['data' => ['skipped' => 1]]),
            'account.test/api/v1/mailing/triggers' => Http::response(['data' => []]),
            'account.test/api/v1/mailing/contacts' => Http::response(['data' => ['added' => 1, 'updated' => 0]], 201),
            'account.test/*' => Http::response(['data' => []]),
        ]);
    }

    #[Test]
    public function registration_reports_event_to_account(): void
    {
        $ticket = $this->register('Ucastnik@Example.com');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events'
            && $request['event'] === 'ticket_registered'
            && $request['email'] === 'ucastnik@example.com'
            && $request['name'] === 'Ján Účastník'
            && $request['ref']['type'] === 'ticket'
            && $request['ref']['id'] === $ticket->uuid
            && $request['dedupe_key'] === 'ticket:'.$ticket->uuid
            && $request['variables']['podujatie'] === $this->futureEvent->name
            && $request['variables']['pocet_miest'] === 2
            && str_ends_with($request['variables']['odkaz_vstupenky'], '/tickets/'.$ticket->uuid));
    }

    #[Test]
    public function cancelled_registration_cancels_pending_mails_and_reports_cancellation(): void
    {
        $ticket = $this->register('zrusenie@example.com');

        $ticket->update(['status' => TicketStatus::Cancelled->value]);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events/cancel'
            && $request['ref'] === ['type' => 'ticket', 'id' => $ticket->uuid]
            && $request['except'] === ['ticket_cancelled']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events'
            && $request['event'] === 'ticket_cancelled'
            && $request['ref']['id'] === $ticket->uuid);
    }

    #[Test]
    public function finished_event_reports_to_holders_with_valid_registration(): void
    {
        $kept = $this->register('prisiel@example.com');
        $cancelled = $this->register('zrusil@example.com');
        $cancelled->update(['status' => TicketStatus::Cancelled->value]);

        $this->futureEvent->forceFill([
            'status' => ModelStatus::Published->value,
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHour(),
        ])->saveQuietly();

        $this->artisan('app:events-archive-finished')->assertSuccessful();

        $finished = Http::recorded(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events'
            && $request['event'] === 'event_finished');

        $this->assertCount(1, $finished);
        $this->assertSame($kept->uuid, $finished->first()[0]['ref']['id']);
        $this->assertSame(ModelStatus::Archived, $this->futureEvent->fresh()->status);
    }

    #[Test]
    public function nothing_is_reported_when_mailing_is_disabled(): void
    {
        config()->set('account.mailing', false);

        $this->register('vypnute@example.com');
        $this->artisan('emailing:register-triggers')->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function register_triggers_sends_all_events(): void
    {
        $this->artisan('emailing:register-triggers')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'http://account.test/api/v1/mailing/triggers'
            && collect($request['triggers'])->pluck('key')->all() === ['ticket_registered', 'ticket_cancelled', 'event_finished', 'event_published', 'canal_event_upcoming', 'canal_views_milestone', 'canal_event_finished']
            && in_array('odkaz_podujatia', $request['triggers'][0]['variables'], true)
            && in_array('odkaz_upravy', $request['triggers'][3]['variables'], true));

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'http://account.test/api/v1/mailing/tags'
            && collect($request['tags'])->pluck('key')->all() === ['user', 'verified', 'organizer', 'canal_contact', 'attendee']);
    }

    #[Test]
    public function registration_sends_attendee_contact_before_the_event(): void
    {
        $this->register('Kontakt@Example.com');

        $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url())
            ->filter(fn ($url) => str_contains($url, '/mailing/contacts') || str_ends_with($url, '/mailing/events'))
            ->values();

        $this->assertSame('http://account.test/api/v1/mailing/contacts', $urls->first());

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/contacts'
            && $request['contacts'][0]['email'] === 'kontakt@example.com'
            && in_array('attendee', $request['contacts'][0]['tags'], true)
            && $request['contacts'][0]['attributes']['meno'] === 'Ján Účastník');
    }

    #[Test]
    public function publishing_event_reports_technical_mail_to_canal_owners(): void
    {
        $this->managedCanal();

        $this->futureEvent->update(['status' => ModelStatus::Published->value, 'published_at' => now()]);

        $email = mb_strtolower($this->user->email);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events'
            && $request['event'] === 'event_published'
            && $request['email'] === $email
            && $request['ref'] === [
                'type' => 'event',
                'id' => (string) $this->futureEvent->id,
                'label' => 'Akcia – '.$this->futureEvent->name,
                'url' => rtrim((string) config('app.frontend_url'), '/').'/dashboard/events/'.$this->futureEvent->id,
            ]
            && $request['dedupe_key'] === 'event:'.$this->futureEvent->id
            && $request['variables']['podujatie'] === $this->futureEvent->name
            && str_ends_with($request['variables']['odkaz_upravy'], '/dashboard/events/'.$this->futureEvent->id.'/edit'));

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/contacts'
            && $request['contacts'][0]['email'] === $email
            && in_array('organizer', $request['contacts'][0]['tags'], true));
    }

    #[Test]
    public function unpublishing_event_cancels_its_pending_mails(): void
    {
        $this->managedCanal();
        $this->futureEvent->update(['status' => ModelStatus::Published->value, 'published_at' => now()]);

        $this->futureEvent->update(['status' => ModelStatus::Draft->value]);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events/cancel'
            && $request['ref'] === ['type' => 'event', 'id' => (string) $this->futureEvent->id]);
    }

    #[Test]
    public function imported_event_and_unmanaged_canal_get_no_technical_mail(): void
    {
        $this->managedCanal();
        $this->futureEvent->forceFill(['orginal_source' => 'https://obec.sk/akcie/1'])->saveQuietly();
        $this->futureEvent->update(['status' => ModelStatus::Published->value, 'published_at' => now()]);

        $this->canalPrimary->forceFill(['registration_source' => RegistrationSource::IMPORT->value, 'claimed_at' => null])->saveQuietly();
        $this->pastEvent->update(['status' => ModelStatus::Published->value, 'published_at' => now()]);

        Http::assertNotSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events'
            && $request['event'] === 'event_published');
    }

    #[Test]
    public function sync_command_sends_directory_with_tags(): void
    {
        $this->managedCanal();
        $this->register('ucastnik@example.com');
        Http::fake(['account.test/*' => Http::response(['data' => ['added' => 1, 'updated' => 0]], 201)]);

        $this->artisan('account:sync-mailing-contacts')->assertSuccessful();

        $contacts = Http::recorded(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/contacts')
            ->flatMap(fn ($pair) => $pair[0]['contacts'])
            ->keyBy('email');

        $this->assertContains('organizer', $contacts[mb_strtolower($this->user->email)]['tags']);
        $this->assertContains('attendee', $contacts['ucastnik@example.com']['tags']);
        $this->assertArrayNotHasKey('permission_note', $contacts['ucastnik@example.com']);
    }

    /** Hlavný kanál používateľa ako vlastný (nie import) a on jeho vlastníkom. */
    private function managedCanal(): void
    {
        $this->canalPrimary->forceFill(['registration_source' => RegistrationSource::SELF->value])->saveQuietly();
        app(CanalMembership::class)->attach($this->canalPrimary, $this->user, CanalRole::Owner);
    }

    private function register(string $email): Ticket
    {
        $this->app['auth']->forgetGuards();

        $type = $this->futureEvent->ticketTypes()->firstOrCreate(
            ['name' => 'Standard'],
            ['price_amount' => 0, 'is_active' => true],
        );

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Ján Účastník',
            'holder_email' => $email,
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 2]],
        ])->assertStatus(201);

        return Ticket::query()->where('holder_email', mb_strtolower($email))->firstOrFail();
    }
}
