<?php

return [
    'attributes' => [
        'body' => 'question',
        'author_name' => 'name',
        'answer_body' => 'answer',
    ],

    'status' => [
        'pending' => 'Awaiting approval',
        'published' => 'Published',
        'hidden' => 'Hidden',
    ],

    'target' => [
        'event' => 'Event',
        'workshop' => 'Workshop',
    ],

    'errors' => [
        'closed' => 'Questions for this event cannot be submitted right now.',
        'duplicate' => 'You have just sent this question.',
        'too_fast' => 'The form was submitted too quickly. Please try again.',
        'votes_disabled' => 'Voting is turned off on this board.',
        'not_votable' => 'This question cannot be voted on.',
        'unknown_target' => 'Unknown question board target type.',
        'workshop_only' => 'A question board only makes sense on a workshop, not on a regular ticket type.',
        'unknown_variant' => 'Unknown slide format or theme.',
        'rendering_unavailable' => 'The server has no text rendering support (GD FreeType), so the slide cannot be created.',
    ],

    'slide' => [
        'eyebrow' => 'Audience questions',
        'cta' => 'Scan and ask',
    ],
];
