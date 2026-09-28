<?php

// Hlášky správy tímu kanála (App\Services\Canals\*, DashboardCanalTeamController).
return [
    'last_owner' => 'A canal must have at least one owner.',
    'collection_not_claimable' => 'This canal collects events from various organizers and cannot be taken over.',
    'already_member' => 'This user is already on the canal team.',
    'already_accepted' => 'The invitation has already been accepted.',
    'invitation_invalid' => 'The invitation is invalid or has expired.',
    'email_mismatch' => 'This invitation is for :email. Sign in with an account using that address.',
    'self_role_change' => 'You cannot change your own role in the canal.',
    'self_remove' => 'You cannot remove yourself from the team.',
];
