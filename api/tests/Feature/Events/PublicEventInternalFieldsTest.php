<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Vnútorná réžia podujatia nesmie odísť do verejnej odpovede.
 *
 * Dôvod je konkrétny: `meta` drží surový text importu (`raw_text`,
 * `imported_raw_body`) a celú odpoveď detektora vrátane
 * `ai_detector.event_payload.persons[]`, kde bývajú mená, telefóny a e-maily
 * kontaktných osôb. V UI sa nezobrazuje nikde, ale kým bol model bez $hidden,
 * chodila celá von neprihlásenému návštevníkovi — výpisom cez EventResource
 * (stavia na parent::toArray()) aj detailom, ktorý serializuje model priamo.
 * Preto sa tu strážia obe cesty.
 */
class PublicEventInternalFieldsTest extends EventSetupTest
{
    /** Stĺpce, ktoré model drží v $hidden a resource vracia až po kontrole práv. */
    private const INTERNAL = [
        'meta',
        'ai_tagged_at',
        'ai_tags_hash',
        'ai_tags_attempts',
        'body_rewritten_at',
        'views_count',
    ];

    private const RAW_TEXT = 'Surový zoškrabaný text z importu.';

    private const PERSON_PHONE = '+421 904 343 305';

    private const PERSON_EMAIL = 'kontakt@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Fixture necháva podujatie v stave draft, verejné endpointy ho tak
        // nevydajú a test by nič nestrážil.
        $this->futureEvent->forceFill([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'meta' => [
                'raw_text' => self::RAW_TEXT,
                'imported_raw_body' => '<p>Surové telo z importu.</p>',
                'ai_detector' => [
                    'event_payload' => [
                        'persons' => [
                            ['meno' => 'Kontaktná osoba', 'telefon' => self::PERSON_PHONE, 'email' => self::PERSON_EMAIL],
                        ],
                    ],
                ],
            ],
        ])->save();
    }

    /**
     * EventSetupTest prihlasuje majiteľa v setUp(), takže bez tohto by
     * „verejná" požiadavka chodila s jeho právami a dostala by aj skryté polia.
     */
    private function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    #[Test]
    public function public_list_hides_internal_fields(): void
    {
        $this->asGuest();

        $row = collect($this->getJson('/api/events')->assertOk()->json('data'))
            ->firstWhere('id', $this->futureEvent->id);

        $this->assertNotNull($row, 'Podujatie musí byť vo verejnom výpise, inak test nič nestráži.');

        foreach (self::INTERNAL as $field) {
            $this->assertArrayNotHasKey($field, $row);
        }
    }

    #[Test]
    public function public_detail_hides_internal_fields(): void
    {
        $this->asGuest();

        $data = $this->getJson('/api/events/' . $this->futureEvent->id)->assertOk()->json();

        foreach (self::INTERNAL as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    /**
     * Najtvrdšia poistka: kontakty z detektora sa nesmú objaviť v surovom tele
     * odpovede ani pod iným kľúčom, ani keby ich tam pridal nový append.
     */
    #[Test]
    public function public_responses_never_contain_detector_contacts(): void
    {
        foreach (['/api/events', '/api/events/' . $this->futureEvent->id] as $url) {
            $this->asGuest();

            $body = $this->getJson($url)->assertOk()->getContent();

            $this->assertStringNotContainsString(self::PERSON_PHONE, $body);
            $this->assertStringNotContainsString(self::PERSON_EMAIL, $body);
            $this->assertStringNotContainsString(self::RAW_TEXT, $body);
        }
    }

    #[Test]
    public function owner_still_receives_internal_fields(): void
    {
        $data = $this->getJson('/api/dashboard/events/' . $this->futureEvent->id)
            ->assertOk()
            ->json();

        foreach (self::INTERNAL as $field) {
            $this->assertArrayHasKey($field, $data);
        }

        $this->assertSame(self::RAW_TEXT, $data['meta']['raw_text'] ?? null);
    }
}
