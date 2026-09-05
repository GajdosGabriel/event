<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Jobs\GenerateFileVariantsJob;
use App\Models\Event;
use App\Models\File;
use App\Models\User;
use App\Services\Files\RemoteAttachmentPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ten istý plagát pri viacerých podujatiach.
 *
 * Import ťahá obrázok pre každé podujatie zvlášť — v produkcii skončil jeden
 * PNG stiahnutý jedenásťkrát a jedenásťkrát prehnaný generovaním variantov.
 * Keď už rovnaký obsah niekde máme, objekty sa prekopírujú aj s hotovými
 * variantmi.
 *
 * Čo sa tým NErieši a riešiť nemá: úspora miesta. Bajty sa kopírujú a cesta
 * sa nezdieľa zámerne — dva riadky nad jednou cestou by znamenali, že
 * zmazanie prílohy v jednom podujatí rozbije náhľad v druhom (viď
 * FileDuplicator).
 */
class RemoteAttachmentReuseTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://zdroj.test/plagat.png';

    private const BODY = 'binárny-obsah-plagátu';

    /** EventFactory nechá `user_id` prázdne, a ten stĺpec je NOT NULL. */
    private function event(): Event
    {
        return Event::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function persist(Event $event): ?File
    {
        return app(RemoteAttachmentPersister::class)->store(
            $event,
            new Collection([['url' => self::URL]]),
            FileType::IMAGE,
            'public',
            'event/'.$event->id.'/image',
            true,
            [],
        )->first();
    }

    private function fakeDownload(): void
    {
        Http::fake([
            self::URL => Http::response(self::BODY, 200, ['Content-Type' => 'image/png']),
        ]);
    }

    #[Test]
    public function the_same_image_for_another_event_is_copied_instead_of_stored_again(): void
    {
        Storage::fake('public');
        Bus::fake();
        $this->fakeDownload();

        $first = $this->persist($this->event());
        $second = $this->persist($this->event());

        $this->assertNotNull($first);
        $this->assertNotNull($second);

        // Druhý riadok vznikol kópiou prvého.
        $this->assertSame($first->id, $second->meta['reused_from'] ?? null);
        $this->assertSame($first->checksum, $second->checksum);

        // Varianty sa druhýkrát negenerujú — sú prekopírované.
        Bus::assertDispatchedTimes(GenerateFileVariantsJob::class, 1);
    }

    /** Zdieľaná cesta by z mazania v jednom podujatí spravila výpadok v druhom. */
    #[Test]
    public function the_copy_never_shares_a_path_with_the_original(): void
    {
        Storage::fake('public');
        Bus::fake();
        $this->fakeDownload();

        $first = $this->persist($this->event());
        $second = $this->persist($this->event());

        $this->assertNotSame($first->path, $second->path);
        $this->assertTrue(Storage::disk('public')->exists($first->path));
        $this->assertTrue(Storage::disk('public')->exists($second->path));
    }

    /** Iný obsah na tom istom odkaze sa musí uložiť nanovo. */
    #[Test]
    public function different_content_is_never_reused(): void
    {
        Storage::fake('public');
        Bus::fake();

        // Postupnosť, nie dva Http::fake() za sebou — druhé volanie pôvodnú
        // podvrhnutú odpoveď neprepíše a obe stiahnutia by vrátili to isté.
        Http::fake([
            self::URL => Http::sequence()
                ->push('prvý obsah', 200, ['Content-Type' => 'image/png'])
                ->push('úplne iný obsah', 200, ['Content-Type' => 'image/png']),
        ]);

        $first = $this->persist($this->event());
        $second = $this->persist($this->event());

        $this->assertNotSame($first->checksum, $second->checksum);
        $this->assertArrayNotHasKey('reused_from', $second->meta ?? []);
        Bus::assertDispatchedTimes(GenerateFileVariantsJob::class, 2);
    }

    /** Ten istý obrázok dvakrát na tom istom podujatí ostáva jedným riadkom. */
    #[Test]
    public function the_same_image_on_the_same_event_stays_one_row(): void
    {
        Storage::fake('public');
        Bus::fake();
        $this->fakeDownload();

        $event = $this->event();

        $first = $this->persist($event);
        $second = $this->persist($event);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, File::query()->where('fileable_id', $event->id)->count());
    }
}
