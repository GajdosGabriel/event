<?php

return [
    'errors' => [
        'input_missing'    => 'Laden Sie ein Plakat hoch oder fügen Sie den Text der Einladung ein.',
        'file_mimes'       => 'Wir unterstützen PDF, Word (.docx), ein Bild des Plakats (JPG, PNG, WEBP) oder eine Textdatei.',
        'file_max'         => 'Die Datei ist zu groß — das Maximum sind 12 MB.',
        'text_min'         => 'Der Text ist zu kurz, um daraus eine Veranstaltung zu erstellen.',
        'end_before_start' => 'Das Ende der Veranstaltung kann nicht vor ihrem Beginn liegen.',
    ],

    'extract' => [
        'empty_text' => 'Der Text ist leer.',
        'doc_legacy' => 'Das alte .doc-Format können wir nicht lesen. Speichern Sie das Dokument als .docx oder PDF und versuchen Sie es erneut.',
        'unsupported' => 'Dieses Dateiformat unterstützen wir nicht. Laden Sie ein PDF, Word (.docx), ein Bild des Plakats oder eine Textdatei hoch.',
        'image_unreadable' => 'Das Bild konnte nicht gelesen werden.',
        'image_too_large' => 'Das Bild ist zu groß. Verkleinern Sie es auf unter 12 MB.',
        'pdf_too_large_limit' => 'Das PDF ist für die Verarbeitung zu groß (Limit :limit MB). Verkleinern Sie es oder laden Sie das Plakat als Bild hoch.',
        'pdf_too_large' => 'Das PDF ist für die Verarbeitung zu groß. Verkleinern Sie es (z. B. durch Export in geringerer Qualität) oder laden Sie das Plakat als Bild hoch.',
        'pdf_failed' => 'Das PDF konnte nicht verarbeitet werden. Versuchen Sie es gleich noch einmal oder laden Sie das Plakat als Bild hoch.',
        'pdf_empty' => 'Aus diesem PDF ließ sich nichts lesen. Laden Sie das Plakat als Bild (JPG/PNG) hoch.',
        'zip_missing' => 'Auf dem Server fehlt die ZIP-Erweiterung, .docx können wir nicht lesen.',
        'docx_unreadable' => 'Die .docx-Datei konnte nicht geöffnet werden — sie ist wahrscheinlich beschädigt.',
        'docx_no_text' => 'Im .docx-Dokument wurde kein Text gefunden.',
        'no_text' => 'Das Dokument enthält keinen Text — die Angaben stecken wohl im Bild. Laden Sie das Plakat als Bild oder PDF hoch.',
    ],

    'draft' => [
        'analyze_failed' => 'Das Plakat konnte nicht verarbeitet werden. Bitte versuchen Sie es gleich noch einmal.',
        'save_failed' => 'Wir haben das Plakat gelesen, konnten es aber nicht speichern. Bitte versuchen Sie es erneut.',
        'link_sent' => 'Den Link zur begonnenen Veranstaltung haben wir an :email geschickt.',
        'login_required' => 'Zum Speichern der Veranstaltung müssen Sie sich anmelden.',
        'token_missing' => 'Es fehlt das Zugriffstoken.',
        'not_found' => 'Das begonnene Plakat wurde nicht gefunden.',
        'expired' => 'Das begonnene Plakat ist abgelaufen. Bitte laden Sie es erneut hoch.',
    ],
];
