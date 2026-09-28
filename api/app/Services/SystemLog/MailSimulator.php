<?php

namespace App\Services\SystemLog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pošle notifikáciu, alebo ju len zapíše do denníka ako `mail.simulated`.
 *
 * Slúži na nové e-maily, ktoré ešte nechceme púšťať ľuďom: e-mail sa naozaj
 * vyskladá (toMail), takže chyba v texte či chýbajúci preklad sa ukáže hneď,
 * ale neodíde — v denníku ostane adresát, predmet, riadky a odkaz. Prepnutím
 * konfigurácie (napr. `canals.simulate_ownership_mail`) sa začne posielať.
 */
class MailSimulator
{
    /**
     * @param  iterable<int, object>  $notifiables  User alebo AnonymousNotifiable (Notification::route)
     */
    public static function send(iterable $notifiables, Notification $notification, bool $simulate, ?Model $subject = null): void
    {
        foreach ($notifiables as $notifiable) {
            try {
                $simulate
                    ? static::record($notifiable, $notification, $subject)
                    : NotificationFacade::send($notifiable, $notification);
            } catch (Throwable $e) {
                // Oznámenie je vedľajší efekt — nesmie zhodiť zmenu, o ktorej informuje.
                Log::warning('MailSimulator: notification failed.', ['error' => $e->getMessage()]);
                Recorder::error('mail', 'failed', class_basename($notification),
                    status: 'failed',
                    recipient: static::address($notifiable),
                    userId: $notifiable instanceof User ? $notifiable->getKey() : null,
                    subject: $subject,
                    context: ['class' => get_class($notification)] + Recorder::exception($e),
                );
            }
        }
    }

    private static function record(object $notifiable, Notification $notification, ?Model $subject): void
    {
        /** @var MailMessage $mail */
        $mail = $notification->toMail($notifiable);

        Recorder::info('mail', 'simulated', (string) $mail->subject,
            status: 'simulated',
            recipient: static::address($notifiable),
            userId: $notifiable instanceof User ? $notifiable->getKey() : null,
            subject: $subject,
            context: [
                'class' => get_class($notification),
                'lines' => array_merge($mail->introLines, $mail->outroLines),
                'action' => $mail->actionUrl,
            ],
        );
    }

    private static function address(object $notifiable): ?string
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return $notifiable->routes['mail'] ?? null;
        }

        return $notifiable->email ?? null;
    }
}
