<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pseudonym anonymného návštevníka — sha256 z IP, user-agenta, aplikačného
 * kľúča a dnešného dátumu.
 *
 * IP sa nikam neukladá, cookie sa nenastavuje, a keďže je v hashi dátum,
 * pseudonym sa každý deň mení. Z tabuliek, ktoré ho používajú, sa teda nedá
 * poskladať, čo konkrétny človek robil naprieč dňami.
 *
 * Vzniklo vytiahnutím z ViewRecorder, keď ten istý pseudonym potreboval aj
 * dedup otázok z publika. Poradie polí v hashi zostalo nedotknuté zámerne —
 * inak by sa v deň nasadenia každému návštevníkovi zmenil pseudonym a
 * počítadlo zobrazení by ten deň rátalo dvakrát.
 */
final class VisitorPseudonym
{
    public static function forRequest(Request $request): string
    {
        return hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) config('app.key'),
            now()->toDateString(),
        ]));
    }
}
