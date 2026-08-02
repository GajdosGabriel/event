<?php

namespace Tests\Unit\Venues;

use App\Models\Venue;
use App\Repositories\Contracts\VenueRepository;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class VenueUpdateTest extends EventSetupTest
{
    protected VenueRepository $venueRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venueRepository = app(VenueRepository::class);
    }

    #[Test]
    public function repository_can_update_soft_deleted_venue(): void
    {
        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create();
        $venue->delete();

        $payload = [
            'canal_id' => $this->canalPrimary->id,
            'name' => 'Updated Venue '.Str::random(5),
            'street' => 'Updated Street 1',
            'postcode' => '81101',
            // Popis prechádza pri zápise cez HtmlBodyCleaner (SanitizesHtmlBody),
            // takže sa posiela rovno ako HTML — inak by ho čistenie obalilo do
            // <p> a uložená hodnota by sa s odoslanou nezhodovala.
            'body' => '<p>Updated venue body '.Str::random(20).'</p>',
        ];

        /** @var Venue $updatedVenue */
        $updatedVenue = $this->venueRepository->update($venue->id, $payload);

        $this->assertSame($venue->id, $updatedVenue->id);
        $this->assertSame($payload['name'], $updatedVenue->name);
        $this->assertSame($payload['body'], $updatedVenue->body);
        $this->assertSoftDeleted('venues', [
            'id' => $venue->id,
        ]);
    }
}
