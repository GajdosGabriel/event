<?php

// Hlášky miest (App\Http\Requests\Venue*Request).
return [
    'errors' => [
        'canal_not_accessible' => 'Vybraný kanál pre vás nie je dostupný.',
        // Referenčný zámok — viď App\Models\Traits\ProtectsReferencedRecords.
        'blocked_by_events' => 'Miesto sa nedá zmazať, používa ho :count podujatí.',
        'unpublish_blocked_by_events' => 'Miesto sa nedá stiahnuť z výpisu, používa ho :count podujatí.',
    ],
];
