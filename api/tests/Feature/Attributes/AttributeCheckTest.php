<?php

namespace Tests\Feature\Attributes;

use App\Contracts\AttributeProbe;
use App\Enums\AttributeCheckStatus;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\AttributeCheck;
use App\Models\Canal;
use App\Models\User;
use App\Notifications\AttributeIssueNotice;
use App\Services\Attributes\AttributeCheckService;
use App\Services\Attributes\ProbeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Overovanie uložených hodnôt na pozadí (App\Services\Attributes).
 *
 * Kanál tu zastupuje všetky typy — kód je pre miesto, podujatie aj firmu ten
 * istý (HasCheckedAttributes + AttributeCheckService), líši sa len model.
 *
 * Sonda je podvrhnutá: skutočná chodí na cudzie servery, takže by z testu
 * urobila meranie internetu. Vymeniť sa dá cez config, čo je zároveň spôsob,
 * akým sa v budúcnosti pridá overovanie ďalších údajov.
 */
class AttributeCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
    }

    /** Kanál s webom a vlastníkom, ktorému môže prísť upozornenie. */
    private function canal(string $website = 'https://divadlo.sk'): Canal
    {
        $canal = Canal::factory()->create([
            'status' => ModelStatus::Published->value,
            'municipality_id' => 1,
            'registration_source' => RegistrationSource::SELF,
            'website' => $website,
        ]);

        $canal->users()->attach($this->owner->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
        ]);

        return $canal->fresh();
    }

    /** Nahradí sondu pre `website` takou, ktorá vždy vráti daný výsledok. */
    private function fakeProbe(ProbeResult $result): void
    {
        $probe = new class($result) implements AttributeProbe
        {
            public function __construct(private ProbeResult $result)
            {
            }

            public function attribute(): string
            {
                return AttributeCheck::WEBSITE;
            }

            public function probe(string $value): ProbeResult
            {
                return $this->result;
            }
        };

        app()->instance($probe::class, $probe);
        config(['attribute_checks.probes' => [$probe::class]]);
    }

    private function service(): AttributeCheckService
    {
        return app(AttributeCheckService::class);
    }

    #[Test]
    public function saving_a_website_registers_it_for_checking(): void
    {
        $canal = $this->canal();

        $this->assertDatabaseHas('attribute_checks', [
            'checkable_type' => Canal::class,
            'checkable_id' => $canal->id,
            'attribute' => 'website',
            'value' => 'https://divadlo.sk',
            'status' => AttributeCheckStatus::Pending->value,
        ]);
    }

    #[Test]
    public function clearing_the_website_removes_the_record(): void
    {
        $canal = $this->canal();

        $canal->update(['website' => null]);

        $this->assertDatabaseCount('attribute_checks', 0);
    }

    #[Test]
    public function changing_the_website_resets_the_result(): void
    {
        $this->fakeProbe(ProbeResult::failed('dns'));
        $canal = $this->canal();

        $this->service()->run($canal->attributeChecks()->first());

        $canal->update(['website' => 'https://nove-divadlo.sk']);

        $check = $canal->fresh()->attributeChecks()->first();

        // Starý výsledok patril inej adrese. Nechať ho by znamenalo tvrdiť
        // o práve zadanej adrese niečo, čo o nej nikto nezisťoval.
        $this->assertSame(AttributeCheckStatus::Pending, $check->status);
        $this->assertSame(0, $check->failures);
        $this->assertNull($check->reason);
        $this->assertSame('https://nove-divadlo.sk', $check->value);
    }

    #[Test]
    public function a_working_website_is_marked_ok(): void
    {
        Notification::fake();
        $this->fakeProbe(ProbeResult::ok(200));
        $canal = $this->canal();

        $this->service()->run($canal->attributeChecks()->first());

        $check = $canal->fresh()->attributeChecks()->first();

        $this->assertSame(AttributeCheckStatus::Ok, $check->status);
        $this->assertSame(0, $check->failures);
        $this->assertTrue($check->next_check_at->isFuture());

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_single_failure_does_not_bother_the_owner(): void
    {
        Notification::fake();
        $this->fakeProbe(ProbeResult::failed('timeout'));
        $canal = $this->canal();

        $this->service()->run($canal->attributeChecks()->first());

        $check = $canal->fresh()->attributeChecks()->first();

        // Výpadok hostingu na pár minút nie je pokazený web.
        $this->assertSame(AttributeCheckStatus::Failed, $check->status);
        $this->assertSame(1, $check->failures);
        $this->assertNull($check->notified_at);

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_repeated_failure_notifies_the_owner_once(): void
    {
        Notification::fake();
        $this->fakeProbe(ProbeResult::failed('not_found', 404));
        $canal = $this->canal();

        // Tri kolá overenia; upozornenie má odísť práve raz — pri druhom,
        // keď sa neúspech potvrdil, a nie znova pri každom ďalšom.
        for ($round = 0; $round < 3; $round++) {
            $check = $canal->fresh()->attributeChecks()->first();
            $check->forceFill(['next_check_at' => now()->subDay()])->save();

            $this->service()->run($check);
        }

        $check = $canal->fresh()->attributeChecks()->first();

        $this->assertSame(3, $check->failures);
        $this->assertNotNull($check->notified_at);

        Notification::assertSentToTimes($this->owner, AttributeIssueNotice::class, 1);
    }

    #[Test]
    public function a_record_without_an_owner_is_checked_but_nobody_is_notified(): void
    {
        Notification::fake();
        $this->fakeProbe(ProbeResult::failed('dns'));

        // Importovaný kanál nemá komu patriť — adresu si vytiahol robot
        // z cudzej stránky a písať niekomu o „jeho" webe by bol nezmysel.
        $canal = Canal::factory()->create([
            'status' => ModelStatus::Published->value,
            'municipality_id' => 1,
            'registration_source' => RegistrationSource::IMPORT,
            'website' => 'https://cudzi-web.sk',
        ]);

        for ($round = 0; $round < 2; $round++) {
            $check = $canal->fresh()->attributeChecks()->first();
            $check->forceFill(['next_check_at' => now()->subDay()])->save();

            $this->service()->run($check);
        }

        $this->assertSame(AttributeCheckStatus::Failed, $canal->fresh()->attributeChecks()->first()->status);

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_probe_that_cannot_decide_leaves_the_state_alone(): void
    {
        Notification::fake();
        $this->fakeProbe(ProbeResult::skipped('idn_unsupported'));
        $canal = $this->canal();

        $this->service()->run($canal->attributeChecks()->first());

        $check = $canal->fresh()->attributeChecks()->first();

        // „Nevieme overiť" nie je „nefunguje" — obviniť adresu z vlastnej
        // nemohúcnosti by znamenalo poslať majiteľovi nepravdivý e-mail.
        $this->assertSame(AttributeCheckStatus::Pending, $check->status);
        $this->assertSame(0, $check->failures);
        $this->assertTrue($check->next_check_at->isFuture());

        Notification::assertNothingSent();
    }

    #[Test]
    public function only_values_that_are_due_get_checked(): void
    {
        $this->fakeProbe(ProbeResult::ok(200));
        $canal = $this->canal();

        $canal->attributeChecks()->first()->forceFill(['next_check_at' => now()->addDay()])->save();

        $this->assertCount(0, $this->service()->due(10));
    }

    #[Test]
    public function a_reported_click_jumps_the_queue(): void
    {
        $this->fakeProbe(ProbeResult::ok(200));
        $canal = $this->canal();

        $canal->attributeChecks()->first()->forceFill(['next_check_at' => now()->addMonth()])->save();

        $this->assertTrue($this->service()->report($canal->fresh(), 'website', '/organizatori/divadlo-1'));

        $check = $canal->fresh()->attributeChecks()->first();

        $this->assertSame('/organizatori/divadlo-1', $check->reported_from);
        $this->assertTrue($check->next_check_at->isPast() || $check->next_check_at->isCurrentMinute());
        $this->assertCount(1, $this->service()->due(10));
    }

    #[Test]
    public function repeated_reports_of_the_same_link_are_ignored(): void
    {
        $this->fakeProbe(ProbeResult::ok(200));
        $canal = $this->canal();

        $this->assertTrue($this->service()->report($canal, 'website', '/organizatori/divadlo-1'));
        // Bez odstupu by sa jedným odkazom dal vyvolať ľubovoľný počet sond.
        $this->assertFalse($this->service()->report($canal->fresh(), 'website', '/organizatori/divadlo-1'));
    }

    #[Test]
    public function force_deleting_a_record_cleans_up_after_it(): void
    {
        $canal = $this->canal();

        $canal->forceDelete();

        $this->assertDatabaseCount('attribute_checks', 0);
    }
}
