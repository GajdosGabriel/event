<?php

namespace Tests\Unit\OpenAI;

use App\Models\Canal;
use App\Services\OpenAI\Detector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nápoveda kanála podľa názvu: presná zhoda má prednosť pred LIKE, krátky
 * názov LIKE nepúšťa a zástupné znaky v názve sa berú doslovne.
 */
class DetectorNameLookupTest extends TestCase
{
    use RefreshDatabase;

    private function lookupCanal(string $name): ?array
    {
        $method = new \ReflectionMethod(Detector::class, 'lookupCanalByName');

        return $method->invoke(new Detector, $name);
    }

    private function canal(string $name, string $createdAt): Canal
    {
        return Canal::factory()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'created_at' => $createdAt,
        ]);
    }

    #[Test]
    public function an_exact_name_wins_over_a_newer_longer_match(): void
    {
        $older = $this->canal('Farnosť Košice', '2026-01-01 00:00:00');
        $this->canal('Farnosť Košice-Sever', '2026-06-01 00:00:00');

        $this->assertSame($older->id, $this->lookupCanal('Farnosť Košice')['id'] ?? null);
    }

    #[Test]
    public function a_short_name_does_not_match_by_substring(): void
    {
        $this->canal('Obecný úrad Kolárovice', '2026-01-01 00:00:00');

        $this->assertNull($this->lookupCanal('Obec'));
    }

    #[Test]
    public function wildcards_in_the_name_are_literal(): void
    {
        $this->canal('Klub priateľov hudby', '2026-01-01 00:00:00');

        $this->assertNull($this->lookupCanal('Klub%hudby'));
        $this->assertNull($this->lookupCanal('Klub_priateľov'));
    }

    #[Test]
    public function a_long_name_still_matches_by_substring(): void
    {
        $canal = $this->canal('Spoločenstvo Ladce a okolie', '2026-01-01 00:00:00');

        $this->assertSame($canal->id, $this->lookupCanal('Spoločenstvo Ladce')['id'] ?? null);
    }

    #[Test]
    public function a_merged_canal_is_not_suggested(): void
    {
        $target = $this->canal('Hnutie Svetlo', '2026-01-01 00:00:00');
        $alias = $this->canal('Hnutie Svetlo života', '2026-02-01 00:00:00');
        $alias->forceFill(['merged_into_id' => $target->id])->save();

        $this->assertNotSame($alias->id, $this->lookupCanal('Hnutie Svetlo života')['id'] ?? null);
    }
}
