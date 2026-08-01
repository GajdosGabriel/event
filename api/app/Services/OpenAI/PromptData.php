<?php

namespace App\Services\OpenAI;

use Carbon\Carbon;

class PromptData
{
    /**
     * @param  bool  $withPosterText  pridá `poster_text` — doslovný prepis plagátu.
     *                                Má zmysel len pri vision volaní: pri textovom
     *                                vstupe by model len vrátil to, čo už máme.
     */
    public function jsonSchema(bool $withPosterText = false): array
    {
        $required = [
            'title',
            'start_at',
            'end_at',
            'organizer',
            'venue',
            'email',
            'phone',
            'persons',
        ];

        if ($withPosterText) {
            $required[] = 'poster_text';
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'event_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    // `strict: true` vyžaduje, aby v `required` boli všetky
                    // properties — voliteľné pole preto pribúda do oboch naraz.
                    'required' => $required,
                    'properties' => [

                        'title' => ['type' => ['string', 'null']],
                        'start_at' => ['type' => ['string', 'null']],
                        'end_at' => ['type' => ['string', 'null']],

                        // 👇 TU IDE ORGANIZER
                        'organizer' => [
                            'type' => ['object', 'null'],
                            'required' => ['name', 'street_and_number', 'city'],
                            'properties' => [
                                'name' => ['type' => ['string', 'null']],
                                'street_and_number' => ['type' => ['string', 'null']],
                                'city' => ['type' => ['string', 'null']],
                            ],
                            'additionalProperties' => false,
                        ],

                        // 👇 TU IDE VENUE
                        'venue' => [
                            'type' => ['object', 'null'],
                            'required' => ['name', 'street_and_number', 'city'],
                            'properties' => [
                                'name' => ['type' => ['string', 'null']],
                                'street_and_number' => ['type' => ['string', 'null']],
                                'city' => ['type' => ['string', 'null']],
                            ],
                            'additionalProperties' => false,
                        ],

                        'email' => ['type' => ['string', 'null']],
                        'phone' => ['type' => ['string', 'null']],

                        'persons' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['meno', 'telefon', 'email', 'description'],
                                'properties' => [
                                    'meno' => ['type' => ['string', 'null']],
                                    'telefon' => ['type' => ['string', 'null']],
                                    'email' => ['type' => ['string', 'null']],
                                    'description' => ['type' => ['string', 'null']],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],

                        ...($withPosterText
                            ? ['poster_text' => ['type' => ['string', 'null']]]
                            : []),
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  bool  $withPosterText  viď jsonSchema() — musí sedieť so schémou,
     *                                inak model pole buď nevráti, alebo ho vráti
     *                                bez toho, aby vedel, čo doň patrí.
     */
    public function prompt(string $text, Carbon $referenceDate, bool $withPosterText = false): array
    {
        $referenceDateFormatted = $referenceDate->format('Y-m-d');
        $referenceYear = $referenceDate->year;
        $nextYear = $referenceYear + 1;

        $dateContext = "DNEŠNÝ DÁTUM (dátum publikovania/extrakcie článku): {$referenceDateFormatted}

PRAVIDLO PRE CHÝBAJÚCI ROK:
- Ak text pri dátume podujatia neuvádza rok (napr. \"v piatok 3. júla\", \"25. júna\"), rok NIKDY nesmie byť v minulosti voči dnešnému dátumu vyššie.
- Za normálnych okolností použi aktuálny rok {$referenceYear}.
- Výnimka: ak je dnešný mesiac december a mesiac podujatia je skorší ako december (napr. januárová akcia spomenutá v decembrovom článku), použi nasledujúci rok {$nextYear}, pretože akcia sa evidentne koná až budúci rok.
- Rok nikdy nehádaj ako minulý rok len preto, že si si istý inou hodnotou z trénovacích dát — vždy vychádzaj z dnešného dátumu uvedeného vyššie.

";

        return [
            [
                'role' => 'system',
                'content' => $dateContext . 'Si presný štruktúrovaný extrakčný asistent pre slovenské udalosti.

Tvojou úlohou je extrahovať informácie z textu do striktne validného JSON podľa zadanej schémy.

PRAVIDLÁ:
- Nikdy nevymýšľaj údaje. Ak informácia nie je explicitne uvedená, nastav ju na null.
- Nerozširuj adresy ani názvy o domienky.
- Organizátor je subjekt, ktorý akciu organizuje (inštitúcia, zbor, farnosť…).
- Venue je fyzické miesto, kde sa akcia koná (kostol, sála, katedrála, centrum…).
- Ak je uvedený iba jeden subjekt a je zjavne miestom konania, vyplň venue a organizer nastav na null.
- Ak je uvedený iba organizátor bez miesta, vyplň organizer a venue nastav na null.

DÁTUM A ČAS:
- Vráť lokálny slovenský čas (Europe/Bratislava) vo formáte YYYY-MM-DD HH:MM:SS (24h). Nekonvertuj na UTC.
- Pre start_at použi dátum/čas konania podujatia, NIE publikačný dátum článku.
- V TK KBS textoch časť typu "Bratislava 25. júna (TK KBS)" je redakčná hlavička článku, nie termín podujatia.
- Ak text obsahuje explicitný čas (napr. "o 17:45"), ten musí byť použitý v start_at.
- Ak je uvedený iba dátum bez času (celý deň), nastav start_at na dátum 00:00:00 a end_at na dátum 23:59:59.
- Ak je uvedený čas začiatku ale čas konca nie je explicitne uvedený, odhadni end_at podľa povahy akcie:
  * pontifikálna svätá omša, pontifikálna bohoslužba: ~1,5 hodiny
  * svätá omša, sv. omša, omša: ~1 hodina
  * bohoslužba ECAV, evanjelická bohoslužba: ~1,5 hodiny
  * koncert, hudobné podujatie: ~2 hodiny
  * konferencia, seminár: podľa programu, inak ~4 hodiny
  * ak povahu akcie nedokážeš odhadnúť: start_at + 2 hodiny
- end_at nastav na null iba vtedy, ak nie je uvedený ani čas začiatku (celý deň — už si nastavil end_at podľa pravidla vyššie).

VENUE Z PRÓZY:
- Venue hľadaj aj vo vete formátu "o HH:MM v [Miesto] v [Mesto]" — prvý veľkým písmenom začínajúci výraz po čase je venue, druhý (za druhým "v") je mesto.
- Príklad: "o 18:00 v Katedrále svätého Martina v Bratislave" → venue.name = "Katedrála svätého Martina", venue.city = "Bratislava".
- Venue name vráť v nominatíve (základný tvar), nie v lokáli ("Katedrála" nie "Katedrále").
- Názov obce alebo mesta patrí VŽDY do venue.city, nikdy do venue.street_and_number — aj keď je na plagáte napísaný v jednom riadku s ulicou alebo s okolím ("Klokočov - Zemplínska Šírava" → venue.city = "Klokočov").
- Do street_and_number daj len ulicu s číslom domu. Ak plagát ulicu neuvádza, nastav ju na null.
- Ak obec nie je uvedená pri mieste, ale je v názve podujatia ("Odpustová slávnosť Klokočov"), použi ju ako venue.city.

HEURISTIKA:
- Slová ako "usporadúva", "organizuje", "v spolupráci s" označujú organizátora.
- Slová ako "koná sa", "miesto konania", "adresa konania", "v budove", "v kostole", "v katedrále", "v centre" označujú venue.
- Slová ako "sa uskutoční", "sa stretne", "pozývame na", "vigília bude" pomáhajú identifikovať termín konania.
- Ak sa v texte nachádza viac dátumov, vyber ten, ktorý je naviazaný na samotné podujatie, nie na zdroj/článok.

Vráť iba validný JSON bez komentárov.',
            ],
            [
                'role' => 'user',
                'content' => "Vstupny text:\n{$text}\n\n"
                    . "Z tohto textu extrahuj JSON objekt s klucmi:\n"
                    . "- title\n"
                    . "- start_at (YYYY-MM-DD HH:MM:SS)\n"
                    . "- end_at (YYYY-MM-DD HH:MM:SS)\n"
                    . "- organizer: { name, street_and_number, city }\n"
                    . "- venue: { name, street_and_number, city }\n"
                    . "- email\n"
                    . "- phone\n"
                    . "- persons: zahrn kazdu fyzicku osobu z textu; aj bez kontaktu; description je rola alebo kontext; chybajuci email/telefon nastav na null\n"
                    . ($withPosterText ? $this->posterTextInstruction() : '')
                    . "Vrat iba validny JSON bez dalsieho textu.",
            ],
        ];
    }

    /**
     * Prepis plagátu. Bez neho z obrázkového plagátu nevznikne popis podujatia:
     * textová vrstva je prázdna, takže copywriter nemá čo rozšíriť a v tele
     * podujatia ostane prázdno — a to je pri plagáte s programom (harmonogram
     * púte, časy bohoslužieb) presne tá informácia, kvôli ktorej ho človek
     * nahráva. Preto sa pýta v tom istom volaní: vision je drahé a druhý
     * prechod tými istými obrázkami by cenu zdvojnásobil.
     */
    private function posterTextInstruction(): string
    {
        return "- poster_text: DOSLOVNY prepis vsetkeho textu z prilozenych obrazkov plagatu.\n"
            . "  * Prepisuj v poradi, v akom je text na plagate, riadok po riadku.\n"
            . "  * Zachovaj cely program: kazdy datum, cas aj nazov bodu programu.\n"
            . "  * Zachovaj mena, ceny, kontakty a poznamky pod ciarou.\n"
            . "  * Riadky oddeluj znakom nového riadku, nic nesumarizuj a nic nedopisuj.\n"
            . "  * Ak je vstupom iba text (ziadny obrazok), nastav poster_text na null.\n";
    }

    public function validator(): array
    {
        return [
            'title' => 'sometimes|nullable|string',
            'start_at' => 'sometimes|nullable|string',
            'end_at' => 'sometimes|nullable|string',
            'organizer' => 'sometimes|nullable|array',
            'venue' => 'sometimes|nullable|array',
            'email' => 'sometimes|nullable|string',
            'phone' => 'sometimes|nullable|string',
            'persons' => 'sometimes|array',
            'poster_text' => 'sometimes|nullable|string',
        ];
    }
}
