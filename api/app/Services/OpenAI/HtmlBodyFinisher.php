<?php

namespace App\Services\OpenAI;

use App\Services\Imports\HtmlBodyCleaner;
use Illuminate\Support\Facades\Log;

/**
 * Posledný krok každého AI prepisu popisu: z výsledku musí vyjsť HTML so
 * štruktúrou, nie jeden zlepený odstavec.
 *
 * Copywriter aj editor („vylepšiť / rozšíriť") majú v prompte napísané, že
 * vracajú HTML, ale model to nie vždy dodrží — a do 31. 8. 2026 sa pri zlyhaní
 * copywritera ukladal rovno plochý extrakt. Na verejnom detaile z toho bola
 * stena textu. Preto sa formátovanie robí až tu, nad hotovým textom: keď je
 * dlhý a nemá nadpisy, zoznamy ani viac odsekov, pošle sa na sadzbu
 * (PromptHtmlFormatter), ktorá text nemení, len ho rozčlení.
 *
 * Výstup vždy prejde HtmlBodyCleaner — `body` sa vykresľuje cez v-html.
 */
class HtmlBodyFinisher
{
    /** Kratší text v jednom odseku je v poriadku — nie je čo členiť. */
    private const MIN_CHARS_FOR_STRUCTURE = 400;

    /**
     * Koľko textu smie sadzba „stratiť" (medzery, zlúčené odrážky). Viac
     * znamená, že model obsah skracoval — taký výsledok sa zahodí.
     */
    private const MIN_KEPT_RATIO = 0.9;

    public function __construct(
        private readonly ChatGPT $chatGPT,
        private readonly HtmlBodyCleaner $cleaner = new HtmlBodyCleaner,
    ) {}

    public function finish(string $text): string
    {
        $html = $this->toSafeHtml($text);

        if (! $this->lacksStructure($html)) {
            return $html;
        }

        $plain = $this->plainText($html);

        try {
            $formatted = $this->chatGPT->formatAsHtml($plain);
        } catch (\Throwable $e) {
            Log::warning('Sadzba popisu do HTML zlyhala.', [
                'text_length' => mb_strlen($plain),
                'error' => $e->getMessage(),
            ]);

            return $html;
        }

        $formatted = $formatted === null ? '' : $this->cleaner->cleanHtmlString($formatted);

        if ($formatted === '' || $this->lacksStructure($formatted)) {
            return $html;
        }

        if (mb_strlen($this->plainText($formatted)) < mb_strlen($plain) * self::MIN_KEPT_RATIO) {
            Log::warning('Sadzba popisu do HTML skrátila text, ostáva pôvodný.', [
                'text_length' => mb_strlen($plain),
                'formatted_length' => mb_strlen($this->plainText($formatted)),
            ]);

            return $html;
        }

        return $formatted;
    }

    /**
     * Dlhý text bez nadpisov, zoznamov a s najviac jedným odsekom.
     */
    public function lacksStructure(string $html): bool
    {
        if (mb_strlen($this->plainText($html)) < self::MIN_CHARS_FOR_STRUCTURE) {
            return false;
        }

        return preg_match('/<(?:h[2-4]|ul|ol)\b/i', $html) !== 1
            && preg_match_all('/<p\b/i', $html) <= 1;
    }

    /**
     * Model niekedy vráti obyčajný text. Rozhoduje prítomnosť skutočného tagu
     * — rovnaké pravidlo ako PosterDraftMaterializer::toSafeHtml().
     */
    private function toSafeHtml(string $text): string
    {
        $hasRealTag = preg_match('/<(?:p|br|div|h[1-6]|ul|ol|li|strong|em|b|i|a|span|blockquote)\b[^>]*>/i', $text) === 1;

        return $hasRealTag
            ? $this->cleaner->cleanHtmlString($text)
            : $this->cleaner->fromPlainText($text);
    }

    /** Text bez tagov, bloky na samostatných riadkoch — tak ho dostane sadzba. */
    private function plainText(string $html): string
    {
        $text = preg_replace('~<br\s*/?>|</(?:p|li|h[1-6]|blockquote)>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s*\n\s*/', "\n", $text) ?? $text);
    }
}
