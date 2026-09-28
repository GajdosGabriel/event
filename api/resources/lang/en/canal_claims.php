<?php

// Chyby žiadosti o prevzatie kanála (App\Services\Canals\CanalClaims).
return [
    'not_found' => 'The channel does not exist.',
    'already_managed' => 'This channel is already managed. Ask its owner for a team invitation.',
    'invalid_method' => 'Unknown verification method.',
    'already_requested' => 'You have already requested this channel. Please wait for confirmation.',
    'no_contact' => 'The channel has no contact email. Send the request for review by an administrator.',
    'not_contestable' => 'The objection period has ended or the link is no longer valid.',
    'not_contested' => 'The takeover is not contested.',
    'not_pending' => 'The request is no longer valid.',
    'too_many' => 'You already have :max open channel requests. Please wait until they are reviewed.',
];
