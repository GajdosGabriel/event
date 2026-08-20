<?php

namespace App\Enums;

use App\Models\Event;

/**
 * V akej fáze je nástenka otázok voči svojmu podujatiu.
 *
 * Nástenka je jedna, ale plní dve rôzne úlohy a je to na nej vidieť:
 *
 * - **pred** podujatím smeruje otázka **organizátorovi** a je to praktická vec
 *   („je tam parkovanie?", „môžem prísť s deťmi?"). Odpoveď je trvalá a patrí
 *   na verejný detail ako FAQ — presne na také otázky ľudia googlia.
 * - **počas** podujatia smeruje otázka **prednášajúcemu**, žije hodinu
 *   a premieta sa na plátno.
 * - **po** podujatí je z toho archív, ktorý stránke drží hodnotu aj rok potom.
 *
 * Fáza sa neukladá — odvodzuje sa z termínu, takže sa nemôže rozísť s realitou
 * a nepotrebuje nikoho, kto by ju prepínal.
 */
enum QuestionBoardPhase: string
{
    case Before = 'before';
    case Live = 'live';
    case After = 'after';

    /**
     * Dve hodiny pred začiatkom sa už skúša technika, dve hodiny po konci je
     * otázka na chodbe stále legitímna otázka.
     *
     * Fáza nič neotvára ani nezatvára — to je vec vypínača `is_open`. Hovorí
     * len, **komu** otázka smeruje a v akom poradí sa zoznam ukáže.
     */
    private const LIVE_MARGIN_HOURS = 2;

    public static function for(?Event $event): self
    {
        if ($event?->start_at === null) {
            // Bez termínu sa fáza určiť nedá. „Pred" je najbezpečnejšia voľba:
            // sekcia sa správa ako FAQ, čo dáva zmysel vždy.
            return self::Before;
        }

        $start = $event->start_at->copy()->subHours(self::LIVE_MARGIN_HOURS);
        $end = ($event->end_at ?? $event->start_at)->copy()->addHours(self::LIVE_MARGIN_HOURS);

        if (now()->lt($start)) {
            return self::Before;
        }

        return now()->lte($end) ? self::Live : self::After;
    }

    /** Ide o otázky pre organizátora (FAQ), nie pre prednášajúceho? */
    public function isFaq(): bool
    {
        return $this !== self::Live;
    }
}
