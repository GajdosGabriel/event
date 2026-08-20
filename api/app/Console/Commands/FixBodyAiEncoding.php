<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Vyčistí `body_ai` s rozbitou diakritikou, aby ho `app:ai-detector` vygeneroval
 * nanovo — už cez opravený fetcher (viď HtmlCharsetNormalizer).
 *
 * Text sa opraviť nedá: keď libxml prečítal Windows-1250 bajty ako ISO-8859-1,
 * ť/ž/š (0x9D/0x9E/0x9A) v ňom neexistujú a zmizli úplne. Jediná cesta späť je
 * stiahnuť zdroj znova.
 */
class FixBodyAiEncoding extends Command
{
    protected $signature = 'app:fix-body-ai-encoding {--apply : Skutočne vymazať rozbité body_ai}';

    protected $description = 'Najde importované popisy s rozbitou diakritikou a pripraví ich na nový beh AI detektora';

    /**
     * Znaky, ktoré vzniknú čítaním Windows-1250 slovenčiny ako ISO-8859-1.
     * V správnom slovenskom texte sa nevyskytujú.
     */
    private const MOJIBAKE_CHARS = ['è', 'ì', 'ï', 'ò', 'ø', 'ù', 'å', '¾', '¹', '¼', '½', '¯', '¥', 'ª', 'º', '¬', 'ð'];

    public function handle(): int
    {
        $broken = Event::query()
            ->whereNotNull('body_ai')
            ->whereNotNull('orginal_source')
            ->get(['id', 'name', 'body_ai', 'orginal_source'])
            ->filter(fn (Event $event) => $this->looksBroken((string) $event->body_ai));

        if ($broken->isEmpty()) {
            $this->info('Ziadne podujatie s rozbitou diakritikou v body_ai.');

            return self::SUCCESS;
        }

        foreach ($broken as $event) {
            $this->line("#{$event->id} {$event->name}");
        }

        if (! $this->option('apply')) {
            $this->warn($broken->count() . ' podujatí ma rozbite body_ai. Spusti s --apply na vymazanie.');

            return self::SUCCESS;
        }

        Event::query()->whereIn('id', $broken->pluck('id'))->update(['body_ai' => null]);

        $this->info($broken->count() . ' popisov vymazanych. app:ai-detector ich postupne vygeneruje nanovo.');

        return self::SUCCESS;
    }

    private function looksBroken(string $text): bool
    {
        $hits = 0;

        foreach (self::MOJIBAKE_CHARS as $char) {
            $hits += mb_substr_count($text, $char);
        }

        // Jeden zásah môže byť cudzie meno („Æ", „ø" v nórčine). Dva a viac už
        // v slovenskom popise podujatia nie sú náhoda.
        return $hits >= 2;
    }
}
