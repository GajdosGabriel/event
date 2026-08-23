<?php

return [
    'attributes' => [
        'body' => 'question',
        'author_name' => 'name',
        'author_email' => 'e-mail address',
        'answer_body' => 'answer',
    ],

    'status' => [
        'pending' => 'Awaiting approval',
        'published' => 'Published',
        'hidden' => 'Hidden',
    ],

    'visibility' => [
        'public' => 'Public',
        'private' => 'Private',
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
        'private_unavailable' => 'Private questions cannot be sent for this event.',
        'private_needs_account' => 'Only a signed-in attendee can send feedback while the event is running.',
        'private_needs_email' => 'A private question needs an e-mail address — there is nowhere else you could see the answer.',
        'private_not_highlightable' => 'A private question cannot be highlighted — it is not on the wall.',
    ],

    'slide' => [
        'eyebrow' => 'Audience questions',
        'cta' => 'Scan and ask',
    ],
];
