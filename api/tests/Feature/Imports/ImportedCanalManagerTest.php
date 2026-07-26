<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\User;
use App\Services\Imports\ImportedCanalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportedCanalManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imports.describe_with_ai', false);

        Role::findOrCreate('super-admin', 'web');
        User::factory()->create()->assignRole('super-admin');
    }

    #[Test]
    public function it_reuses_a_canal_when_the_detected_name_only_adds_an_alias(): void
    {
        // Regresia: LIKE '%nový názov%' zaberie len keď je existujúci názov
        // dlhší. Keď AI pri ďalšom behu doplní alias v zátvorke, je dlhší ten
        // nový, zhoda zlyhá a pre tú istú organizáciu vznikne druhý kanál.
        $existing = Canal::factory()->create([
            'name' => 'Spoločenstvo evanjelických žien',
            'slug' => 'spolocenstvo-evanjelickych-zien',
        ]);

        $before = Canal::query()->count();

        $resolved = app(ImportedCanalManager::class)->resolveOrCreate(
            'Spoločenstvo evanjelických žien (SEŽ)',
            'Spoločenstvo evanjelických žien (SEŽ)',
            'https://www.ecav.sk',
        );

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame($before, Canal::query()->count());
    }

    #[Test]
    public function it_reuses_a_canal_across_dash_and_punctuation_variants(): void
    {
        $existing = Canal::factory()->create([
            'name' => 'Pápežská nadácia ACN – Pomoc trpiacej Cirkvi',
            'slug' => 'papezska-nadacia-acn-pomoc-trpiacej-cirkvi',
        ]);

        $before = Canal::query()->count();

        $resolved = app(ImportedCanalManager::class)->resolveOrCreate(
            'Pápežská nadácia ACN .- Pomoc trpiacej Cirkvi (Aid to the Church in Need)',
            'Pápežská nadácia ACN .- Pomoc trpiacej Cirkvi (Aid to the Church in Need)',
            'https://www.vyveska.sk',
        );

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame($before, Canal::query()->count());
    }

    #[Test]
    public function it_still_creates_a_separate_canal_for_an_unrelated_organizer(): void
    {
        Canal::factory()->create([
            'name' => 'Spišská diecéza',
            'slug' => 'spisska-dieceza',
        ]);

        $resolved = app(ImportedCanalManager::class)->resolveOrCreate(
            'Rožňavská diecéza',
            'Rožňavská diecéza',
            'https://www.vyveska.sk',
        );

        $this->assertSame('Rožňavská diecéza', $resolved->name);
    }
}
