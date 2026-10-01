<?php

namespace Tests\Unit\Venues;

use App\Models\Municipality;
use App\Models\Venue;
use App\Services\OpenAI\Detector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * „Námestie SNP“ je v každom druhom meste. Napojenie na existujúce miesto
 * preto musí overiť aj obec, nielen názov.
 */
class DetectorExistingVenueCityTest extends TestCase
{
    use RefreshDatabase;

    private function lookup(string $name, ?string $city): ?array
    {
        $method = new \ReflectionMethod(Detector::class, 'lookupVenueByName');

        return $method->invoke(new Detector, $name, $city);
    }

    #[Test]
    public function a_venue_with_the_same_street_in_another_town_is_not_matched(): void
    {
        $bratislava = Municipality::query()->where('fullname', 'Bratislava')->first() ?? Municipality::query()->first();
        Venue::factory()->create([
            'name' => 'Busseto, Italy, Námestie SNP, Bratislava',
            'village_id' => $bratislava->id,
        ]);

        $this->assertNull($this->lookup('Námestie SNP', 'Zzz neexistujúce mesto'));
        $this->assertNotNull($this->lookup('Námestie SNP', $bratislava->fullname));
    }
}
