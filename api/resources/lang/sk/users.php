<?php

// Správa používateľov v admine (App\Http\Controllers\Admin\UserController).
// Vlastný účet si admin nemôže upraviť ani zmazať — na to je bežný profil.
return [
    'errors' => [
        'self_update' => 'Nemôžete upraviť vlastný účet.',
        'self_delete' => 'Nemôžete zmazať vlastný účet.',
        'impersonate_self' => 'Do vlastného účtu sa prihlásiť nedá.',
        'impersonate_admin' => 'Do účtu iného super-admina sa prihlásiť nedá.',
        'impersonate_nested' => 'Najprv sa vráťte do svojho účtu.',
        'impersonate_inactive' => 'Nie ste prihlásený v cudzom účte.',
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
