<?php

namespace App\Services\Questions;

/**
 * Čo sa má na snímke objaviť. Všetko už preložené a naformátované — renderer
 * nerieši jazyk ani dátumy, len kreslí.
 */
final readonly class SlideContent
{
    public function __construct(
        /** Malý nadpis nad názvom — „Otázky z publika". */
        public string $eyebrow,
        /** Názov workshopu alebo podujatia. */
        public string $title,
        /** Názov podujatia, keď je nástenka na workshope. */
        public ?string $subtitle,
        /** „Streda 17. 8. 2026 18:00". */
        public ?string $when,
        /** „Stará tržnica, Bratislava". */
        public ?string $where,
        /** Názov kanála — dopĺňa monogram v odznaku. */
        public ?string $organizer,
        /**
         * Adresa tak, ako sa prepisuje z plátna do telefónu — bez schémy a
         * s pomlčkou v kóde: `event.sk/q/A7K2M-9QXBF`. Pomlčka je len pre oko,
         * BoardToken::normalize() ju pri príchode zahodí.
         */
        public string $url,
        /** Adresa do QR kódu — kanonická, celá aj so schémou a bez pomlčky. */
        public string $qrUrl,
        /** „Naskenujte a opýtajte sa". */
        public string $cta,
        /** Bajty fotky kanála, alebo null — vtedy sa kreslí monogram. */
        public ?string $photo = null,
    ) {
    }
}
