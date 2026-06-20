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
                'content' => 'Si presny strukturovany extrakcny asistent.

                Tvojou ulohou je extrahovat informacie z textu do striktne validneho JSON podla zadanej schemy.

                PRAVIDLA:
                - Nikdy nevymyslaj udaje.
                - Ak informacia nie je explicitne uvedena, nastav ju na null.
                - Nerozsiruj adresy ani nazvy o domnienky.
                - Organizator je subjekt, ktory akciu organizuje.
                - Venue je fyzicke miesto, kde sa akcia kona.
                - Ak je uvedeny iba jeden subjekt a je zjavne miestom konania, vypln venue a organizer nastav na null.
                - Ak je uvedeny iba organizator bez miesta, vypln organizer a venue nastav na null.
                - Datum a cas vrat vo formate YYYY-MM-DD HH:MM:SS (24h).
                - Pre start_at pouzi datum/cas konania podujatia, nie publikacny datum clanku.
                - V TKKBS textoch cast typu "Mesto 10. aprila (TK KBS)" je redakcna hlava clanku, nie termin podujatia.
                - Ak text obsahuje explicitny cas (napr. "o 17:45"), ten musi byt pouzity v start_at.
                - Ak je uvedeny iba datum bez casu (cely den), nastav start_at na datum 00:00:00 a end_at na datum 23:59:59.
                - Ak je uvedeny cas zaciatku, ale cas konca nie je explicitne uvedeny: odhadni end_at podla povahy akcie (napr. bohosluzba ~1,5h, koncert ~2h, konferencia podla programu a pod.); ak povahu akcie nemozes odhadnut, nastav end_at na start_at + 2 hodiny.
                - end_at nastav na null iba vtedy, ak nie je uvedeny ani cas zaciatku (t. j. je to cely den a uz si nastavil end_at podla pravidla vyssie).

                HEURISTIKA:
                - Slova ako "usporaduje", "organizuje", "v spolupraci s" oznacuju organizatora.
                - Slova ako "kona sa", "miesto konania", "adresa konania", "v budove", "v hoteli", "v centre" oznacuju venue.
                - Slova ako "sa uskutocni", "sa stretna", "pozvame na", "vigilia bude" pomahaju identifikovat termin konania.
                - Ak sa v texte nachadza viac datumov, vyber ten, ktory je naviazany na samotne podujatie, nie na zdroj/clanok.

                Vrat iba validny JSON bez komentarov.',
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
