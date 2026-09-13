<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * „OpenAI: došiel kredit" — pre super-adminov. Posiela OpenAiBillingAlert.
 */
class OpenAiBillingIssue extends Notification implements ShouldQueue
{
    use Queueable;

    private const BILLING_URL = 'https://platform.openai.com/settings/organization/billing/';

    public function __construct(
        protected int $status,
        protected string $code,
        protected string $message,
        protected int $cooldownHours,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('mail.openai_billing.subject'))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.openai_billing.intro'))
            ->line(__('mail.openai_billing.response', ['status' => (string) $this->status, 'code' => $this->code]));

        if (trim($this->message) !== '') {
            $mail->line('**'.trim($this->message).'**');
        }

        return $mail
            ->line(__('mail.openai_billing.impact'))
            ->action(__('mail.openai_billing.action'), self::BILLING_URL)
            ->line(__('mail.openai_billing.cooldown', ['hours' => (string) $this->cooldownHours]));
    }
}
