<?php

use App\Models\Canal;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;

/**
 * Zlúčenie duplicitných kanálov a miest (november 2026).
 *
 * Páry sú ručne overené: tá istá organizácia pod dvoma názvami
 * („KBD" / „Katolícke biblické dielo na Slovensku", „Prešovské
 * arcibiskupstvo" / „Prešovská archieparchia", tri kanály UPeCe Bratislava…)
 * a to isté miesto pod adresou aj názvom („Hlavná 89, Košice" / „Seminárny
 * kostol na Hlavnej 89"). Rôzne organizácie na spoločnom portáli (ecav.sk,
 * kbs.sk) ani rôzne rehole s podobným názvom sa nezlučujú.
 *
 * Zlučuje ResourceMerger ako admin (podujatia, adresy, väzby; zdroj ostane
 * aliasom cieľa cez merged_into_id). Pár sa preskočí, keď sedí ID, ale nie
 * názov, keď už niektorá strana neexistuje, alebo keď ho merger odmietne
 * (iný stav, fotky, pozvánky, série podujatí…) — zlúčenie je ťažko vratné.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: int, 2: string, 3: int, 4: string}> [druh, zdroj, názov zdroja, cieľ, názov cieľa] */
    private const PAIRS = [
        ['canals', 507, 'slovenská farnosť v Prahe', 1264, 'Slovenská rímskokatolícka farnosť v Prahe'],
        ['canals', 636, 'Prešovské arcibiskupstvo', 48, 'Prešovská archieparchia'],
        ['canals', 1479, 'Katolícke spoločenstvo Marana Tha Prešov', 70, 'Katolícke spoločenstvo Marana Tha'],
        ['canals', 135, 'M-Aréna', 1892, 'M-Aréna a spoločenstvo MaranaTha'],
        ['canals', 136, 'Banská Bystrica - Centrum pre rodinu', 924, 'Rodina BB'],
        ['canals', 1869, 'CPR Sigord', 196, 'Sigord - Centrum pre rodinu'],
        ['canals', 1725, 'Rožňavský biskup', 227, 'Rožňavská diecéza'],
        ['canals', 261, 'Biskupstvo Banská Bystrica', 717, 'Banskobystrické biskupstvo'],
        ['canals', 1392, 'EKC Zborov', 302, 'Spoločenstvo Extrémnej krížovej cesty (EKC) na Slovensku'],
        ['canals', 891, 'DC pre bezdetných manželov', 336, 'Milujúca náruč, n.o'],
        ['canals', 807, 'Bratia pallotíni', 1554, 'Spoločnosť katolíckeho apoštolátu (pallotíni)'],
        ['canals', 948, 'KBD', 1387, 'Katolícke biblické dielo na Slovensku'],
        ['canals', 1697, 'Farnosť Šaštín-Stráže', 1271, 'rímskokatolícka farnosť v Šaštíne'],
        ['canals', 1039, 'Tím speed dating katRande', 1359, 'katRande.org'],
        ['canals', 1106, 'Bratislavské UPeCe', 775, 'Univerzitné pastoračné centrum (UPeCe) sv. Jozefa Freinademetza'],
        ['canals', 1571, 'UPeCe Bratislava', 775, 'Univerzitné pastoračné centrum (UPeCe) sv. Jozefa Freinademetza'],
        ['canals', 1803, 'Priatelia Svätojakubskej cesty na Slovensku – Camino de Santiago', 1871, 'Confraternity Sancti Iacobi'],
        ['canals', 1866, 'Jasovská kanónia premonštrátov', 1848, 'Nezisková organizácia Rádu premonštrátov – Opátstva Jasov'],
        ['canals', 1856, 'EBF UK', 1857, 'Evanjelická bohoslovecká fakulta UK v Bratislave'],
        ['canals', 35, 'jezuiti', 1876, 'Slovenská provincia Spoločnosti Ježišovej'],
        ['canals', 1243, 'Biskupský úrad VD ECAV na Slovensku', 1730, 'Východný dištrikt Evanjelickej cirkvi augsburského vyznania na Slovensku'],
        ['canals', 11, 'Spoločenstvo evanjelických žien', 13, 'SEŽ ECAV'],
        ['venues', 978, 'Veľká sála UPeCe', 1053, 'Univerzitné pastoračné centrum sv. Jozefa Freinademetza'],
        ['venues', 960, 'Hlavná 89, Košice', 107, 'Seminárny kostol na Hlavnej 89'],
    ];

    public function up(): void
    {
        $merger = app(ResourceMerger::class);

        foreach (self::PAIRS as [$kind, $sourceId, $sourceName, $targetId, $targetName]) {
            $class = $kind === 'canals' ? Canal::class : Venue::class;
            $source = $class::query()->find($sourceId);
            $target = $class::query()->find($targetId);

            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName) {
                continue;
            }

            try {
                $merger->merge($kind, $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie {$sourceName} → {$targetName} preskočené",
                    subject: $target, context: ['source_id' => $sourceId, 'error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    public function down(): void
    {
        // Zlúčenie sa nevracia — zdroj ostáva zmazaný ako alias cieľa (merged_into_id).
    }
};
