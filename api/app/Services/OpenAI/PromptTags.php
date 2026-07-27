<?php

namespace App\Services\OpenAI;

/**
 * Priradenie obsahových štítkov podujatiu.
 *
 * Na rozdiel od ostatných promptov dostáva schéma zoznam povolených slugov ako
 * `enum` — model tak fyzicky nemôže vrátiť štítok mimo číselníka. Bez toho by
 * sa číselník do týždňa zaplevil synonymami („koncert" / „koncerty" /
 * „hudobný koncert") a filtre aj odporúčania by prestali dávať zmysel.
 *
 * Aby sa číselník predsa len mal ako rozširovať, model v tej istej odpovedi
 * vráti aj `suggested` — voľné výrazy, ktoré by bol použil, keby smel. Zbierajú
 * sa do tag_suggestions a slúžia ako podklad na doplnenie TagSeeder-a.
 */
class PromptTags
{
    /**
     * @param  array<int, string>  $allowedSlugs
     */
    public function jsonSchema(array $allowedSlugs): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'event_tags_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['tags', 'suggested'],
                    'properties' => [
                        'tags' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['slug', 'confidence'],
                                'properties' => [
                                    'slug' => [
                                        'type' => 'string',
                                        'enum' => array_values($allowedSlugs),
                                    ],
                                    'confidence' => ['type' => 'integer'],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                        'suggested' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, array<int, array{slug: string, name: string}>>  $catalog  facet => štítky
     */
    public function prompt(string $text, array $catalog): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'Si klasifikator podujati. Podujatiu priradis obsahove stitky z pevneho ciselnika.'
                    . "\n\nPRAVIDLA:"
                    . "\n- Vyberaj VYHRADNE slugy zo zoznamu nizsie. Nic ine nie je platna hodnota."
                    . "\n- Priradzuj 2 az 6 stitkov. Vzdy aspon jeden z facetu \"format\" (aky druh podujatia to je)."
                    . "\n- Stitok priradz len vtedy, ked ho text podporuje. Radsej menej stitkov nez hadanie."
                    . "\n- Facety su nezavisle osi: to iste podujatie moze mat sucasne format aj temu aj publikum."
                    . "\n- confidence je cele cislo 0-100. Ked vahas, daj nizsie cislo — stitky pod 70 sa zahadzuju."
                    . "\n- \"pre deti\" nie je to iste ako \"pre rodiny\" — pouzi to, co text naozaj hovori."
                    . "\n\nFACET \"attribute\" (vonku, vstup volny, s registraciou, viacdnove, online):"
                    . "\n- Priradz ho LEN ked to text vyslovne uvadza. Nikdy neodvodzuj z typu podujatia."
                    . "\n- \"online\" znamena, ze sa podujatie kona na dialku cez internet. Podujatie s adresou"
                    . " je fyzicke — \"online\" tam NEPATRI, aj keby bola pozvanka zverejnena na webe."
                    . "\n- \"vstup volny\" daj len ked je vyslovne uvedene, ze vstup je zadarmo. Nepredpokladaj to"
                    . " podla toho, ze cena nie je spomenuta."
                    . "\n- \"viacdnove\" len ked z terminu vyplyva viac nez jeden den."
                    . "\n\nPOLE suggested:"
                    . "\n- Az 3 vyrazy, ktore by si bol pouzil, keby neboli obmedzene ciselnikom, a ktore v nom chybaju."
                    . "\n- Male pismena, jedno az dve slova, slovensky, v zakladnom tvare (napr. \"hasicska sutaz\")."
                    . "\n- Ked ciselnik podujatie pokryva dostatocne, vrat prazdne pole.",
            ],
            [
                'role' => 'user',
                'content' => "CISELNIK STITKOV:\n"
                    . $this->formatCatalog($catalog)
                    . "\n\nPODUJATIE:\n{$text}\n\n"
                    . 'Vrat validny JSON s klucmi "tags" (pole objektov slug + confidence) a "suggested" (pole retazcov).',
            ],
        ];
    }

    public function validator(): array
    {
        // Zamerne volny — rozsahy a pocty riesi EventTagger orezanim, nie
        // zamietnutim celej odpovede. Jeden nezmyselny confidence nema zahodit
        // aj zvysok korektne urcenych stitkov.
        return [
            'tags' => 'present|array',
            'tags.*.slug' => 'required|string',
            'tags.*.confidence' => 'required|numeric',
            'suggested' => 'present|array',
            'suggested.*' => 'string',
        ];
    }

    /**
     * @param  array<string, array<int, array{slug: string, name: string}>>  $catalog
     */
    private function formatCatalog(array $catalog): string
    {
        $lines = [];

        foreach ($catalog as $group => $tags) {
            $items = array_map(
                static fn (array $tag) => $tag['slug'] . ' = ' . $tag['name'],
                $tags,
            );

            $lines[] = strtoupper($group) . ': ' . implode(' | ', $items);
        }

        return implode("\n", $lines);
    }
}
