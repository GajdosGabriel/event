<?php

namespace App\Services\OpenAI;

/**
 * Sadzba hotového textu do HTML — posledný krok po prepise alebo rozšírení.
 *
 * Na rozdiel od copywritera a editora text NEPÍŠE: dostane výsledok, ktorý
 * už prešiel úpravou, a len ho rozčlení na odseky, nadpisy a zoznamy. Preto
 * nulová teplota a zákaz meniť slová — obsah je hotový, chýba mu len tvar.
 */
class PromptHtmlFormatter
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'html_formatter_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['html'],
                    'properties' => [
                        'html' => ['type' => 'string'],
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
                'content' => 'Si sadzač textov o kultúrnych a duchovných podujatiach.
Dostaneš hotový text popisu podujatia. Tvojou jedinou úlohou je naformátovať ho do HTML.
Text NEMEŇ: neprepisuj vety, nič nepridávaj, nič nevynechávaj, neopravuj fakty.
Vráť iba validný JSON bez ďalšieho textu.',
            ],
            [
                'role' => 'user',
                'content' => "Text:\n{$text}\n\nPravidlá:
- Rozdeľ text do logických odsekov <p>.
- Keď má text zjavné časti (program, cena, prihlásenie, kontakt), daj nad ne krátky nadpis <h3> z 1 až 4 slov.
- Harmonogram, program alebo výpočet daj do <ul><li>.
- Dátum, čas, miesto, cenu a kontakt zvýrazni <strong>.
- Povolené tagy: p, h3, strong, em, ul, ol, li, br. Žiadne atribúty, triedy ani štýly.
- Nevracaj <html>, <head>, <body> ani <!DOCTYPE>.",
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'html' => 'required|string',
        ];
    }
}
