<?php

namespace App\Services\OpenAI;

/**
 * Informačný popis organizátora (canal) alebo miesta (venue).
 *
 * Používa sa pri importe, kde by inak vznikol kanál/miesto s prázdnym alebo
 * systémovým textom. Prompt je zámerne konzervatívny: keď model subjekt
 * nepozná, má vrátiť null a volajúci použije neutrálny fallback. Dĺžka je
 * horný limit, nie cieľ — pri málo známom subjekte má model napísať menej,
 * nie dopĺňať vatu.
 */
class PromptProfile
{
    public const KIND_CANAL = 'canal';
    public const KIND_VENUE = 'venue';

    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'profile_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['description'],
                    'properties' => [
                        'description' => ['type' => ['string', 'null']],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function prompt(string $kind, string $name, ?string $context = null): array
    {
        $subject = $kind === self::KIND_VENUE
            ? 'miesto konania podujati (kostol, kulturny dom, divadlo, muzeum, hala, namestie a podobne)'
            : 'organizatora podujati (farnost, zbor, mesto/obec, skola, kulturna institucia, klub, spolok a podobne)';

        $contextLine = $context !== null && $context !== ''
            ? "Doplnujuci kontext: {$context}\n"
            : '';

        return [
            [
                'role' => 'system',
                'content' => 'Si konzervativny asistent, ktory pise vecne informacne popisy v slovencine.

                PRAVIDLA:
                - Pis 4 az 8 viet, spolu maximalne 1200 znakov, v spisovnej slovencine s diakritikou.
                - Dlzka nesmie ist na ukor presnosti: ked o subjekte vies malo, napis radsej kratsi text. Nikdy nedoplnaj vety len preto, aby si sa dostal na pozadovanu dlzku.
                - Pis iba fakty, ktorymi si si isty: typ subjektu, zameranie, obec/mesto, region, historicky kontext, architektura ci patrocinium (pri mieste), cinnost a cielova skupina (pri organizatorovi).
                - Nikdy nevymyslaj kontakty, adresy, webstranky, telefonne cisla, datumy zalozenia, mena osob ani statistiky.
                - Obec, mesto, okres, diecezu ci kraj uved iba vtedy, ak vyplyva z nazvu alebo z doplnujuceho kontextu. Ak poloha nie je dana, o polohe vobec nepis — nehadaj ju.
                - Nepis marketingove frazy ("srdecne vas pozyvame", "jedinecny zazitok") ani hodnotenia.
                - Nespominaj import, scraper, system, databazu ani zdroj dat.
                - Ak subjekt nepoznas alebo je nazov prilis vseobecny ci nejednoznacny, vrat description = null. Radsej null ako vymysleny text.
                - Vrat iba validny JSON bez komentarov.',
            ],
            [
                'role' => 'user',
                'content' => "Napis popis pre {$subject}.\n"
                    . "Nazov: {$name}\n"
                    . $contextLine
                    . "\nVrat JSON objekt s jedinym klucom:\n"
                    . "- description (string s popisom, alebo null ak subjekt spolahlivo nepoznas)\n\n"
                    . 'Priklad: {"description":"Farnost Bela je rimskokatolicka farnost v obci Bela v okrese Zilina. Patri do Zilinskej diecezy a stara sa o duchovny zivot v obci a okolitych osadach. Farnost spravuje miestny farsky kostol a zabezpecuje pravidelne bohosluzby, vysluhovanie sviatosti a vyucbu nabozenstva. Okrem liturgickeho zivota organizuje aj podujatia pre rodiny, mladez a seniorov. Sucastou jej cinnosti su tradicne farske slavnosti a putnicke podujatia, ktore sa viazu na cirkevny rok."}',
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'description' => 'sometimes|nullable|string',
        ];
    }
}
