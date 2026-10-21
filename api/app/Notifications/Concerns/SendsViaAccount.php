<?php

namespace App\Notifications\Concerns;

use App\Models\Canal;
use App\Notifications\Channels\AccountMailChannel;
use App\Services\Account\AccountClient;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * E-mail kanálu ide cez Account, keď je to zapnuté (`ACCOUNT_MESSAGING`).
 *
 * Len pre holú adresu (Notification::route) – to je kontakt kanála, od
 * ktorého čakáme odpoveď. Používateľom a adminom chodí pošta naďalej
 * lokálne; ich odpoveď nemá kam patriť.
 */
trait SendsViaAccount
{
    /** @return array<int, string> */
    protected function mailVia(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable && app(AccountClient::class)->messagingEnabled()
            ? [AccountMailChannel::class]
            : ['mail'];
    }

    /** Referencia pre Account – podľa nej príde odpoveď späť ku kanálu. */
    protected function canalRef(Canal $canal): array
    {
        $admin = rtrim((string) config('app.frontend_url'), '/').'/admin/canals/'.$canal->id;

        return [
            'type' => 'canal',
            'id' => (string) $canal->id,
            'label' => 'Kanál: '.$canal->name,
            'url' => $admin,
        ];
    }
}
