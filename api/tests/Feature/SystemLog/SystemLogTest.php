<?php

namespace Tests\Feature\SystemLog;

use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use App\Services\SystemLog\Recorder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Denník udalostí: zápis z udalostí Laravelu, mazanie starých záznamov
 * a admin výpis /api/admin/system-logs.
 */
class SystemLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Niektoré testy zapisujú mimo transakcie RefreshDatabase — ich
        // registrácie (auth.registered) by inak prekážali v počtoch.
        SystemLog::query()->delete();
    }

    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        return $admin;
    }

    #[Test]
    public function sent_mail_is_recorded_with_recipient_and_subject(): void
    {
        Notification::route('mail', 'prijemca@example.com')
            ->notifyNow(new PasswordResetLink('token-123', 'prijemca@example.com'));

        $log = SystemLog::where('event', 'mail.sent')->sole();

        $this->assertSame('sent', $log->status);
        $this->assertSame('prijemca@example.com', $log->recipient);
        $this->assertNotSame('', $log->message);
        $this->assertSame(PasswordResetLink::class, $log->context['class']);
    }

    #[Test]
    public function login_and_failed_login_are_recorded_without_password(): void
    {
        $user = User::factory()->create(['email' => 'ja@example.com', 'password' => Hash::make('spravne-heslo')]);

        $this->postJson('/api/login', ['email' => 'ja@example.com', 'password' => 'zle-heslo'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'ja@example.com', 'password' => 'spravne-heslo'])->assertOk();

        $failed = SystemLog::where('event', 'auth.failed')->sole();
        $this->assertSame('ja@example.com', $failed->recipient);
        $this->assertSame($user->id, $failed->user_id);
        $this->assertStringNotContainsString('zle-heslo', json_encode($failed->getAttributes()));

        $this->assertSame($user->id, SystemLog::where('event', 'auth.login')->sole()->user_id);
    }

    #[Test]
    public function prune_keeps_errors_longer_than_info(): void
    {
        Recorder::info('test', 'old', 'starý info');
        Recorder::error('test', 'old', 'stará chyba');
        Recorder::info('test', 'fresh', 'čerstvý info');
        SystemLog::where('event', 'test.old')->update(['created_at' => now()->subDays(40)]);

        Artisan::call('model:prune', ['--model' => SystemLog::class]);

        $this->assertEqualsCanonicalizing(
            ['error:test.old', 'info:test.fresh'],
            SystemLog::all()->map(fn ($log) => $log->level.':'.$log->event)->all(),
        );
    }

    #[Test]
    public function admin_sees_filtered_log_with_summary(): void
    {
        $admin = $this->superAdmin();

        Recorder::info('mail', 'sent', 'Pozvánka', status: 'sent', recipient: 'pozvany@priklad.test');
        Recorder::error('mail', 'failed', 'Lístok', status: 'failed', recipient: 'b@priklad.test');
        Recorder::warning('import', 'finished', 'tkkbs.sk — chyby 2');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/system-logs?channel=mail')
            ->assertOk();

        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.event', 'mail.failed');
        $response->assertJsonPath('data.0.recipient', 'b@priklad.test');
        $response->assertJsonPath('summary.sentDay', 1);
        $response->assertJsonPath('summary.failedDay', 1);
        $response->assertJsonPath('summary.errorsDay', 1);
        $this->assertContains('import', $response->json('channels'));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/system-logs?search=pozvany%40')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.message', 'Pozvánka');
    }

    #[Test]
    public function user_filter_matches_his_id_and_his_email(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create(['email' => 'clovek@example.com']);

        Recorder::info('mail', 'sent', 'Mail bez user_id', status: 'sent', recipient: 'clovek@example.com');
        Recorder::info('auth', 'login', 'Prihlásenie', userId: $user->id);
        Recorder::info('auth', 'login', 'Niekto iný', userId: $admin->id);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/system-logs?user_id='.$user->id)
            ->assertOk()
            // registrácia (observer) + mail podľa adresy + prihlásenie podľa user_id
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function invalid_filter_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/admin/system-logs?level=fatal&date_from=včera')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level', 'date_from']);
    }

    #[Test]
    public function guest_and_regular_user_cannot_see_log(): void
    {
        $this->getJson('/api/admin/system-logs')->assertUnauthorized();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/system-logs')
            ->assertForbidden();
    }
}
