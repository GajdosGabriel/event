<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Správa má presne dvoch účastníkov a inbox je osobný — žiadne per-kanálové
 * právo tu nie je čo riešiť. Ani super-admin do cudzieho inboxu nevidí: v
 * dashboarde mu Gate::before() bypass nedáva (viď AuthServiceProvider) a
 * administrátorské rozhranie správy neukazuje.
 */
class MessagePolicy
{
    /**
     * Čítať vlákno smie ktorýkoľvek z dvojice — odosielateľ si tak vie pozrieť
     * odpoveď, ktorá mu prišla, aj s otázkou, na ktorú odpovedá.
     */
    public function view(User $user, Message $message): bool
    {
        return (int) $message->recipient_user_id === (int) $user->id
            || (int) $message->sender_user_id === (int) $user->id;
    }

    /**
     * Prečítané / neprečítané je stav doručenia, takže ho prepína len príjemca.
     */
    public function markRead(User $user, Message $message): bool
    {
        return (int) $message->recipient_user_id === (int) $user->id;
    }

    /**
     * Odpovedať smie len príjemca, a len keď je jeho účet v poriadku
     * (overený e-mail, neblokovaný) — rovnaké anti-spam pravidlo ako pri
     * verejnom „Poslať správu".
     */
    public function reply(User $user, Message $message): bool
    {
        return $this->markRead($user, $message) && $user->canSendMessages();
    }
}
