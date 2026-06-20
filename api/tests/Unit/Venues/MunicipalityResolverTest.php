<?php

namespace Tests\Unit\Venues;

use App\Services\Geocoding\MunicipalityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MunicipalityResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function resolve_matches_municipality_by_city_name_without_accents(): void
    {
        $cityName = 'Čerešňové';

        $municipalityId = DB::table('municipalities')->insertGetId([
            'fullname' => $cityName,
            'shortname' => $cityName,
            'zip' => '81101',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = (new MunicipalityResolver())->resolve('Ceresnove');

        $this->assertSame([
            'village_id' => $municipalityId,
            'matched_municipality' => [
                'id' => $municipalityId,
                'fullname' => $cityName,
                'shortname' => $cityName,
                'zip' => '81101',
            ],
            'municipality_match' => [
                'confidence' => 'medium',
                'match_source' => 'city',
            ],
        ], $result);
    }

    #[Test]
    public function resolve_uses_postcode_to_disambiguate_city_match(): void
    {
        DB::table('municipalities')->insert([
            'fullname' => 'Testov',
            'shortname' => 'Testov',
            'zip' => '90001',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expectedId = DB::table('municipalities')->insertGetId([
            'fullname' => 'Testov',
            'shortname' => 'Testov',
            'zip' => '81101',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = (new MunicipalityResolver())->resolve('Testov', '811 01');

        $this->assertSame([
            'village_id' => $expectedId,
            'matched_municipality' => [
                'id' => $expectedId,
                'fullname' => 'Testov',
                'shortname' => 'Testov',
                'zip' => '81101',
            ],
            'municipality_match' => [
                'confidence' => 'high',
                'match_source' => 'city_and_postcode',
            ],
        ], $result);
    }

    #[Test]
    public function resolve_returns_null_when_no_municipality_matches(): void
    {
        $result = (new MunicipalityResolver())->resolve('Neexistujuce mesto');

        $this->assertSame([
            'village_id' => null,
            'matched_municipality' => null,
            'municipality_match' => [
                'confidence' => 'none',
                'match_source' => null,
            ],
        ], $result);
    }

    #[Test]
    public function resolve_uses_cache_for_identical_city_and_postcode(): void
    {
        Cache::flush();

        $municipalityId = DB::table('municipalities')->insertGetId([
            'fullname' => 'Cachetown',
            'shortname' => 'Cachetown',
            'zip' => '11111',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = new MunicipalityResolver();

        $first = $resolver->resolve('Cachetown', '11111');

        DB::table('municipalities')->where('id', $municipalityId)->delete();

        $second = $resolver->resolve('Cachetown', '11111');

        $this->assertSame($first, $second);
        $this->assertSame($municipalityId, $second['village_id']);
    }
}
