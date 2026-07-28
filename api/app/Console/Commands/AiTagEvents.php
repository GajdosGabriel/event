<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Services\Tags\EventTagger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Doštítkuje podujatia, ktoré štítky ešte nemajú alebo im medzitým zmenil text.
 *
 * Beží z webcronu spolu s ostatnými plánovanými príkazmi v jednom HTTP requeste,
 * preto malá dávka a tvrdý časový strop.
 */
class AiTagEvents extends Command
{
    protected $signature = 'app:events-ai-tag
        {--limit=5 : Počet podujatí na beh (0 = bez limitu, na ručný backfill)}
        {--sleep=200 : Pauza v ms medzi volaniami AI}
        {--time-budget=12 : Strop behu v sekundách (0 = bez stropu, len na ručný backfill)}
        {--force : Preštítkovať aj podujatia, ktorým sa text ani číselník nezmenili}
        {--dry-run : Iba vypísať, čo by sa priradilo}';

    protected $description = 'Assign content tags to events using AI';

    /**
     * Po treťom zlyhaní sa podujatie prestane vyberať. Bez toho by podujatie
     * s trvalo padajúcim volaním viselo vo fronte navždy a každý beh by stálo
     * peniaze.
     */
    private const MAX_ATTEMPTS = 3;

    public function handle(EventTagger $tagger): int
    {
        $start = microtime(true);
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        // Príkazy zo schedulera bežia sekvenčne v jednom webcron requeste, kde
        // už každú minútu beží aj app:ai-detector — preto nízky predvolený strop.
        $timeBudget = max(0, (int) $this->option('time-budget'));

        // Bez tejto zarážky zlyhá dávka na každom podujatí rovnako, každému
        // pritom spáli pokus a po treťom behu podujatia vypadnú z výberu
        // natrvalo — hoci chyba je v deployi, nie v dátach.
        if (! $tagger->hasCatalog()) {
            $message = 'AiTagEvents: číselník štítkov je prázdny — spustite `php artisan db:seed --class=TagSeeder --force`.';

            Log::error($message);
            $this->error($message);

            return self::FAILURE;
        }

        $query = Event::query()
            ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Scheduled->value])
            ->where('ai_tags_attempts', '<', self::MAX_ATTEMPTS)
            ->orderByRaw('ai_tagged_at IS NOT NULL')
            ->orderBy('id');

        if (! $this->option('force')) {
            // Výraz musí presne sedieť s EventTagger::contentHash(), inak by sa
            // tie isté podujatia štítkovali dokola. Verzia číselníka je prvý
            // člen — po doplnení štítka do seedra sa preštítkuje všetko.
            $query->whereRaw(
                "(ai_tagged_at IS NULL OR ai_tags_hash <> MD5(CONCAT_WS('|', ?, COALESCE(name, ''), COALESCE(body_ai, ''), COALESCE(body, ''))))",
                [$tagger->catalogVersion()],
            );
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('AiTagEvents: no eligible event found.');

            return self::SUCCESS;
        }

        $tagged = 0;
        $failed = 0;
        $stoppedEarly = false;

        foreach ($events as $event) {
            if ($timeBudget > 0 && microtime(true) - $start > $timeBudget) {
                $stoppedEarly = true;
                break;
            }

            // Pokus sa počíta PRED volaním — pád uprostred requestu inak necháva
            // podujatie vo výbere navždy. EventTagger ho po úspechu vynuluje.
            if (! $dryRun) {
                DB::table('events')->where('id', $event->id)->increment('ai_tags_attempts');
            }

            $result = $tagger->tag($event, $dryRun);

            if (! ($result['success'] ?? false)) {
                $failed++;

                Log::warning('AiTagEvents failed for event.', [
                    'event_id' => $event->id,
                    'error' => $result['error'] ?? 'Unknown tagger error',
                ]);

                $this->warn(sprintf(' - #%d %s -> %s', $event->id, $event->name, $result['error'] ?? 'chyba'));

                continue;
            }

            $tagged++;

            $this->line(sprintf(
                ' - #%d %s -> %s%s%s',
                $event->id,
                $event->name,
                implode(', ', $result['tags'] ?? []) ?: '(žiadne)',
                ($result['derived'] ?? []) !== [] ? ' + ' . implode(', ', $result['derived']) : '',
                ($result['suggested'] ?? []) !== [] ? '  [návrhy: ' . implode(', ', $result['suggested']) . ']' : '',
            ));

            $this->pause();
        }

        $this->info(sprintf(
            'AiTagEvents: oštítkovaných %d, zlyhaní %d%s%s',
            $tagged,
            $failed,
            $dryRun ? ' (dry-run)' : '',
            $stoppedEarly ? ' — zastavené na časovom strope' : '',
        ));

        // Zlyhanie jednotlivých podujatí nemá zhodiť celý plánovaný beh; chybu
        // hlásime len keď neprešlo vôbec nič.
        return ($failed > 0 && $tagged === 0) ? self::FAILURE : self::SUCCESS;
    }

    private function pause(): void
    {
        $sleepMs = max(0, (int) $this->option('sleep'));

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }
}
