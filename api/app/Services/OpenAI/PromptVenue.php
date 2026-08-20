<?php

namespace App\Services\OpenAI;

class PromptVenue
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'venue_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => [
                        'name',
                        'street',
                        'postcode',
                        'city',
                        'country',
                        'latitude',
                        'longitude',
                    ],
                    'properties' => [
                        'name' => ['type' => ['string', 'null']],
                        'street' => ['type' => ['string', 'null']],
                        'postcode' => ['type' => ['string', 'null']],
                        'city' => ['type' => ['string', 'null']],
                        'country' => ['type' => ['string', 'null']],
                        'latitude' => ['type' => ['number', 'null']],
                        'longitude' => ['type' => ['number', 'null']],
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
                'content' => 'Si konzervativny asistent na doplnanie detailov miesta.

                Tvojou ulohou je vratit iba tie udaje o mieste, ktore vies priradit s vysokou istotou.

                PRAVIDLA:
                - Ak text obsahuje frazu "Miesto konania:", pouzi ju ako primarny zdroj pre name, city a street.
                - Priorita zdrojov je: 1) "Miesto konania:" 2) veta s plnym nazvom objektu 3) zvysok textu.
                - Ak vstup uz obsahuje presny nazov objektu, nazov neprepisuj na iny objekt v tej istej obci ani na iny historicky/alternativny objekt.
                - Nazov mozes rozsirif iba vtedy, ak ide zjavne o ten isty objekt (napr. doplnenie oficialnej formy, nie zamena budovy).
                - Venue moze byt akykolvek objekt pre eventy a stretnutia: kulturny dom, centrum, divadlo, kino, klub, kostol, chram, synagoga, muzeum, galeria, kniznica, skola, aula, hotel, restauracia, hala, stadion, arena, amfiteater a podobne.
                - Nikdy nevymyslaj adresu ani PSC.
                - GPS suradnice vrat len pre vseobecne znamy objekt (katedrala, stadion, amfiteater, znamy kulturny dom) a len vtedy, ked lezi v zadanej obci; staci priblizna poloha objektu.
                - Pri objekte, ktory nepoznas (bezny kulturny dom, klub, restauracia), vrat pri suradniciach null - nehadaj podla mena obce.
                - Ak si nie si isty alebo existuje viac moznych miest, nastav hodnotu na null.
                - Mesto extrahuj aj z formatov "..., Sabinov, ..." alebo "v Sabinove"; v takom pripade vrat zakladny tvar mesta "Sabinov".
                - Krajinu vrat iba ak je jasna, inak null.
                - Ak je zjavne, ze ide o miesto na Slovensku, country nastav na "Slovensko".
                - Ulica moze byt aj bez cisla domu.
                - Latitude a longitude vrat iba ako cisla.
                - Vrat iba validny JSON bez komentarov.',
            ],
            [
                'role' => 'user',
                'content' => "Vstupne data:\n{$text}\n\n"
                    . "Vrat JSON objekt s klucmi:\n"
                    . "- name (nazov miesta; zachovaj identitu objektu zo vstupu, neprepisuj ho na iny objekt)\n"
                    . "- street (ulica, aj bez cisla domu)\n"
                    . "- postcode\n"
                    . "- city (mesto/obec v zakladnom tvare)\n"
                    . "- country\n"
                    . "- latitude\n"
                    . "- longitude\n\n"
                    . "Priklad 1 (neznamy objekt - suradnice nehadaj):\n"
                    . "Vstup: Miesto konania: Greckokatolicky chram v Sabinove, Sabinov, Matice Slovenskej\n"
                    . "Vystup:\n"
                    . "{\"name\":\"Greckokatolicky chram v Sabinove\",\"street\":\"Matice Slovenskej\",\"postcode\":null,\"city\":\"Sabinov\",\"country\":\"Slovensko\",\"latitude\":null,\"longitude\":null}\n\n"
                    . "Priklad 2 (vseobecne znamy objekt - priblizne suradnice su v poriadku):\n"
                    . "Vstup: Miesto konania: Bojnicky zamok, Bojnice\n"
                    . "Vystup:\n"
                    . "{\"name\":\"Bojnicky zamok\",\"street\":null,\"postcode\":null,\"city\":\"Bojnice\",\"country\":\"Slovensko\",\"latitude\":48.7794,\"longitude\":18.5786}\n\n"
                    . "Suradnice z prikladu nikdy nepouzi pre ine miesto - platia len pre objekt, ktory naozaj poznas.\n\n"
                    . "Ak udaj nevies spolahlivo urcit, vrat null. Vrat iba validny JSON bez dalsieho textu.",
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'name' => 'sometimes|nullable|string',
            'street' => 'sometimes|nullable|string',
            'postcode' => 'sometimes|nullable|string',
            'city' => 'sometimes|nullable|string',
            'country' => 'sometimes|nullable|string',
            'latitude' => 'sometimes|nullable|numeric',
            'longitude' => 'sometimes|nullable|numeric',
        ];
    }
}
