<?php

return [
    'attributes' => [
        'body' => 'message',
    ],

    'errors' => [
        'login_required' => 'You have to sign in to send a message.',
        'verified_required' => 'Only accounts with a verified e-mail can send messages.',
        'not_contactable' => 'This target cannot be messaged.',
        'relay_unavailable' => 'The message cannot be sent to the organiser right now.',
        'duplicate' => 'You have just sent this message.',
        'relay_limit' => 'Too many messages. Please try again later.',
        'self' => 'You cannot send a message to yourself.',
        'unknown_target' => 'Unknown message target type.',
        'sender_gone' => 'The sender of the message no longer exists, replying is not possible.',
    ],
];
