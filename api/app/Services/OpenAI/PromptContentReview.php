<?php

namespace App\Services\OpenAI;

/**
 * Kontrola už zverejneného obsahu — čo je na ňom zlé a čo s tým.
 *
 * Prompt je zámerne prísny na to, čo smie vzniknúť: model NEPREPISUJE text,
 * len ho posudzuje. Prepis je samostatná operácia (viď PromptTextEditor) a
 * púšťa ju človek vo formulári, keď sa tak rozhodne. Kontrola, ktorá by rovno
 * menila zverejnený text, by bola tichá úprava cudzieho obsahu.
 *
 * Každá výhrada nesie `mode` — ktorý režim panela „Vyplniť pomocou AI" ju vie
 * vyriešiť. Vďaka tomu vedie odkaz z e-mailu rovno do formulára s už
 * zaškrtnutým režimom a človek nemusí hádať, čo má stlačiť.
 */
class PromptContentReview
{
    /** Režimy panela, na ktoré sa výhrada smie odvolať. */
    public const MODES = ['grammar', 'style', 'expand'];

    /** Závažnosti od najmenšej — poradie je aj poradím pri triedení. */
    public const SEVERITIES = ['notice', 'warning'];

    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'content_review_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['score', 'summary', 'issues'],
                    'properties' => [
                        'score' => ['type' => 'integer'],
                        'summary' => ['type' => 'string'],
                        'issues' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['severity', 'mode', 'message', 'quote'],
                                'properties' => [
                                    'severity' => ['type' => 'string', 'enum' => self::SEVERITIES],
                                    'mode' => ['type' => 'string', 'enum' => self::MODES],
                                    'message' => ['type' => 'string'],
                                    // Kúsok pôvodného textu, ktorého sa výhrada
                                    // týka — bez neho je „máte tam preklep"
                                    // nepoužiteľná rada. Prázdny reťazec, keď
                                    // ide o text ako celok (napr. „je krátky").
                                    'quote' => ['type' => 'string'],
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

    /**
     * @param  string  $kind     'event' | 'venue' | 'canal'
     * @param  array<string, string>  $context  ďalšie polia záznamu (názov, obec…)
     */
    public function prompt(string $kind, string $name, string $body, array $context = []): array
    {
        $subject = match ($kind) {
            'venue' => 'miesta konania podujatí',
            'canal' => 'organizátora podujatí',
            default => 'podujatia',
        };

        $contextLines = '';

        foreach ($context as $label => $value) {
            if (filled($value)) {
                $contextLines .= "- {$label}: {$value}\n";
            }
        }

        return [
            [
                'role' => 'system',
                'content' => 'Si korektor slovenských textov na portáli s kultúrnymi a duchovnými podujatiami.
Dostaneš text, ktorý je UŽ ZVEREJNENÝ. Tvojou úlohou je posúdiť ho, NIE prepísať.

ČO HĽADÁŠ (v tomto poradí dôležitosti):
1. Gramatické a pravopisné chyby, chýbajúca diakritika, zlá interpunkcia → severity "warning", mode "grammar".
2. Rozbitá alebo nečitateľná štruktúra: jeden dlhý blok bez odsekov, zvyšky HTML značiek, zlepené slová, text zjavne zoškrabaný z iného webu (pätičky, „cookies", navigácia) → severity "warning", mode "style".
3. Text je príliš stručný na to, aby návštevníkovi odpovedal na otázku „mám tam ísť?" — chýba, o čo ide, pre koho to je, čo sa bude diať → severity "notice", mode "expand".
4. Štylistické drobnosti: opakujúce sa slová, kostrbaté vety, marketingové frázy → severity "notice", mode "style".

PRAVIDLÁ:
- NEVYMÝŠĽAJ chyby. Keď je text v poriadku, vráť prázdne pole issues a vysoké score.
- NEHODNOŤ samotné podujatie, miesto ani organizátora — posudzuješ text, nie zámer.
- NEVYČÍTAJ chýbajúce údaje (dátum, cenu, adresu). Tie sú v iných poliach formulára, nie v popise.
- Neopravuj vlastné mená, názvy obcí, sviatkov ani liturgické pojmy, ktoré nepoznáš — radšej ich nechaj tak.
- Maximálne 5 výhrad. Keď je ich viac, vyber tie najzávažnejšie.
- message píš v slovenčine, jednou vetou, ako radu človeku („Vo vete o programe chýba čiarka pred a to.").
- quote je doslovný úryvok z textu (max 120 znakov), alebo prázdny reťazec pri výhrade k celku.
- score je 0-100: 100 = bez výhrad, pod 60 = text potrebuje zásah.
- Vráť iba validný JSON bez ďalšieho textu.',
            ],
            [
                'role' => 'user',
                'content' => "Posúď popis {$subject}.\n"
                    . "Názov: {$name}\n"
                    . $contextLines
                    . "\nText:\n"
                    . $body,
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'score' => 'required|integer|min:0|max:100',
            'summary' => 'required|string',
            'issues' => 'present|array',
            'issues.*.severity' => 'required|string|in:'.implode(',', self::SEVERITIES),
            'issues.*.mode' => 'required|string|in:'.implode(',', self::MODES),
            'issues.*.message' => 'required|string',
            'issues.*.quote' => 'present|string',
        ];
    }
}
