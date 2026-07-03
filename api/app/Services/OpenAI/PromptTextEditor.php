<?php

namespace App\Services\OpenAI;

class PromptTextEditor
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'text_editor_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['improved_text', 'changes_summary'],
                    'properties' => [
                        'improved_text' => ['type' => 'string'],
                        'changes_summary' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function prompt(string $text, array $modes): array
    {
        $instructions = $this->buildInstructions($modes);

        return [
            [
                'role' => 'system',
                'content' => 'Si odborný editor a copywriter pre slovenské texty o kultúrnych a duchovných podujatiach.
Tvojou úlohou je vylepšiť text podľa zadaných pokynov.
Nikdy nevymýšľaj nové fakty. Zachovaj všetky dátumy, miesta, ceny a kontakty.
Odpovedaj vždy v slovenčine.
Vráť iba validný JSON bez ďalšieho textu.',
            ],
            [
                'role' => 'user',
                'content' => "Pôvodný text:\n{$text}\n\nPokyny na úpravu:\n{$instructions}\n\nV poli changes_summary krátko popiš čo si zmenil (1-2 vety v slovenčine).",
            ],
        ];
    }

    private function buildInstructions(array $modes): string
    {
        $lines = [];

        if (in_array('grammar', $modes, true)) {
            $lines[] = '- Oprav gramatické a pravopisné chyby, interpunkciu a diakritiku.';
        }
        if (in_array('style', $modes, true)) {
            $lines[] = '- Vylepši štylistiku: plynulejšie vety, lepší rytmus, odstrán opakujúce sa slová.';
        }
        if (in_array('expand', $modes, true)) {
            $lines[] = '- Rozšír text: pridaj motivačné a obsahové vysvetlenie, rozdeľ do logických odsekov.';
        }
        if (in_array('html', $modes, true)) {
            $lines[] = '- Naformátuj výstup ako čisté sémantické HTML. Povolené tagy: p, h2, h3, h4, strong, em, ul, ol, li, blockquote, br. Každý odsek obaľ do <p>, nadpisy do <h2>/<h3>, zoznamy do <ul><li>. Nepoužívaj CSS triedy, inline štýly ani žiadne iné atribúty. Nevracaj <html>, <head>, <body> ani <!DOCTYPE>.';
        }

        if (empty($lines)) {
            $lines[] = '- Všeobecne vylepši text: gramatika, štýl, zrozumiteľnosť.';
        }

        return implode("\n", $lines);
    }

    public function validator(): array
    {
        return [
            'improved_text' => 'required|string',
            'changes_summary' => 'required|string',
        ];
    }
}
