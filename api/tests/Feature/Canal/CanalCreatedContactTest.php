<?php

namespace Tests\Feature\Canal;

use App\Models\Canal;
use App\Models\User;
use App\Notifications\CanalContactNotice;
use App\Notifications\CanalEventPublishedOffer;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

/**
 * Kanál, ktorý si založí používateľ sám: ponuku prevzatia nedostáva, ale jeho
 * kontaktný e-mail treba potvrdiť (CanalContactVerifier::created) — okrem
 * overeného e-mailu zakladateľa.
 */
class CanalCreatedContactTest extends CanalSetupTest
{
    private function creator(?string $email = null, bool $verified = true): User
    {
        /** @var User $creator */
        $creator = User::factory()->createOne([
            ...($email !== null ? ['email' => $email] : []),
            'email_verified_at' => $verified ? now() : null,
        ]);
        $creator->assignRole('canal-editor');
        $creator->givePermissionTo('canal.update');
        $this->actingAs($creator, 'sanctum');

        return $creator;
    }

    private function store(array $attributes): Canal
    {
        Notification::fake();

        $response = $this->postJson('/api/dashboard/canals', [...$this->formCanal, ...$attributes]);
        $response->assertStatus(201);

        return Canal::query()->findOrFail($response->json('data.id') ?? $response->json('id'));
    }

    /** @return array<int, CanalContactNotice> */
    private function verificationsTo(string $email): array
    {
        $sent = Notification::sentNotifications()[AnonymousNotifiable::class][''][CanalContactNotice::class] ?? [];

        return array_values(array_map(fn (array $m) => $m['notification'], array_filter($sent,
            fn (array $m) => $m['notifiable']->routes['mail'] === $email && $m['notification']->kind === CanalContactNotice::VERIFY)));
    }

    #[Test]
    public function new_canal_email_gets_a_verification_link_not_a_takeover_offer(): void
    {
        $this->creator('zakladatel@osoba.test');

        $canal = $this->store(['email' => 'info@spolok.test']);

        $this->assertCount(1, $this->verificationsTo('info@spolok.test'));
        $this->assertStringContainsString('/contact/verify', (string) $this->verificationsTo('info@spolok.test')[0]->verifyUrl);
        $this->assertNull($canal->fresh()->email_verified_at);
        Notification::assertNotSentTo(new AnonymousNotifiable, CanalEventPublishedOffer::class);
    }

    #[Test]
    public function creators_own_verified_email_is_taken_as_verified(): void
    {
        $this->creator('ja@osoba.test');

        $canal = $this->store(['email' => 'JA@osoba.test']);

        $this->assertSame([], $this->verificationsTo('ja@osoba.test'));
        $this->assertNotNull($canal->fresh()->email_verified_at);
    }

    #[Test]
    public function creators_unverified_email_still_needs_the_link(): void
    {
        $this->creator('ja@osoba.test', verified: false);

        $canal = $this->store(['email' => 'ja@osoba.test']);

        $this->assertCount(1, $this->verificationsTo('ja@osoba.test'));
        $this->assertNull($canal->fresh()->email_verified_at);
    }

    #[Test]
    public function canal_without_email_sends_nothing(): void
    {
        $this->creator();

        $this->store(['email' => null]);

        Notification::assertNothingSent();
    }
}
