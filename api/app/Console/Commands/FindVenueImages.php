<?php

namespace App\Console\Commands;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Services\Files\FileManager;
use App\Services\Places\VenueImageFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Doplní miestam bez obrázka fotky z Wikimedia Commons (najviac 4).
 *
 * Prvá fotka sa stane hlavnou (avatar), ďalšie idú do galérie. Miesto, ktoré
 * už nejaký obrázok má, sa nedotkne — ani keď ho niekto zmazal zámerne.
 * Pri miestach bez zhody si príkaz na 30 dní zapamätá, že ich skúšal, aby
 * ďalší beh nevolal Wikidata zbytočne (--force to obíde).
 */
class FindVenueImages extends Command
{
    protected $signature = 'app:venues-find-images
        {--venue=* : ID konkrétnych miest}
        {--limit=50 : Maximum miest v jednom behu}
        {--max=4 : Maximum fotiek na miesto (1–4)}
        {--dry-run : Len vypíše, čo by nahral}
        {--force : Skúsi aj miesta, pri ktorých posledný beh nič nenašiel}
        {--sleep=1000 : Pauza medzi miestami v ms (ohľaduplnosť k Wikidata)}';

    protected $description = 'Nájde fotky miest na Wikimedia Commons a nahrá ich ako avatar a galériu';

    private const MISS_TTL_DAYS = 30;

    public function handle(VenueImageFinder $finder, FileManager $files): int
    {
        $max = max(1, min(4, (int) $this->option('max')));
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_filter(array_map('intval', (array) $this->option('venue')));

        $query = Venue::query()
            ->with('municipality')
            ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Draft->value])
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', '<>', 'fallback'))
            ->whereDoesntHave('images')
            ->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $processed = $found = $stored = 0;

        foreach ($query->lazy() as $venue) {
            if ($processed >= max(1, (int) $this->option('limit'))) {
                break;
            }

            $missKey = 'venue-images:miss:'.$venue->id;

            if (! $this->option('force') && $ids === [] && Cache::has($missKey)) {
                continue;
            }

            if ($processed > 0 && (int) $this->option('sleep') > 0) {
                usleep((int) $this->option('sleep') * 1000);
            }

            $processed++;
            $images = $finder->find($venue, $max);

            if ($images === []) {
                $this->line(sprintf(' - #%d %s → nenájdené', $venue->id, $venue->name));

                if (! $dryRun) {
                    Cache::put($missKey, true, now()->addDays(self::MISS_TTL_DAYS));
                }

                continue;
            }

            $found++;
            $this->line(sprintf(' + #%d %s → %s %s (%d)', $venue->id, $venue->name, $images[0]['wikidata'], $images[0]['label'], count($images)));

            foreach ($images as $index => $image) {
                $this->line(sprintf('     %s  [%s, %s]', $image['commons_page'], $image['author'] ?? '?', $image['license'] ?? '?'));

                if ($dryRun) {
                    continue;
                }

                try {
                    // upload.wikimedia.org pri sérii sťahovaní dočasne odmieta (429) —
                    // dva pokusy navyše s rastúcou pauzou.
                    retry([3000, 10000], fn () => $files->storeRemoteForModel(
                        model: $venue,
                        attachments: [['url' => $image['url'], 'name' => $image['name'], 'user_agent' => $finder->userAgent()]],
                        type: FileType::IMAGE,
                        makePrimary: $index === 0,
                        meta: [
                            'origin' => 'wikimedia_commons',
                            'wikidata' => $image['wikidata'],
                            'commons_title' => $image['commons_title'],
                            'commons_page' => $image['commons_page'],
                            'author' => $image['author'],
                            'license' => $image['license'],
                            'license_url' => $image['license_url'],
                        ],
                    ));
                    $stored++;
                } catch (\Throwable $e) {
                    $this->warn('     nepodarilo sa stiahnuť: '.$e->getMessage());
                }
            }
        }

        $this->info(sprintf('Miesta: %d, s nájdenou fotkou: %d, nahraných fotiek: %d%s', $processed, $found, $stored, $dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
