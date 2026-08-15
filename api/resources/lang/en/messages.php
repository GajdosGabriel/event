<?php

return [
    'attributes' => [
        'body' => 'message',
    ],

    'errors' => [
        'login_required' => 'You have to sign in to send a message.',
        'verified_required' => 'Only accounts with a verified e-mail can send messages.',
        'not_contactable' => 'This target cannot be messaged.',
        'self' => 'You cannot send a message to yourself.',
        'unknown_target' => 'Unknown message target type.',
        'sender_gone' => 'The sender of the message no longer exists, replying is not possible.',
    ],
];
