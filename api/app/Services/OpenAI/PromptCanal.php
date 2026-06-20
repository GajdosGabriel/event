<?php

namespace App\Services\OpenAI;

class PromptCanal
{
    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'canal_schema',
                'schema' => [
                    'type' => 'object',
                    'required' => ['canal_name'],
                    'properties' => [
                        'canal_name' => ['type' => ['string', 'null']],
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
                'content' => 'Si extrakcny asistent pre nazov canalu (organizatora eventu).\n\n'
                    . 'PRAVIDLA:\n'
                    . '- Vrat iba nazov organizatora/eventovej jednotky, bez doplnkovych slov.\n'
                    . '- Ak organizator nie je explicitne uvedeny, ale je jasne uvedene jedine miesto/venue (napr. nazov centra, domu kultury, farnosti), pouzi nazov venue ako canal_name.\n'
                    . '- V tomto systeme je pri takomto type eventu bezne, ze organizator a venue su totozne.\n'
                    . '- Nikdy nevracaj genericke hodnoty ako "vyveska", "výveska", "nezname", "unknown" ani nazov hostitelskeho webu, ak to nie je explicitny organizator v texte.\n'
                    . '- Ak naozaj nevies urcit ani organizatora, ani venue, vrat canal_name = null.\n'
                    . '- Nevymyslaj udaje.',
            ],
            [
                'role' => 'user',
                'content' => "Vstup:\n{$text}\n\n"
                    . "Vrat iba validny JSON s klucom canal_name.\n"
                    . "Ak je organizator nejasny, pouzi nazov venue z textu (ak je jednoznacny).",
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'canal_name' => 'required|nullable|string|max:250',
        ];
    }
}
