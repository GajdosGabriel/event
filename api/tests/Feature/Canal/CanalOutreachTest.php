<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\CanalOutreach;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\View;
use App\Services\Canals\CanalMembership;
use App\Services\Canals\CanalOutreachSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Oslovenie organizátora (app:canal-outreach) — výber kandidátov, vypnutý
 * emailing a odhlásenie. Doručenie cez Account: CanalOutreachMailingTest.
 */
class CanalOutreachTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->admin = User::factory()->create(['last_login_at' => now(), 'last_activity' => now()]);
        $this->admin->assignRole('super-admin');
    }

    #[Test]
    public function only_eligible_canals_are_candidates(): void
    {
        $ok = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $this->endedEvent($ok, daysAgo: 3, views: 2);

        $claimed = $this->imported('Prevzatá', 'info@prevzata.sk');
        $claimed->forceFill(['claimed_at' => now()])->save();
        $this->endedEvent($claimed, 3, 5);

        $collection = $this->imported('vyveska.sk', 'info@vyveska.sk');
        $this->endedEvent($collection, 3, 5);

        $suppressed = $this->imported('Odhlásená', 'info@odhlasena.sk');
        $this->endedEvent($suppressed, 3, 5);
        EmailSuppression::add('INFO@odhlasena.sk', 'unsubscribe');

        $old = $this->imported('Stará akcia', 'info@stara.sk');
        $this->endedEvent($old, 30, 5);

        $unseen = $this->imported('Nikto nevidel', 'info@nikto.sk');
        $this->endedEvent($unseen, 3, 0);

        $noContact = $this->imported('Bez kontaktu', null);
        $this->endedEvent($noContact, 3, 5);

        $candidates = app(CanalOutreachSender::class)->candidates();

        $this->assertSame([$ok->id], $candidates->pluck('canal.id')->all());
        $this->assertSame(['event_views' => 2, 'event_visitors' => 2, 'canal_views' => 0, 'signups' => 0], $candidates[0]['stats']);
    }

    #[Test]
    public function without_account_mailing_nobody_is_contacted(): void
    {
        config(['account.mailing' => false]);

        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $this->endedEvent($canal, 2, 3);

        $this->artisan('app:canal-outreach')->assertSuccessful();

        $this->assertSame(0, CanalOutreach::count());
        $this->assertSame(0, CanalInvitation::count());
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function unsubscribe_link_suppresses_the_address_everywhere(): void
    {
        $url = URL::signedRoute('public.email.unsubscribe', ['email' => 'info@hajske.sk']);

        $this->get(str_replace('info%40', 'iny%40', $url))->assertForbidden();
        $this->get($url)->assertRedirectContains('/odhlasenie');
        $this->post($url)->assertNoContent();

        $this->assertTrue(EmailSuppression::has('info@hajske.sk'));
        $this->assertSame(1, SystemLog::where('event', 'mail.unsubscribed')->count());

        // Ani prihláška na akciu už kontaktu neponúkne prevzatie.
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $event = Event::factory()->future()->create([
            'canal_id' => $canal->id, 'status' => ModelStatus::Published->value, 'published_at' => now()->subDay(),
            'registration_deadline_at' => null, 'price_amount' => null, 'email' => 'info@hajske.sk',
        ]);
        $this->actingAs(User::factory()->create(['last_login_at' => now(), 'last_activity' => now()]), 'sanctum')
            ->postJson("/api/events/{$event->id}/signup")->assertOk();
        $this->assertSame(0, CanalInvitation::count());
    }

    private function imported(string $name, ?string $email): Canal
    {
        $canal = Canal::factory()->create([
            'name' => $name,
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => $email,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
        ]);
        app(CanalMembership::class)->attach($canal, $this->admin, CanalRole::Owner);

        return $canal;
    }

    private function endedEvent(Canal $canal, int $daysAgo, int $views): Event
    {
        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'status' => ModelStatus::Archived->value,
            'start_at' => now()->subDays($daysAgo)->subHours(3),
            'end_at' => now()->subDays($daysAgo),
            'email' => null,
        ]);

        for ($i = 0; $i < $views; $i++) {
            View::query()->create([
                'viewable_type' => $event->getMorphClass(),
                'viewable_id' => $event->id,
                'visitor_hash' => hash('sha256', 'v'.$i),
                'viewed_on' => now()->subDays($daysAgo + 1)->toDateString(),
            ]);
        }

        return $event;
    }
}
