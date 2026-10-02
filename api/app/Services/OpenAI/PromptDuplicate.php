<?php

namespace App\Services\OpenAI;

/**
 * Posúdenie, či dva záznamy (kanál alebo miesto) označujú ten istý subjekt.
 *
 * Prompt je zámerne konzervatívny: zlúčenie dvoch rôznych subjektov je horšie
 * než ponechanie duplikátu (podujatia by skončili pod cudzím organizátorom či
 * v cudzom meste), takže pri pochybnosti má model vrátiť nižšiu istotu.
 */
class PromptDuplicate
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'duplicate_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['same', 'confidence', 'reason'],
                    'properties' => [
                        'same' => ['type' => 'boolean'],
                        'confidence' => ['type' => 'number'],
                        'reason' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  'canal'|'venue'  $kind
     * @param  array<string, string|null>  $a  nový záznam (názov, obec, adresa, web…)
     * @param  array<string, string|null>  $b  existujúci záznam
     */
    public function prompt(string $kind, array $a, array $b): array
    {
        $subject = $kind === 'venue'
            ? 'miesto konania podujati (kostol, kulturny dom, hrad, namestie, hala a podobne)'
            : 'organizatora podujati (farnost, rehola, zbor, mesto/obec, skola, klub, spolok a podobne)';

        $describe = static function (array $fields): string {
            $lines = [];
            foreach ($fields as $label => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $lines[] = '- '.$label.': '.trim($value);
                }
            }

            return implode("\n", $lines);
        };

        return [
            [
                'role' => 'system',
                'content' => 'Si konzervativny asistent, ktory rozhoduje, ci dva zaznamy opisuju ten isty subjekt.

                PRAVIDLA:
                - Porovnavas '.$subject.'.
                - Zhoda znamena rovnaky realny subjekt na rovnakom mieste, nie len podobny nazov. Napriklad "Kostol svateho Jozefa" v Nitre a v Zilina su dva rozne subjekty.
                - Rozny nazov pre ten isty subjekt je v poriadku: preklepy, skratky ("sv." vs "svaty"), pripisana obec ci zatvorka, poradie slov, uvedenie typu ("Farnost X" vs "Rimskokatolicka farnost X").
                - Rozne obce, rozne ulice alebo rozne weby su silny dokaz, ze ide o rozne subjekty.
                - Nadriadena a podriadena organizacia (diecéza vs farnost, rada KBS vs KBS, mesto vs jeho oddelenie kultury) NIE su ten isty subjekt.
                - Ak si nie si isty, vrat same = false alebo nizku istotu. Zle zlucenie je horsie nez ponechany duplikat.
                - confidence je cislo od 0 do 1 (1 = absolutna istota v tvojom rozhodnuti same).
                - reason je jedna kratka veta v slovencine.
                - Vrat iba validny JSON bez komentarov.',
            ],
            [
                'role' => 'user',
                'content' => "NOVY ZAZNAM:\n".$describe($a)."\n\nEXISTUJUCI ZAZNAM:\n".$describe($b)
                    ."\n\nVrat JSON objekt s klucmi same (boolean), confidence (0 az 1), reason (string).",
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'same' => 'required|boolean',
            'confidence' => 'required|numeric|min:0|max:1',
            'reason' => 'sometimes|nullable|string',
        ];
    }
}
