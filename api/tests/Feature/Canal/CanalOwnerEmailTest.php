<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalClaimMethod;
use App\Enums\CanalEmailStatus;
use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\CanalEmail;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Canals\CanalClaims;
use App\Services\Canals\CanalMembership;
use App\Services\Users\GuestAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * E-mail vlastníka medzi adresami kanála (CanalEmails::recordOwner /
 * forgetOwners): po prevzatí a pri novom vlastníkovi v tíme.
 */
class CanalOwnerEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->admin = $this->user('admin@portal.test');
        $this->admin->assignRole('super-admin');

        $this->canal = Canal::factory()->create([
            'name' => 'Farnosť Hájske',
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => 'info@farnost.sk',
            // Adresa z importu — schránka sa ešte neozvala.
            'email_verified_at' => null,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);
        app(CanalMembership::class)->attach($this->canal, $this->admin, CanalRole::Owner);
        SystemLog::query()->delete();
    }

    #[Test]
    public function admin_approved_takeover_makes_the_owner_email_primary_and_keeps_the_imported_one(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $this->approve($jozef);

        $canal = $this->canal->fresh();
        $this->assertSame('jozef@gmail.test', $canal->email);
        $this->assertNotNull($canal->email_verified_at);

        $owner = CanalEmail::where('canal_id', $canal->id)->where('email', 'jozef@gmail.test')->sole();
        $this->assertSame(CanalEmail::SOURCE_OWNER, $owner->source);
        $this->assertSame(CanalEmailStatus::Verified, $owner->status);
        $this->assertTrue($owner->is_primary);

        $imported = CanalEmail::where('canal_id', $canal->id)->where('email', 'info@farnost.sk')->sole();
        $this->assertFalse($imported->is_primary);
        $this->assertSame(CanalEmailStatus::Unverified, $imported->status);

        // Upozornenie o prevzatí odišlo ešte na pôvodný kontakt.
        $this->assertTrue(SystemLog::where('event', 'mail.simulated')->where('recipient', 'info@farnost.sk')->exists());
    }

    #[Test]
    public function confirmed_organization_mailbox_stays_primary_after_takeover(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $claim = app(CanalClaims::class)->request($this->canal, $jozef, CanalClaimMethod::ContactEmail);
        app(CanalClaims::class)->confirm($claim);

        $canal = $this->canal->fresh();
        $this->assertSame('info@farnost.sk', $canal->email);
        $this->assertNotNull($canal->email_verified_at);

        $owner = CanalEmail::where('canal_id', $canal->id)->where('email', 'jozef@gmail.test')->sole();
        $this->assertSame(CanalEmail::SOURCE_OWNER, $owner->source);
        $this->assertFalse($owner->is_primary);
    }

    #[Test]
    public function new_owner_replaces_the_previous_owner_who_stays_as_additional_address(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $maria = $this->user('maria@gmail.test');
        $this->approve($jozef);

        app(CanalMembership::class)->attach($this->canal->fresh(), $maria, CanalRole::Owner);

        $canal = $this->canal->fresh();
        $this->assertSame('maria@gmail.test', $canal->email);
        $this->assertEqualsCanonicalizing(
            ['info@farnost.sk', 'jozef@gmail.test', 'maria@gmail.test'],
            CanalEmail::where('canal_id', $canal->id)->pluck('email')->all(),
        );

        // Iná rola než vlastník adresu nepridáva.
        $peter = $this->user('peter@gmail.test');
        app(CanalMembership::class)->attach($canal, $peter, CanalRole::Editor);
        $this->assertFalse(CanalEmail::where('canal_id', $canal->id)->where('email', 'peter@gmail.test')->exists());
    }

    #[Test]
    public function manually_entered_contact_is_not_replaced_by_the_owner_email(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $maria = $this->user('maria@gmail.test');
        $this->approve($jozef);

        // Tím si zadá kontakt sám.
        app(\App\Services\Canals\CanalEmails::class)->sync($this->canal->fresh(), 'kancelaria@farnost.sk', []);
        app(CanalMembership::class)->attach($this->canal->fresh(), $maria, CanalRole::Owner);

        $this->assertSame('kancelaria@farnost.sk', $this->canal->fresh()->email);
        $this->assertTrue(CanalEmail::where('canal_id', $this->canal->id)->where('email', 'maria@gmail.test')->exists());
    }

    #[Test]
    public function reverted_takeover_removes_owner_addresses(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $claim = $this->approve($jozef);

        app(CanalClaims::class)->contest($claim->fresh(), 'Nepoznáme ho.');
        app(CanalClaims::class)->resolve($claim->fresh(), $this->admin, revert: true);

        $canal = $this->canal->fresh();
        $this->assertFalse($canal->isManaged());
        $this->assertSame('info@farnost.sk', $canal->email);
        $this->assertNull($canal->email_verified_at);
        $this->assertFalse(CanalEmail::where('canal_id', $canal->id)->where('source', CanalEmail::SOURCE_OWNER)->exists());
    }

    #[Test]
    public function reverted_takeover_of_a_canal_without_contact_leaves_it_without_email(): void
    {
        CanalEmail::where('canal_id', $this->canal->id)->delete();
        $this->canal->forceFill(['email' => null])->saveQuietly();

        $jozef = $this->user('jozef@gmail.test');
        $claim = $this->approve($jozef);
        $this->assertSame('jozef@gmail.test', $this->canal->fresh()->email);

        app(CanalClaims::class)->contest($claim->fresh());
        app(CanalClaims::class)->resolve($claim->fresh(), $this->admin, revert: true);

        $canal = $this->canal->fresh();
        $this->assertNull($canal->email);
        $this->assertNull($canal->email_verified_at);
        $this->assertSame(0, CanalEmail::where('canal_id', $canal->id)->count());
    }

    #[Test]
    public function personal_canal_of_a_verified_account_has_a_verified_address(): void
    {
        $anna = $this->user('anna@gmail.test');

        $canal = Canal::query()->findOrFail($anna->fresh()->canal_id);
        $this->assertSame('anna@gmail.test', $canal->email);
        $this->assertNotNull($canal->email_verified_at);
        $this->assertSame(CanalEmailStatus::Verified, CanalEmail::where('canal_id', $canal->id)->sole()->status);
    }

    #[Test]
    public function guest_account_canal_address_is_verified_once_the_account_is(): void
    {
        $guest = app(GuestAccountProvisioner::class)->ensure('host@gmail.test', 'Hosť', 'ticket');

        $canal = Canal::query()->findOrFail($guest->fresh()->canal_id);
        $this->assertSame('host@gmail.test', $canal->email);
        $this->assertNull($canal->email_verified_at);

        $guest->fresh()->forceFill(['email_verified_at' => now()])->save();

        $this->assertNotNull($canal->fresh()->email_verified_at);
        $this->assertSame(1, CanalEmail::where('canal_id', $canal->id)->count());
    }

    #[Test]
    public function deleted_last_owner_returns_the_claimed_canal_to_nobody(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $this->approve($jozef);

        $jozef->fresh()->delete();

        $canal = $this->canal->fresh();
        $this->assertFalse($canal->isManaged());
        $this->assertSame('info@farnost.sk', $canal->email);
        $this->assertFalse(CanalEmail::where('canal_id', $canal->id)->where('email', 'jozef@gmail.test')->exists());
        $this->assertFalse($canal->users()->withTrashed()->where('users.id', $jozef->id)->exists());
        $this->assertTrue($canal->owners()->where('users.id', $this->admin->id)->exists());
        $this->assertTrue(SystemLog::where('event', 'canals.owner_deleted')->where('status', 'released')->exists());

        // Obnovený účet kanál späť nedostane — o tom rozhoduje admin.
        User::withTrashed()->findOrFail($jozef->id)->restore();
        $this->assertFalse($this->canal->fresh()->isManaged());
        $this->assertFalse(CanalEmail::where('canal_id', $canal->id)->where('email', 'jozef@gmail.test')->exists());
    }

    #[Test]
    public function deleted_owner_with_a_co_owner_leaves_the_canal_managed(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $maria = $this->user('maria@gmail.test');
        $this->approve($jozef);
        app(CanalMembership::class)->attach($this->canal->fresh(), $maria, CanalRole::Owner);

        $maria->fresh()->delete();

        $canal = $this->canal->fresh();
        $this->assertTrue($canal->isManaged());
        $this->assertSame('jozef@gmail.test', $canal->email);
        $this->assertFalse(CanalEmail::where('canal_id', $canal->id)->where('email', 'maria@gmail.test')->exists());

        // Obnova vráti jej adresu ako ďalšiu.
        User::withTrashed()->findOrFail($maria->id)->restore();
        $this->assertTrue(CanalEmail::where('canal_id', $canal->id)->where('email', 'maria@gmail.test')->exists());
    }

    #[Test]
    public function deleted_account_takes_its_address_from_the_personal_canal(): void
    {
        $anna = $this->user('anna@gmail.test');
        $canalId = $anna->fresh()->canal_id;

        $anna->fresh()->delete();

        $this->assertNull(Canal::query()->findOrFail($canalId)->email);
        $this->assertSame(0, CanalEmail::where('canal_id', $canalId)->count());
    }

    #[Test]
    public function changed_account_email_replaces_the_owner_address(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $this->approve($jozef);

        $jozef->fresh()->forceFill(['email' => 'jozef.novy@gmail.test'])->save();

        $canal = $this->canal->fresh();
        $this->assertSame('jozef.novy@gmail.test', $canal->email);
        $this->assertEqualsCanonicalizing(
            ['info@farnost.sk', 'jozef.novy@gmail.test'],
            CanalEmail::where('canal_id', $canal->id)->pluck('email')->all(),
        );

        // Osobný kanál ide s adresou účtu tiež.
        $personal = Canal::query()->findOrFail($jozef->fresh()->canal_id);
        $this->assertSame('jozef.novy@gmail.test', $personal->email);
    }

    #[Test]
    public function blocked_account_keeps_its_canal_addresses(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $this->approve($jozef);

        $jozef->fresh()->forceFill(['blocked_at' => now()])->save();

        $canal = $this->canal->fresh();
        $this->assertTrue($canal->isManaged());
        $this->assertSame('jozef@gmail.test', $canal->email);
    }

    private function approve(User $user): CanalClaim
    {
        $claim = app(CanalClaims::class)->request($this->canal->fresh(), $user, CanalClaimMethod::AdminReview);
        app(CanalClaims::class)->approve($claim, $this->admin);

        return $claim->fresh();
    }

    private function user(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'last_activity' => now(),
        ]);
    }
}
