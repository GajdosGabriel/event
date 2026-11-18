<?php

return [
    'staff_name' => 'Support',
    'no_context' => 'no channel',

    'status' => [
        'open' => 'Waiting for support',
        'answered' => 'Answered',
        'closed' => 'Closed',
    ],

    'category' => [
        'question' => 'Question',
        'problem' => 'Something is broken',
        'canal' => 'Channel and access',
        'idea' => 'Suggestion',
        'other' => 'Other',
    ],

    'mail' => [
        'action' => 'Open conversation',
        'created' => [
            'subject' => 'New request :reference – :subject',
            'intro' => ':name wrote to support (:category, context: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Support reply :reference – :subject',
            'subject_user' => 'Follow-up :reference – :subject',
            'intro' => 'New message from :name:',
            'intro_staff' => 'Reply from support:',
            'outro' => 'Please do not reply to this e-mail — reply directly in the conversation.',
        ],
    ],
];
