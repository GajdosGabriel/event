<?php

// Správa používateľov v admine (App\Http\Controllers\Admin\UserController).
// Vlastný účet si admin nemôže upraviť ani zmazať — na to je bežný profil.
return [
    'errors' => [
        'self_update' => 'Nemôžete upraviť vlastný účet.',
        'self_delete' => 'Nemôžete zmazať vlastný účet.',
    ],
    // Názvy polí do chybových hlások validátora (AdminUserUpdateRequest).
    'fields' => [
        'email' => 'e-mail',
        'status' => 'stav',
        'password' => 'heslo',
        'canal_id' => 'osobný kanál',
        'blocked_until' => 'blokovať do',
        'blocked_reason' => 'dôvod blokovania',
    ],
];
