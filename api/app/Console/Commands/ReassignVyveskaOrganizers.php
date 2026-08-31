<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Services\Imports\ImportedNameMatcher;
use App\Services\Imports\ImportedCanalManager;
use App\Services\Publishing\EventDependencyPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Jednorazová oprava: podujatia importované z vyveska.sk skončili hromadne na
 * zbernom kanáli „vyveska.sk", lebo starší import z tohto zdroja nečítal pole
 * „Organizátor:" z info-boxu. Novší import to už rieši (EventDetailService), no
 * staré podujatia treba prepojiť dodatočne.
 *
 * Organizátora neberie znova z AI — pre väčšinu podujatí ho už raz určil nočný
 * `app:ai-detector` a je uložený v `meta.ai_detector.event_payload.organizer`.
 * Kanál sa zakladá/hľadá cez ten istý `ImportedCanalManager` ako riadny import
 * (fuzzy zhoda mien, systémové vlastníctvo, popis, identity_mode).
 *
 * Postup na produkcii: najprv `--dry-run`, skontrolovať tabuľku, potom bez neho.
 * Podujatia, pri ktorých organizátor známy nie je, ostávajú na vyveska.sk.
 */
class ReassignVyveskaOrganizers extends Command
{
    protected $signature = 'app:reassign-vyveska-organizers
        {--dry-run : Iba vypísať, čo by sa zmenilo}
        {--canal-id= : ID zberného kanála vyveska.sk (inak sa nájde automaticky)}
        {--limit=0 : Maximálny počet podujatí (0 = všetky)}';

    protected $description = 'Priradí podujatia zo zberného kanála vyveska.sk k reálnym organizátorom';

    /**
     * Priveľmi generické „názvy" organizátora — AI ich vráti, keď v texte
     * konkrétny subjekt nie je. Nechávajú sa na zbernom kanáli: cez
     * `LIKE %názov%` v ImportedCanalManager by sa inak nalepili na náhodný
     * existujúci kanál, ktorý toto slovo náhodou obsahuje.
     */
    private const GENERIC_ORGANIZER_NAMES = [
        'farsky urad', 'farnost', 'rimskokatolicka farnost', 'greckokatolicka farnost',
        'kostol', 'obec', 'mesto', 'organizator', 'farsky urad gaboltov',
    ];

    /** Hostitelia, ktorí nie sú webom organizátora (registračné formuláre, ticketing). */
    private const NON_ORGANIZER_HOSTS = [
        'vyveska.sk',
        'docs.google.com', 'forms.gle', 'drive.google.com',
        'inviton.eu', 'reenio.sk', 'jimdosite.com',
    ];

    public function handle(ImportedCanalManager $canalManager, EventDependencyPublisher $dependencyPublisher): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $source = $this->resolveSourceCanal();
        if (! $source instanceof Canal) {
            return self::FAILURE;
        }

        $this->info(sprintf('Zberný kanál: #%d %s (%s)', $source->id, $source->name, $source->website ?? '—'));

        $query = Event::query()->where('canal_id', $source->id)->orderBy('id');
        if (($limit = max(0, (int) $this->option('limit'))) > 0) {
            $query->limit($limit);
        }

        $events = $query->get();
        $this->line(sprintf('Podujatí na kanáli: %d', $events->count()));
        $this->newLine();

        $moved = 0;
        $createdCanalIds = [];
        $unresolved = [];

        // Kanály, ktoré existovali už pred týmto behom — čokoľvek mimo je nové.
        // (resolveOrCreate() vracia fresh() model, takže `wasRecentlyCreated`
        // sa spoľahnúť nedá.)
        $knownCanalIds = Canal::query()->pluck('id')->flip();

        foreach ($events as $event) {
            $organizerName = $this->organizerNameFor($event);

            if ($organizerName === null) {
                $unresolved[] = $event;
                $this->line(sprintf('  <fg=yellow>skip</> #%d %s — organizátor neznámy', $event->id, Str::limit($event->name, 60)));
                continue;
            }

            $website = $this->organizerWebsite($event);

            if ($dryRun) {
                $existing = $this->previewCanal($organizerName);
                $target = $existing instanceof Canal
                    ? sprintf('existujúci #%d „%s"', $existing->id, $existing->name)
                    : 'NOVÝ kanál';
                $this->line(sprintf(
                    '  #%d %s',
                    $event->id,
                    Str::limit($event->name, 55),
                ));
                $this->line(sprintf('       -> %s  [%s]  web: %s', $organizerName, $target, $website ?? '—'));
                continue;
            }

            $canal = $canalManager->resolveOrCreate($organizerName, $organizerName, $website ?? '');

            if ($canal->id === $source->id) {
                $unresolved[] = $event;
                $this->line(sprintf('  <fg=yellow>skip</> #%d %s — zhoda vrátila zberný kanál', $event->id, Str::limit($event->name, 50)));
                continue;
            }

            if (! $knownCanalIds->has($canal->id)) {
                $knownCanalIds[$canal->id] = true;
                $createdCanalIds[$canal->id] = $canal->name;
            }

            $event->update(['canal_id' => $canal->id]);

            if ($event->venue_id !== null) {
                $venue = Venue::query()->find($event->venue_id);
                $venue?->assignCanal($canal, isOwner: true);
            }

            if ($event->status === ModelStatus::Published) {
                $dependencyPublisher->publishAll($event->fresh());
            }

            $moved++;
            $this->line(sprintf('  <fg=green>move</> #%d %s -> #%d %s', $event->id, Str::limit($event->name, 45), $canal->id, $canal->name));
        }

        $this->newLine();
        if ($dryRun) {
            $this->info(sprintf('[dry-run] priradilo by sa: %d, bez organizátora (ostávajú): %d', $events->count() - count($unresolved), count($unresolved)));
        } else {
            $this->info(sprintf('Priradených: %d', $moved));
            $this->info(sprintf('Nových kanálov: %d', count($createdCanalIds)));
            foreach ($createdCanalIds as $id => $name) {
                $this->line(sprintf('  + #%d %s', $id, $name));
            }
            $this->info(sprintf('Bez organizátora (ostávajú na #%d): %d', $source->id, count($unresolved)));
        }

        foreach ($unresolved as $event) {
            $this->line(sprintf('  · #%d %s — %s', $event->id, Str::limit($event->name, 55), $event->orginal_source));
        }

        return self::SUCCESS;
    }

    private function resolveSourceCanal(): ?Canal
    {
        if (($id = $this->option('canal-id')) !== null) {
            $canal = Canal::query()->find((int) $id);
            if (! $canal instanceof Canal) {
                $this->error("Kanál #{$id} neexistuje.");

                return null;
            }

            return $canal;
        }

        // Zberný kanál je ten, ktorý sa volá presne po hostiteľovi zdroja
        // (hostLabel() → „vyveska.sk"). Reálne kanály organizátorov majú tiež
        // website = vyveska.sk (source_origin z pipeline), takže podľa webu sa
        // rozlíšiť nedajú — rozhoduje meno/slug.
        $candidates = Canal::query()
            ->where('registration_source', RegistrationSource::IMPORT->value)
            ->where(function ($q) {
                $q->whereIn('slug', ['vyveskask', 'vyveska-sk', 'www-vyveskask'])
                    ->orWhereIn('name', ['vyveska.sk', 'www.vyveska.sk', 'Výveska', 'výveska']);
            })
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->isEmpty()) {
            $this->error('Zberný kanál vyveska.sk sa nenašiel — zadaj --canal-id.');

            return null;
        }

        $this->error('Našlo sa viac kandidátov na zberný kanál — zadaj --canal-id:');
        foreach ($candidates as $canal) {
            $this->line(sprintf('  #%d %s (%s)', $canal->id, $canal->name, $canal->website));
        }

        return null;
    }

    private function organizerNameFor(Event $event): ?string
    {
        $meta = is_array($event->meta) ? $event->meta : [];

        $candidates = [
            data_get($meta, 'ai_detector.event_payload.organizer.name'),
            data_get($meta, 'ai_detect_from_text.organizer_name'),
            data_get($meta, 'import.detected_canal_name'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $name = trim($candidate);

            if ($this->isGenericName($name)) {
                return null;
            }

            return $name;
        }

        return null;
    }

    private function isGenericName(string $name): bool
    {
        $ascii = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->value();

        return in_array($ascii, self::GENERIC_ORGANIZER_NAMES, true);
    }

    /**
     * Web organizátora z poľa `website` podujatia — ak ním nie je registračný
     * formulár, ticketing alebo samotná vyveska.sk. Skracuje sa na origin, aby
     * kanál nedostal do webu hlbokú URL konkrétneho podujatia.
     */
    private function organizerWebsite(Event $event): ?string
    {
        $website = is_string($event->website) ? trim($event->website) : '';
        if ($website === '') {
            return null;
        }

        $host = strtolower((string) parse_url($website, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === '') {
            return null;
        }

        foreach (self::NON_ORGANIZER_HOSTS as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                return null;
            }
        }

        $scheme = (string) (parse_url($website, PHP_URL_SCHEME) ?: 'https');

        return $scheme . '://' . $host;
    }

    /** Odhad, čo by resolveOrCreate() spravil — bez zápisu (len pre --dry-run). */
    private function previewCanal(string $name): ?Canal
    {
        $slug = Str::slug($name);

        $direct = Canal::query()
            ->where('slug', $slug)
            ->orWhere('name', $name)
            ->orWhere('name', 'like', '%' . Str::limit($name, 100, '') . '%')
            ->orderByDesc('created_at')
            ->first();

        return $direct ?? ImportedNameMatcher::firstByBaseName(Canal::query(), $name);
    }
}
