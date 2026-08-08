<?php

// Hlášky správ (App\Http\Requests\MessageStoreRequest). Pole `body` sa tu volá
// inak než vo validation.attributes — tam je `popis` podujatia, tu `správa`.
return [
    'attributes' => [
        'body' => 'správa',
    ],
];
