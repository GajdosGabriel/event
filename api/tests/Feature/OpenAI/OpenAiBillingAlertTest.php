<?php

namespace Tests\Feature\OpenAI;

use App\Models\User;
use App\Notifications\OpenAiBillingIssue;
use App\Services\OpenAI\ChatGPT;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Došlý kredit v OpenAI sa nesmie stratiť v logu importu — super-admin musí
 * dostať e-mail, ale nie stovky e-mailov z jedného importu.
 */
class OpenAiBillingAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('openai.api_key', 'test-key');
        Cache::flush();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fakeOpenAi(int $status, array $body): void
    {
        Http::fake(['api.openai.com/*' => Http::response($body, $status)]);
    }

    private function callOpenAi(): void
    {
        try {
            (new ChatGPT)->extractCanalName('Farnosť Nitra');
            $this->fail('ChatGPT should have thrown on OpenAI error.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('OpenAI API error', $e->getMessage());
        }
    }

    private function noCreditsBody(): array
    {
        return ['error' => [
            'message' => 'You have no credits remaining. Add credits to continue using the API.',
            'type' => 'insufficient_quota',
            'param' => null,
            'code' => 'credit_balance_exhausted',
        ]];
    }

    #[Test]
    public function super_admin_is_notified_once_when_credits_run_out(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $regular = User::factory()->create();

        $this->fakeOpenAi(429, $this->noCreditsBody());

        $this->callOpenAi();
        $this->callOpenAi();

        Notification::assertSentToTimes($admin, OpenAiBillingIssue::class, 1);
        Notification::assertNotSentTo($regular, OpenAiBillingIssue::class);
    }

    #[Test]
    public function alert_is_sent_again_after_cooldown(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->fakeOpenAi(429, $this->noCreditsBody());

        $this->callOpenAi();
        $this->travel(7)->hours();
        $this->callOpenAi();

        Notification::assertSentToTimes($admin, OpenAiBillingIssue::class, 2);
    }

    #[Test]
    public function other_openai_errors_do_not_notify(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->fakeOpenAi(429, ['error' => [
            'message' => 'Rate limit reached.',
            'type' => 'requests',
            'code' => 'rate_limit_exceeded',
        ]]);

        $this->callOpenAi();

        Notification::assertNothingSent();
    }

    #[Test]
    public function mail_contains_openai_message_and_billing_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $mail = (new OpenAiBillingIssue(429, 'credit_balance_exhausted', 'You have no credits remaining.', 6))
            ->toMail($admin);

        $this->assertSame(__('mail.openai_billing.subject'), $mail->subject);
        $this->assertStringContainsString('platform.openai.com/settings/organization/billing', $mail->actionUrl);
        $this->assertContains('**You have no credits remaining.**', $mail->introLines);
    }
}
