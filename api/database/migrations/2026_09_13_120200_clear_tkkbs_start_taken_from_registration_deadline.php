<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tkkbs.sk oznamy bez termínu podujatia („Záujemcovia sa môžu prihlásiť do
 * 15. septembra 2026.") dostali uzávierku prihlášok ako začiatok — na webe
 * svietili ako podujatie 15. 9. 2026 (10732, 10790).
 *
 * Keď začiatok padá na deň uzávierky a ten dátum sa v texte inak nespomína,
 * termín sa zmaže, uzávierka sa uloží tam, kam patrí, a podujatie sa vráti
 * medzi koncepty — bez termínu nemá byť verejne v kalendári.
 */
return new class extends Migration
{
    private const TIMEZONE = 'Europe/Bratislava';

    private const MONTHS = [
        'januára' => 1, 'februára' => 2, 'marca' => 3, 'apríla' => 4, 'mája' => 5, 'júna' => 6,
        'júla' => 7, 'augusta' => 8, 'septembra' => 9, 'októbra' => 10, 'novembra' => 11, 'decembra' => 12,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        DB::table('events')
            ->where('orginal_source', 'like', '%tkkbs.sk%')
            ->whereNotNull('start_at')
            ->select(['id', 'body', 'meta', 'start_at', 'status', 'registration_deadline_at'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);
                    $meta = is_array($meta) ? $meta : [];

                    $text = implode("\n", array_filter([
                        is_string($meta['raw_text'] ?? null) ? $meta['raw_text'] : null,
                        is_string($meta['imported_raw_body'] ?? null) ? strip_tags($meta['imported_raw_body']) : null,
                        strip_tags((string) $row->body),
                    ]));

                    $deadline = $this->deadlineOnlyDate($text);
                    $startDay = Carbon::parse($row->start_at, 'UTC')->setTimezone(self::TIMEZONE)->toDateString();

                    if ($deadline === null || $deadline->toDateString() !== $startDay) {
                        continue;
                    }

                    DB::table('events')->where('id', $row->id)->update([
                        'start_at' => null,
                        'end_at' => null,
                        'registration_deadline_at' => $row->registration_deadline_at
                            ?? $deadline->setTime(23, 59, 59)->utc()->format('Y-m-d H:i:s'),
                        'status' => $row->status === 'published' ? 'draft' : $row->status,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Dátum z „prihlásiť sa do D. mesiaca RRRR" — len ak ho text nespomína
     * aj inak než ako hranicu „do …" (vtedy by to mohol byť skutočný termín).
     */
    private function deadlineOnlyDate(string $text): ?Carbon
    {
        $months = implode('|', array_keys(self::MONTHS));

        if (! preg_match('/prihl\p{L}*[^.\d]{0,40}?\bdo:?\s+(\d{1,2})\.\s*('.$months.')\s+(\d{4})/iu', $text, $m)) {
            return null;
        }

        $sameDate = '/(\S+\s+)?\b0?'.(int) $m[1].'\.\s*'.preg_quote($m[2], '/').'\s+'.$m[3].'/iu';
        preg_match_all($sameDate, $text, $all);

        foreach ($all[1] as $before) {
            if (! preg_match('/^do:?\s+$/iu', $before)) {
                return null;
            }
        }

        $month = self::MONTHS[mb_strtolower($m[2])] ?? null;

        return $month === null ? null : Carbon::create((int) $m[3], $month, (int) $m[1], 0, 0, 0, self::TIMEZONE);
    }

    /**
     * Zámerne prázdne — zmazaný termín bol nesprávny.
     */
    public function down(): void {}
};
