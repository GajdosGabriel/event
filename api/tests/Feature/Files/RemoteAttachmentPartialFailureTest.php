<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Models\Event;
use App\Models\User;
use App\Services\Files\FileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prílohy sa sťahujú pred DB transakciou a jedna zlyhaná nezhodí ostatné.
 */
class RemoteAttachmentPartialFailureTest extends TestCase
{
    use RefreshDatabase;

    /** EventFactory nechá `user_id` prázdne, a ten stĺpec je NOT NULL. */
    private function event(): Event
    {
        return Event::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    #[Test]
    public function a_failed_attachment_is_logged_and_the_others_are_stored(): void
    {
        Storage::fake('public');
        Bus::fake();
        Log::spy();

        Http::fake([
            'https://zdroj.test/prvy.pdf' => Http::response('prvý', 200, ['Content-Type' => 'application/pdf']),
            'https://zdroj.test/druhy.pdf' => Http::response('', 404),
            'https://zdroj.test/treti.pdf' => Http::response('tretí', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $event = $this->event();

        $stored = app(FileManager::class)->storeRemoteForEvent($event, [
            ['url' => 'https://zdroj.test/prvy.pdf', 'name' => 'prvy.pdf'],
            ['url' => 'https://zdroj.test/druhy.pdf', 'name' => 'druhy.pdf'],
            ['url' => 'https://zdroj.test/treti.pdf', 'name' => 'treti.pdf'],
        ], FileType::FILE, 'public');

        $this->assertCount(2, $stored);
        $this->assertSame(
            ['https://zdroj.test/prvy.pdf', 'https://zdroj.test/treti.pdf'],
            $event->files()->orderBy('id')->get()->map(fn ($file) => $file->meta['source_url'])->all(),
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $context['url'] === 'https://zdroj.test/druhy.pdf'
                && str_contains($context['error'], 'Failed to download'));
    }

    /** Keď nevyjde ani jedna, volajúci (retry pri 429, kontroléry) dostane výnimku. */
    #[Test]
    public function when_every_attachment_fails_the_exception_is_rethrown(): void
    {
        Storage::fake('public');
        Bus::fake();

        Http::fake(['https://zdroj.test/*' => Http::response('', 429)]);

        $event = $this->event();

        try {
            app(FileManager::class)->storeRemoteForEvent($event, [
                ['url' => 'https://zdroj.test/obrazok.png'],
            ], FileType::IMAGE, 'public');
            $this->fail('Očakávaná výnimka.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Failed to download', $e->getMessage());
        }

        $this->assertSame(0, $event->files()->count());
    }

    /** Keď zlyhá prvá, primárnou sa stane prvá úspešne stiahnutá. */
    #[Test]
    public function the_first_successful_image_becomes_primary(): void
    {
        Storage::fake('public');
        Bus::fake();

        Http::fake([
            'https://zdroj.test/a.png' => Http::response('', 404),
            'https://zdroj.test/b.png' => Http::response('obrázok b', 200, ['Content-Type' => 'image/png']),
        ]);

        $event = $this->event();

        $stored = app(FileManager::class)->storeRemoteForEvent($event, [
            ['url' => 'https://zdroj.test/a.png'],
            ['url' => 'https://zdroj.test/b.png'],
        ], FileType::IMAGE, 'public', null, true);

        $this->assertCount(1, $stored);
        $this->assertTrue((bool) $stored->first()->fresh()->is_primary);
    }
}
