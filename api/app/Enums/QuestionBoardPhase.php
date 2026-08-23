<?php

namespace App\Enums;

use App\Models\Event;
use Carbon\CarbonInterface;

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

    /**
     * `$at` je okamih, ku ktorému sa fáza počíta. Predvolene „teraz" — to je
     * pohľad návštevníka na stránke. Dashboard ho posiela ako `created_at`
     * otázky, aby vedel povedať „toto prišlo počas akcie", teda že to nie je
     * otázka do FAQ, ale podnet, ktorý mal riešiť vtedy.
     */
    public static function for(?Event $event, ?CarbonInterface $at = null): self
    {
        if ($event?->start_at === null) {
            // Bez termínu sa fáza určiť nedá. „Pred" je najbezpečnejšia voľba:
            // sekcia sa správa ako FAQ, čo dáva zmysel vždy.
            return self::Before;
        }

        $at ??= now();
        $start = $event->start_at->copy()->subHours(self::LIVE_MARGIN_HOURS);
        $end = ($event->end_at ?? $event->start_at)->copy()->addHours(self::LIVE_MARGIN_HOURS);

        if ($at->lt($start)) {
            return self::Before;
        }

        return $at->lte($end) ? self::Live : self::After;
    }

    /** Ide o otázky pre organizátora (FAQ), nie pre prednášajúceho? */
    public function isFaq(): bool
    {
        return $this !== self::Live;
    }

    /** Práve sa hrá — otázka aj podnet sa riešia teraz, nie zajtra. */
    public function isLive(): bool
    {
        return $this === self::Live;
    }

    /**
     * Musí byť pisateľ súkromného vstupu prihlásený?
     *
     * Počas akcie áno. Podnet („v sále je zima") je prevádzková informácia,
     * podľa ktorej organizátor niečo urobí — a urobí to na základe toho, že
     * píše človek, ktorý v tej sále naozaj sedí. Anonymné „je zima" z druhého
     * konca internetu nie je podnet, je to šum. Zároveň je to jediná forma
     * účtu, ktorú vieme na verejnej stránke vyžadovať bez toho, aby sme
     * pýtali lístok — voľné podujatia žiadny nemajú.
     *
     * Pred akciou a po nej to neplatí: tam je súkromná otázka bežná otázka
     * a adresa na odpoveď je dostatočný kontakt.
     */
    public function requiresAccountForPrivate(): bool
    {
        return $this->isLive();
    }
}
