<?php

// Hlášky kanálov. Referenčný zámok — viď App\Models\Traits\ProtectsReferencedRecords.
return [
    'errors' => [
        'blocked_by_events' => 'Kanál sa nedá zmazať, patrí pod neho :count podujatí.',
        'blocked_by_venues' => 'Kanál sa nedá zmazať, vlastní :count miest.',
        'blocked_by_users' => 'Kanál sa nedá zmazať, má ho nastavený :count používateľov.',
    ],
];
