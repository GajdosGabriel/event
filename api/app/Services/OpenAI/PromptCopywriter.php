<?php

namespace App\Services\OpenAI;

class PromptCopywriter
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'event_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => [
                        'event_body'
                    ],
                    'properties' => [
                        'event_body' => ['type' => ['string', 'null']],
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
                'content' => 'Si copywriter pre duchovne a kulturne podujatia.

                    Tvojou ulohou je ROZSIRIT existujuci text.
                    Nikdy nemen fakty.
                    Nikdy nevymyslaj nove informacie.
                    Zachovaj datum, miesto, cenu, kontakt.

                    Text mas obohatit, prehlbit a spravit zaujimavejsim,
                    nie ho skratit ani zmenit jeho obsah.
                    Pouzi HTML tagy: p, strong, h3, ul, li.
                    Povolene atributy: class na h3, ul, li.
                    Pri tagoch h3, ul a li je class povinna.
                    Nepouzivaj ine atributy okrem class.',
            ],
            [
                'role' => 'user',
                'content' => "Vstupny text:{$text}
                    Vytvor rozsireny HTML text.

                    Pravidla:
                    - Zachovaj vsetky povodne informacie.
                    - Nic nevynechaj.
                    - Nic nemen.
                    - Text rozsir o motivacne a obsahove vysvetlenie.
                    - Rozdel do 3 sekcii s <h3> nadpismi.
                    - Nadpisy pis ako <h3 class=\"event-section-title\">...</h3>
                    - Zoznamy pis ako <ul class=\"event-list\">...</ul>
                    - Polozky zoznamu pis ako <li class=\"event-list-item\">...</li>
                    - Zachovaj cenu, miesto, datum, email.
                    - Nepouzivaj frazy typu 'srdecne vas pozyvame'

                    Vrat iba validny JSON bez dalsieho textu."
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'event_body' => 'sometimes|nullable|string',
        ];
    }
}
