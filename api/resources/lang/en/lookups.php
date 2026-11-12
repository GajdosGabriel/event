<?php

return [
    'blocked' => [
        'profile' => [
            'disabled' => 'Profile enrichment is turned off (AI limits).',
            'not_eligible' => 'Only published and draft organisation channels are enriched.',
            'complete' => 'Nothing is missing from the profile.',
            'budget' => 'The monthly AI search budget is used up.',
        ],
        'coordinates' => [
            'has_coordinates' => 'The channel already has a location.',
        ],
        'email' => [
            'trashed' => 'The channel is deleted or merged.',
            'status' => 'The channel is neither published nor a draft.',
            'personal' => 'Personal channels are not searched for contacts.',
            'collection' => 'A collection channel holds events of other organisers.',
            'verified_email' => 'The channel has a verified address.',
        ],
    ],
];
