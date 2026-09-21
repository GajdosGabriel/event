<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\SystemLog\Recorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Plní denník udalostí (system_logs) z udalostí Laravelu — maily, zlyhané
 * joby, prihlásenia a pády cronu sa tak zapisujú bez zásahu do notifikácií
 * a kontrolerov.
 *
 * „Neodišlo“ má dve cesty:
 *  - frontovaný mail (väčšina) → JobFailed, až keď fronta vzdá všetky pokusy;
 *  - mail posielaný hneď (admin upozornenia, správa kanálu) → výnimka
 *    z transportu, ktorú zachytí bootstrap/app.php (withExceptions) cez mailFailed().
 *    Príjemcu si pamätáme z MessageSending, výnimka ho nenesie.
 */
class SystemLogSubscriber
{
    /** Mail, ktorý sa práve posiela (MessageSending → MessageSent). */
    private static ?array $pending = null;

    /** Beží job z fronty — jeho zlyhania zapíše JobFailed, nie handler výnimiek. */
    private static bool $inQueueJob = false;

    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSending::class => 'mailSending',
            MessageSent::class => 'mailSent',
            JobProcessing::class => fn () => self::$inQueueJob = true,
            JobProcessed::class => fn () => self::$inQueueJob = false,
            Looping::class => fn () => self::$inQueueJob = false,
            JobFailed::class => 'jobFailed',
            ScheduledTaskFailed::class => 'scheduledTaskFailed',
            Login::class => 'login',
            Failed::class => 'loginFailed',
            Lockout::class => 'lockout',
            PasswordReset::class => 'passwordReset',
            Registered::class => 'registered',
        ];
    }

    public function mailSending(MessageSending $event): void
    {
        self::$pending = [
            'recipient' => $this->recipients($event->message),
            'subject' => (string) $event->message->getSubject(),
            'class' => $this->mailClass($event->data),
        ];
    }

    public function mailSent(MessageSent $event): void
    {
        self::$pending = null;

        $class = $this->mailClass($event->data);

        Recorder::info('mail', 'sent', (string) $event->message->getSubject(),
            status: 'sent',
            recipient: $this->recipients($event->message),
            context: [
                'class' => $class,
                'queued' => $event->data['__laravel_notification_queued'] ?? null,
                'message_id' => $event->sent->getMessageId(),
                'cc' => $this->addresses($event->message->getCc()),
                'bcc' => $this->addresses($event->message->getBcc()),
            ],
        );
    }

    /**
     * Volá bootstrap/app.php (withExceptions) pri chybe transportu (SMTP
     * neodpovedá, odmietnutý príjemca…). Vo fronte sa nezapisuje — tam príde
     * JobFailed až po poslednom pokuse a jeden mail by mal inak viac riadkov.
     */
    public static function mailFailed(Throwable $e): void
    {
        $pending = self::$pending;
        self::$pending = null;

        if (self::$inQueueJob) {
            // Výnimka jobu sa hlási až po JobFailed. Pri sync fronte už potom
            // nepríde JobProcessed, takže príznak treba zhodiť tu.
            self::$inQueueJob = false;

            return;
        }

        Recorder::error('mail', 'failed', $pending['subject'] ?? 'Mail sa nepodarilo odoslať',
            status: 'failed',
            recipient: $pending['recipient'] ?? null,
            context: ['class' => $pending['class'] ?? null] + Recorder::exception($e),
        );
    }

    public function jobFailed(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $name = $payload['displayName'] ?? $event->job->resolveName();
        $command = $this->command($payload);
        $exception = Recorder::exception($event->exception);

        if ($command instanceof SendQueuedNotifications) {
            $channel = implode(',', (array) $command->channels) ?: 'mail';
            $notification = get_class($command->notification);

            foreach ($command->notifiables as $notifiable) {
                Recorder::error($channel === 'mail' ? 'mail' : 'queue', 'failed', class_basename($notification),
                    status: 'failed',
                    recipient: $channel === 'mail' ? $this->notifiableEmail($notifiable, $command->notification) : null,
                    userId: $notifiable instanceof User ? $notifiable->getKey() : null,
                    context: ['class' => $notification, 'channel' => $channel, 'job_id' => $event->job->getJobId()] + $exception,
                );
            }

            return;
        }

        if ($command instanceof SendQueuedMailable) {
            $mailable = $command->mailable;

            Recorder::error('mail', 'failed', (string) ($mailable->subject ?: class_basename($mailable)),
                status: 'failed',
                recipient: collect($mailable->to)->pluck('address')->implode(', ') ?: null,
                context: ['class' => get_class($mailable), 'job_id' => $event->job->getJobId()] + $exception,
            );

            return;
        }

        Recorder::error('queue', 'failed', class_basename($name),
            status: 'failed',
            context: ['job' => $name, 'queue' => $event->job->getQueue(), 'job_id' => $event->job->getJobId()] + $exception,
        );
    }

    public function scheduledTaskFailed(ScheduledTaskFailed $event): void
    {
        $task = $event->task;
        $name = $task->description ?: Str::after((string) $task->command, "'artisan' ");

        Recorder::error('scheduler', 'failed', Str::limit($name, 200),
            status: 'failed',
            context: ['command' => $task->command, 'exit_code' => $task->exitCode] + Recorder::exception($event->exception),
        );
    }

    public function login(Login $event): void
    {
        Recorder::info('auth', 'login', 'Prihlásenie',
            status: 'ok',
            recipient: $event->user->email ?? null,
            userId: $event->user->getAuthIdentifier(),
            context: ['remember' => $event->remember ?: null, 'guard' => $event->guard, 'agent' => $this->agent()],
            ip: request()?->ip(),
        );
    }

    /** Heslo sa nikdy neukladá — z údajov sa berie len login. */
    public function loginFailed(Failed $event): void
    {
        Recorder::warning('auth', 'failed', $event->user ? 'Nesprávne heslo' : 'Neznámy účet',
            status: 'failed',
            recipient: $event->credentials['email'] ?? null,
            userId: $event->user?->getAuthIdentifier(),
            context: ['guard' => $event->guard, 'agent' => $this->agent()],
            ip: request()?->ip(),
        );
    }

    public function lockout(Lockout $event): void
    {
        Recorder::warning('auth', 'lockout', 'Priveľa pokusov o prihlásenie — dočasne zablokované',
            status: 'failed',
            recipient: $event->request->input('email'),
            context: ['agent' => $this->agent()],
            ip: $event->request->ip(),
        );
    }

    public function passwordReset(PasswordReset $event): void
    {
        Recorder::info('auth', 'password_reset', 'Heslo zmenené cez obnovu',
            status: 'ok',
            recipient: $event->user->email ?? null,
            userId: $event->user->getAuthIdentifier(),
            ip: request()?->ip(),
        );
    }

    public function registered(Registered $event): void
    {
        Recorder::info('auth', 'registered', 'Nová registrácia',
            status: 'ok',
            recipient: $event->user->email ?? null,
            userId: $event->user->getAuthIdentifier(),
            ip: request()?->ip(),
        );
    }

    /** Príkaz jobu z payloadu. Zmazaný model pri rozbalení nesmie zhodiť zápis. */
    private function command(array $payload): mixed
    {
        $serialized = $payload['data']['command'] ?? null;

        if (! is_string($serialized) || ! str_starts_with($serialized, 'O:')) {
            return null;
        }

        try {
            return unserialize($serialized);
        } catch (Throwable) {
            return null;
        }
    }

    private function notifiableEmail(mixed $notifiable, mixed $notification): ?string
    {
        try {
            $route = method_exists($notifiable, 'routeNotificationFor')
                ? $notifiable->routeNotificationFor('mail', $notification)
                : null;
        } catch (Throwable) {
            $route = null;
        }

        if (is_array($route)) {
            return implode(', ', array_map(
                fn ($key, $value) => is_string($key) ? $key : $value,
                array_keys($route),
                $route
            )) ?: null;
        }

        return is_string($route) ? $route : ($notifiable->email ?? null);
    }

    private function mailClass(array $data): ?string
    {
        return $data['__laravel_notification'] ?? $data['__laravel_mailable'] ?? null;
    }

    private function recipients(Email $message): ?string
    {
        return $this->addresses($message->getTo());
    }

    /** @param  Address[]  $addresses */
    private function addresses(array $addresses): ?string
    {
        return implode(', ', array_map(fn (Address $address) => $address->getAddress(), $addresses)) ?: null;
    }

    private function agent(): ?string
    {
        $agent = request()?->userAgent();

        return $agent ? Str::limit($agent, 180) : null;
    }
}
