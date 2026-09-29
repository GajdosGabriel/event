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
            'subject' => 'Support :reference: :subject',
            'intro' => ':name wrote to support (:category, context: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Support replied — :reference :subject',
            'subject_user' => 'Follow-up on :reference: :subject',
            'intro' => ':name wrote:',
            'outro' => 'Please do not reply to this e-mail — reply directly in the conversation.',
        ],
    ],
];
