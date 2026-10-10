<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalEmailStatus;
use App\Enums\CanalRole;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalEmail;
use App\Models\EmailSuppression;
use App\Models\SystemLog;
use App\Models\User;
use App\Repositories\Contracts\CanalRepository;
use App\Services\Canals\CanalEmails;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Viac e-mailov na kanál: jedna primárna (zrkadlená v `canals.email`),
 * vrátený e-mail ju zosadí, potvrdenie alebo odpoveď ju vráti.
 */
class CanalEmailsTest extends TestCase
{
    use RefreshDatabase;

    private CanalEmails $emails;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        Http::fake(['*' => Http::response([])]);

        $this->emails = app(CanalEmails::class);
    }

    #[Test]
    public function canal_email_becomes_its_primary_address(): void
    {
        $canal = $this->canal('Info@Divadlo.test', verified: true);

        $row = $canal->emails()->sole();
        $this->assertSame('info@divadlo.test', $row->email);
        $this->assertTrue($row->is_primary);
        $this->assertSame(CanalEmailStatus::Verified, $row->status);
        $this->assertSame('info@divadlo.test', $canal->fresh()->email);
        $this->assertNotNull($canal->fresh()->email_verified_at);
    }

    #[Test]
    public function form_keeps_the_whole_list_and_removes_what_is_missing(): void
    {
        $canal = $this->canal('a@divadlo.test');
        $repository = app(CanalRepository::class);

        $repository->update($canal->id, ['email' => 'a@divadlo.test', 'additional_emails' => ['B@divadlo.test', 'c@divadlo.test', 'a@divadlo.test']]);
        $this->assertSame(['a@divadlo.test', 'b@divadlo.test', 'c@divadlo.test'], $this->emails->all($canal)->pluck('email')->all());

        // Iná primárna, jedna adresa preč.
        $repository->update($canal->id, ['email' => 'c@divadlo.test', 'additional_emails' => ['a@divadlo.test']]);

        $this->assertSame(['c@divadlo.test', 'a@divadlo.test'], $this->emails->all($canal)->pluck('email')->all());
        $this->assertSame('c@divadlo.test', $canal->fresh()->email);
        $this->assertSame(1, $canal->emails()->where('is_primary', true)->count());
    }

    #[Test]
    public function changing_only_the_column_keeps_the_previous_address_as_additional(): void
    {
        $canal = $this->canal('stary@divadlo.test', verified: true);

        $canal->update(['email' => 'novy@divadlo.test']);

        $this->assertSame('novy@divadlo.test', $this->emails->primary($canal)->email);
        $this->assertNull($canal->fresh()->email_verified_at);
        $this->assertTrue($canal->emails()->where('email', 'stary@divadlo.test')->sole()->isVerified());
    }

    #[Test]
    public function bounce_demotes_the_primary_and_promotes_the_best_remaining(): void
    {
        $canal = $this->canal('zla@divadlo.test');
        $this->emails->sync($canal, 'zla@divadlo.test', ['neoverena@divadlo.test', 'overena@divadlo.test']);
        $this->emails->confirm($canal, 'overena@divadlo.test', 'test');
        // Overená adresa nahradila neoverenú primárnu; vrátime ju späť ručne.
        $this->emails->sync($canal, 'zla@divadlo.test', ['neoverena@divadlo.test', 'overena@divadlo.test']);
        $this->assertSame('zla@divadlo.test', $canal->fresh()->email);

        $this->assertSame(1, $this->emails->recordBounce('ZLA@divadlo.test', CanalEmail::BOUNCE_HARD, '550 user unknown'));

        $bad = $canal->emails()->where('email', 'zla@divadlo.test')->sole();
        $this->assertSame(CanalEmailStatus::Undeliverable, $bad->status);
        $this->assertFalse($bad->is_primary);
        $this->assertSame('550 user unknown', $bad->bounce_reason);
        $this->assertSame('overena@divadlo.test', $canal->fresh()->email);
        $this->assertNotNull($canal->fresh()->email_verified_at);
        $this->assertTrue(EmailSuppression::has('zla@divadlo.test'));
        $this->assertTrue(SystemLog::where('event', 'canals.email_bounced')->where('recipient', 'zla@divadlo.test')->exists());
    }

    #[Test]
    public function bounce_of_the_only_address_leaves_the_canal_without_contact(): void
    {
        $canal = $this->canal('plna@divadlo.test', verified: true);

        $this->emails->recordBounce('plna@divadlo.test', CanalEmail::BOUNCE_SOFT, 'mailbox full');

        $canal->refresh();
        $this->assertNull($canal->email);
        $this->assertNull($canal->email_verified_at);
        $this->assertSame(0, $canal->emails()->where('is_primary', true)->count());
        // Plná schránka je dočasná — adresa sa globálne neblokuje.
        $this->assertFalse(EmailSuppression::has('plna@divadlo.test'));

        // Uloženie formulára bez zmeny ju späť neoživí…
        app(CanalRepository::class)->update($canal->id, ['email' => null, 'additional_emails' => ['plna@divadlo.test']]);
        $this->assertNull($canal->fresh()->email);

        // …výber za primárnu áno, ako neoverenú.
        app(CanalRepository::class)->update($canal->id, ['email' => 'plna@divadlo.test', 'additional_emails' => []]);
        $this->assertSame('plna@divadlo.test', $canal->fresh()->email);
        $this->assertSame(CanalEmailStatus::Unverified, $canal->emails()->sole()->status);
    }

    #[Test]
    public function reply_revives_a_bounced_address_and_beats_an_unverified_primary(): void
    {
        $canal = $this->canal('prva@divadlo.test');
        $this->emails->sync($canal, 'prva@divadlo.test', ['druha@divadlo.test']);
        $this->emails->recordBounce('druha@divadlo.test');

        $this->assertSame(1, $this->emails->recordReply('druha@divadlo.test'));

        $this->assertSame('druha@divadlo.test', $canal->fresh()->email);
        $this->assertNotNull($canal->fresh()->email_verified_at);
        $this->assertFalse(EmailSuppression::has('druha@divadlo.test'));

        // Overenú primárnu už ďalšia odpoveď nevymení.
        $this->emails->recordReply('prva@divadlo.test');
        $this->assertSame('druha@divadlo.test', $canal->fresh()->email);
        $this->assertSame(0, $this->emails->recordReply('nikto@inde.test'));
    }

    #[Test]
    public function editor_sees_all_addresses_and_the_public_none(): void
    {
        $owner = User::factory()->create(['last_login_at' => now(), 'last_activity' => now()]);
        $canal = $this->canal('a@divadlo.test');
        app(CanalMembership::class)->attach($canal, $owner, CanalRole::Owner);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$canal->id}", [
                'name' => $canal->name,
                'municipality_id' => $canal->municipality_id,
                'email' => 'a@divadlo.test',
                'additional_emails' => ['b@divadlo.test'],
            ])
            ->assertOk()
            ->assertJsonPath('emails.0.email', 'a@divadlo.test')
            ->assertJsonPath('emails.0.is_primary', true)
            ->assertJsonPath('emails.1.email', 'b@divadlo.test')
            ->assertJsonPath('emails.1.status', 'unverified');

        $this->putJson("/api/dashboard/canals/{$canal->id}", [
            'name' => $canal->name,
            'municipality_id' => $canal->municipality_id,
            'email' => 'a@divadlo.test',
            'additional_emails' => ['nie-je-email'],
        ])->assertJsonValidationErrors('additional_emails.0');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/canals/{$canal->id}")->assertJsonMissingPath('emails');
    }

    private function canal(string $email, bool $verified = false): Canal
    {
        return Canal::factory()->active()->create([
            'registration_source' => RegistrationSource::SELF->value,
            'email' => $email,
            'email_verified_at' => $verified ? now() : null,
        ]);
    }
}
