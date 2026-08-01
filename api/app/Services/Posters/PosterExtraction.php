<?php

namespace App\Services\Posters;

/**
 * Výsledok čítania nahratého plagátu: čo sa dá poslať do AI ako text a čo ako
 * obrázok. Oboje naraz — textová vrstva býva presnejšia na diakritiku a e-maily,
 * obrázok zase jediný vidí, čo je vysádzané v grafike.
 */
class PosterExtraction
{
    /**
     * @param  string  $text  textová vrstva dokumentu (môže byť prázdna)
     * @param  array<int, string>  $imageDataUrls  `data:image/...;base64,…` na vision
     * @param  string  $kind  pdf | docx | text | image
     * @param  int  $pageCount  počet strán, ak ich dokument má
     */
    public function __construct(
        public readonly string $text,
        public readonly array $imageDataUrls = [],
        public readonly string $kind = 'text',
        public readonly int $pageCount = 1,
        private readonly ?bool $usedVision = null,
    ) {}

    /**
     * Rekonštrukcia z uloženého `analysis.source` — na prepočet reportu pri
     * návrate k rozpracovanému plagátu.
     *
     * Obrázky strán sa neukladajú (base64 by nafúklo riadok v DB o megabajty)
     * a na report ani nie sú potrebné; stačí vedieť, či sa vision použil.
     *
     * @param  array<string, mixed>|null  $source
     */
    public static function fromStoredSource(?array $source, string $text): self
    {
        $source ??= [];

        return new self(
            text: $text,
            kind: (string) ($source['kind'] ?? 'text'),
            pageCount: (int) ($source['page_count'] ?? 1),
            usedVision: (bool) ($source['used_vision'] ?? false),
        );
    }

    /**
     * Textová vrstva je použiteľná až od istej dĺžky. Pod ňou ide zvyčajne
     * o pätičku tlačiarne alebo o zvyšok po neúspešnom OCR a rozhoduje obrázok.
     */
    public function hasUsableText(): bool
    {
        return mb_strlen(trim($this->text)) >= 120;
    }

    public function usesVision(): bool
    {
        return $this->usedVision ?? ($this->imageDataUrls !== []);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'page_count' => $this->pageCount,
            'text_length' => mb_strlen(trim($this->text)),
            'has_text_layer' => $this->hasUsableText(),
            'used_vision' => $this->usesVision(),
        ];
    }
}
