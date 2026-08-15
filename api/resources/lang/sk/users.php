<?php

// Správa používateľov v admine (App\Http\Controllers\Admin\UserController).
// Vlastný účet si admin nemôže upraviť ani zmazať — na to je bežný profil.
return [
    'errors' => [
        'self_update' => 'Nemôžete upraviť vlastný účet.',
        'self_delete' => 'Nemôžete zmazať vlastný účet.',
    ],
];
