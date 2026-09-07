<?php

namespace App\Services\Imports;

use App\Support\PlaceholderNames;
use Illuminate\Support\Str;

/**
 * Čistenie názvu organizátora, nech ho prečítal ktokoľvek.
 *
 * Bol to súkromný kus ImportedCanalNameResolver-a. Osamostatnil sa, keď to
 * isté potreboval aj `app:ai-detector`: ten číta celý článok naraz a meno
 * organizátora z neho ide tou istou cestou — do názvu kanála. Bez spoločného
 * orezania by z detektora vznikali kanály menom „Cirkevný zbor ECAV Bardejov
 * vás srdečne pozýva".
 */
class OrganizerName
{
    /** Za týmito slovami už nepokračuje názov organizátora, ale veta pozvánky. */
    private const SENTENCE_MARKERS = [
        'vás', 'vas', 'ťa', 'ta',
        'srdečne', 'srdecne',
        'pozýva', 'pozyva', 'pozývajú', 'pozyvaju', 'pozývame', 'pozyvame',
        'organizuje', 'organizujú', 'usporiada', 'usporadúva',
        'pripravuje', 'pripravujú', 'oznamuje', 'ponúka',
    ];

    public static function sanitize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = self::cutTrailingSentence($value);
        $value = trim($value, " \t\n\r\0\x0B,.;:-/");

        if ($value === '') {
            return null;
        }

        // Zástupné hodnoty ("null", "neuvedené") nie sú názov organizátora —
        // zoznam žije v App\Support\PlaceholderNames, používa ho aj odvodenie
        // sídla kanála.
        if (PlaceholderNames::matches($value)) {
            return null;
        }

        // Názov organizátora nad 120 znakov je v praxi vždy zvyšok vety, nie
        // názov — najdlhšie reálne názvy v dátach majú okolo 80 znakov.
        return Str::limit($value, 120, '');
    }

    /**
     * Holá doména („vyveska.sk", „www.tkkbs.sk") nie je organizátor, ale zdroj.
     * Presne takto sa volajú zberné kanály, takže ich meno sa nesmie vrátiť
     * ako detekovaný organizátor — inak by podujatie „prešlo" zo zberného
     * kanála samo do seba.
     */
    public static function looksLikeHost(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = preg_replace('/^www\./', '', $value) ?? $value;
        $value = rtrim($value, '/');

        return (bool) preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $value);
    }

    /**
     * Odreže vetu pozvánky nalepenú za názov.
     *
     * Heuristiky zachytávajú aj text ako „Cirkevný zbor ECAV Liptovský Mikuláš
     * vás srdečne pozývajú na uvedenie knihy…", z ktorého je názvom len prvá
     * časť. Rez sa robí na hranici slova, takže názvy so slovom vnútri
     * (napr. „Ponúkame n. o.") ostanú nedotknuté.
     */
    private static function cutTrailingSentence(string $value): string
    {
        $pattern = '/\s+(' . implode('|', array_map('preg_quote', self::SENTENCE_MARKERS)) . ')\b.*$/iu';

        $cut = preg_replace($pattern, '', $value);

        // Rez, po ktorom by nezostalo nič zmysluplné, radšej neurobíme.
        if (is_string($cut) && trim($cut) !== '') {
            return $cut;
        }

        return $value;
    }
}
