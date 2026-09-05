<?php

namespace App\Console\Commands;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Services\Canals\CanalSeatDeriver;
use Illuminate\Console\Command;

/**
 * Opraví sídlo importovaných kanálov podľa mesta organizátora z ich článkov.
 *
 * Kým sa obec kanála odvodzovala výhradne z miest jeho podujatí, stačilo, aby
 * import trafil rovnomenné miesto v inom meste — a organizátor sedel v obci,
 * ktorá v článku ani nebola (múzeum z Trnavy na Košiciach podľa rovnomenného
 * kostola). Mesto organizátora je pritom v `meta` uložené, len ho dovtedy
 * nikto nepoužil.
 *
 * Ručne zadanú obec príkaz neprepíše — stráži to `applyDetectedCity()`.
 */
class BackfillCanalSeats extends Command
{
    protected $signature = 'app:backfill-canal-seats
        {--canal= : Spracovať iba tento kanál}
        {--limit=0 : Maximálny počet kanálov (0 = bez limitu)}
        {--dry-run : Iba vypísať, čo by sa zmenilo}';

    protected $description = 'Doplní importovaným kanálom obec podľa mesta organizátora prečítaného z článkov';

    public function handle(CanalSeatDeriver $seatDeriver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $query = Canal::query()
            ->where('registration_source', RegistrationSource::IMPORT->value)
            ->orderBy('id');

        if ($this->option('canal') !== null) {
            $query->where('id', (int) $this->option('canal'));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $changed = 0;
        $skipped = 0;

        foreach ($query->get() as $canal) {
            $city = $this->detectedCity($canal);

            // Mesto z článku ešte nie je obec z číselníka: cudzie mesto (Praha,
            // Turín) ani zástupná hodnota sa nepreložia a ručne zadané sídlo sa
            // neprepisuje. Preto sa výsledok pýta dopredu — aj v suchom behu.
            $municipalityId = $seatDeriver->detectedSeat($canal, $city);

            if ($municipalityId === null) {
                $skipped++;
                continue;
            }

            $municipality = Municipality::query()->find($municipalityId)?->fullname ?? $municipalityId;
            $prefix = $dryRun ? '[dry-run] ' : '';

            if (! $dryRun && ! $seatDeriver->applyDetectedCity($canal, $city)) {
                $skipped++;
                continue;
            }

            $this->line("{$prefix}kanál {$canal->id} ({$canal->name}): {$city} → {$municipality}");
            $changed++;
        }

        $this->info("Hotovo: zmenených {$changed}, preskočených {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Mesto organizátora z podujatí kanála.
     *
     * Poradie zdrojov kopíruje ich spoľahlivosť: `ai_detector` číta celý
     * článok naraz, kým `import.detected_canal_city` skladá výsledok z regexov
     * nad scrapnutým textom. Berie sa prvé mesto, ktoré sa nájde — kanály
     * s podujatiami vo viacerých mestách rieši `applyDetectedCity()` tým, že
     * odvodenú obec neprepíše nad ručne zadanú.
     */
    private function detectedCity(Canal $canal): ?string
    {
        $events = Event::query()
            ->where('canal_id', $canal->id)
            ->whereNotNull('meta')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'meta']);

        foreach ([
            ['ai_detector', 'event_payload', 'organizer', 'city'],
            ['import', 'detected_canal_city'],
        ] as $path) {
            foreach ($events as $event) {
                $value = is_array($event->meta) ? $event->meta : [];

                foreach ($path as $key) {
                    $value = is_array($value) ? ($value[$key] ?? null) : null;
                }

                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }
}
