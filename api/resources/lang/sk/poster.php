<?php

// Hlášky nahrávania plagátu (App\Http\Requests\Poster*Request).
return [
    'errors' => [
        'input_missing'    => 'Nahrajte plagát alebo vložte text pozvánky.',
        'file_mimes'       => 'Podporujeme PDF, Word (.docx), obrázok plagátu (JPG, PNG, WEBP) alebo textový súbor.',
        'file_max'         => 'Súbor je príliš veľký — maximum je 12 MB.',
        'text_min'         => 'Text je príliš krátky na to, aby sa z neho dalo podujatie zostaviť.',
        'end_before_start' => 'Koniec podujatia nemôže byť skôr než jeho začiatok.',
    ],

    // Čítanie nahraného súboru (App\Services\Posters\PosterTextExtractor).
    // Hláška ide návštevníkovi rovno do formulára, preto je konkrétna.
    'extract' => [
        'empty_text' => 'Text je prázdny.',
        'doc_legacy' => 'Starý formát .doc čítať nevieme. Uložte dokument ako .docx alebo PDF a skúste to znova.',
        'unsupported' => 'Tento formát súboru nepodporujeme. Nahrajte PDF, Word (.docx), obrázok plagátu alebo textový súbor.',
        'image_unreadable' => 'Obrázok sa nepodarilo prečítať.',
        'image_too_large' => 'Obrázok je príliš veľký. Skúste ho zmenšiť pod 12 MB.',
        'pdf_too_large_limit' => 'PDF je pre spracovanie príliš veľké (limit :limit MB). Zmenšite ho alebo nahrajte plagát ako obrázok.',
        'pdf_too_large' => 'PDF je pre spracovanie príliš veľké. Zmenšite ho (napr. exportom v nižšej kvalite) alebo nahrajte plagát ako obrázok.',
        'pdf_failed' => 'PDF sa nepodarilo spracovať. Skúste to o chvíľu znova, alebo nahrajte plagát ako obrázok.',
        'pdf_empty' => 'Z tohto PDF sa nepodarilo prečítať nič. Skúste nahrať plagát ako obrázok (JPG/PNG).',
        'zip_missing' => 'Na serveri chýba rozšírenie ZIP, .docx čítať nevieme.',
        'docx_unreadable' => 'Súbor .docx sa nepodarilo otvoriť — je pravdepodobne poškodený.',
        'docx_no_text' => 'V dokumente .docx sa nenašiel žiadny text.',
        'no_text' => 'Dokument neobsahuje text — údaje sú zrejme v obrázku. Nahrajte plagát ako obrázok alebo PDF.',
    ],

    // Priebeh nahrávania a uloženia rozpracovaného plagátu
    // (App\Http\Controllers\Public\PosterController).
    'draft' => [
        'analyze_failed' => 'Plagát sa nepodarilo spracovať. Skúste to prosím o chvíľu znova.',
        'save_failed' => 'Plagát sme prečítali, ale nepodarilo sa ho uložiť. Skúste to prosím znova.',
        'link_sent' => 'Odkaz na rozpracované podujatie sme poslali na :email.',
        'login_required' => 'Na uloženie podujatia sa musíte prihlásiť.',
        'token_missing' => 'Chýba prístupový token.',
        'not_found' => 'Rozpracovaný plagát sa nenašiel.',
        'expired' => 'Platnosť rozpracovaného plagátu vypršala. Nahrajte ho prosím znova.',
    ],
];
