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
];
