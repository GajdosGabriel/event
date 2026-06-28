<?php

namespace App\Services\OpenAI;

class PromptData
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'event_schema',
                'schema' => [
                    'type' => 'object',
                    'required' => [
                        'title',
                        'start_at',
                        'end_at',
                        'organizer',
                        'venue',
                        'email',
                        'phone',
                        'persons',
                    ],
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
                                'properties' => [
                                    'meno' => ['type' => ['string', 'null']],
                                    'telefon' => ['type' => ['string', 'null']],
                                    'email' => ['type' => ['string', 'null']],
                                    'description' => ['type' => ['string', 'null']],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function prompt(string $text): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'Si presný štruktúrovaný extrakčný asistent pre slovenské udalosti.

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
                    . "Vrat iba validny JSON bez dalsieho textu.",
            ],
        ];
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
        ];
    }
}
