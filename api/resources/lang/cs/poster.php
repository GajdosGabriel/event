<?php

return [
    'errors' => [
        'input_missing'    => 'Nahrajte plakát nebo vložte text pozvánky.',
        'file_mimes'       => 'Podporujeme PDF, Word (.docx), obrázek plakátu (JPG, PNG, WEBP) nebo textový soubor.',
        'file_max'         => 'Soubor je příliš velký — maximum je 12 MB.',
        'text_min'         => 'Text je příliš krátký na to, aby se z něj dala akce sestavit.',
        'end_before_start' => 'Konec akce nemůže být dříve než její začátek.',
    ],

    'extract' => [
        'empty_text' => 'Text je prázdný.',
        'doc_legacy' => 'Starý formát .doc číst neumíme. Uložte dokument jako .docx nebo PDF a zkuste to znovu.',
        'unsupported' => 'Tento formát souboru nepodporujeme. Nahrajte PDF, Word (.docx), obrázek plakátu nebo textový soubor.',
        'image_unreadable' => 'Obrázek se nepodařilo přečíst.',
        'image_too_large' => 'Obrázek je příliš velký. Zkuste ho zmenšit pod 12 MB.',
        'pdf_too_large_limit' => 'PDF je pro zpracování příliš velké (limit :limit MB). Zmenšete ho nebo nahrajte plakát jako obrázek.',
        'pdf_too_large' => 'PDF je pro zpracování příliš velké. Zmenšete ho (např. exportem v nižší kvalitě) nebo nahrajte plakát jako obrázek.',
        'pdf_failed' => 'PDF se nepodařilo zpracovat. Zkuste to za chvíli znovu, nebo nahrajte plakát jako obrázek.',
        'pdf_empty' => 'Z tohoto PDF se nepodařilo přečíst nic. Zkuste nahrát plakát jako obrázek (JPG/PNG).',
        'zip_missing' => 'Na serveru chybí rozšíření ZIP, .docx číst neumíme.',
        'docx_unreadable' => 'Soubor .docx se nepodařilo otevřít — je pravděpodobně poškozený.',
        'docx_no_text' => 'V dokumentu .docx se nenašel žádný text.',
        'no_text' => 'Dokument neobsahuje text — údaje jsou zřejmě v obrázku. Nahrajte plakát jako obrázek nebo PDF.',
    ],

    'draft' => [
        'analyze_failed' => 'Plakát se nepodařilo zpracovat. Zkuste to prosím za chvíli znovu.',
        'save_failed' => 'Plakát jsme přečetli, ale nepodařilo se ho uložit. Zkuste to prosím znovu.',
        'link_sent' => 'Odkaz na rozpracovanou akci jsme poslali na :email.',
        'login_required' => 'Pro uložení akce se musíte přihlásit.',
        'token_missing' => 'Chybí přístupový token.',
        'not_found' => 'Rozpracovaný plakát se nenašel.',
        'expired' => 'Platnost rozpracovaného plakátu vypršela. Nahrajte ho prosím znovu.',
    ],
];
