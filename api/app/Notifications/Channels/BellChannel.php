<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/** In-app delivery also supports existing notifications routed to an email address. */
class BellChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $user = $notifiable instanceof User ? $notifiable : null;
        if ($notifiable instanceof AnonymousNotifiable) {
            $email = $notifiable->routeNotificationFor('mail', $notification);
            if (is_string($email)) {
                $user = User::where('email', $email)->whereNotNull('email_verified_at')->first();
            }
        }
        if (! $user || ! $user->email_verified_at) {
            return;
        }

        $data = $notification->toBell($notifiable);
        $base = rtrim((string) config('app.frontend_url'), '/');
        if ($base !== '' && is_string($data['link'] ?? null) && str_starts_with($data['link'], $base.'/')) {
            $data['link'] = substr($data['link'], strlen($base));
        }

        // Laravel reuses the notification UUID on queue retries: do not duplicate it
        // or turn a previously read item back into an unread one.
        $user->notifications()->firstOrCreate(['id' => $notification->id], [
            'type' => get_class($notification),
            'data' => $data,
        ]);
    }
}
