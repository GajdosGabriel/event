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
            'name' => 'Updated Venue ' . Str::random(5),
            'street' => 'Updated Street 1',
            'postcode' => '81101',
            'body' => 'Updated venue body ' . Str::random(20),
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
