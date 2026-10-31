<?php

namespace App\Services\Canals;

use App\Models\Canal;
use App\Models\CanalRelayThread;
use App\Models\Event;
use App\Models\User;
use App\Notifications\CanalRelayReplied;
use App\Notifications\MessageRelayed;
use App\Notifications\QuestionRelayed;
use App\Services\SystemLog\Recorder;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Otázka alebo správa návštevníka pre kanál, ktorý nikto nespravuje.
 *
 * Nespravovaný kanál nemá inbox ani nástenku, a tak jediným nosičom je e-mail
 * na jeho kontakt (CanalTakeover) s ponukou prevziať profil. Text sa neukladá,
 * len väzba na pisateľa pre odpoveď (CanalRelayThread). Brzdy sú prísne: len prihlásený s overeným účtom (kontroluje volajúci),
 * limit na človeka aj na kanál a odmietnutie opakovaného textu.
 */
class CanalRelay
{
    /** Typ referencie konverzácie v Accounte — jedna konverzácia na preposlanie. */
    public const ACCOUNT_REF = 'canal_relay';

    /** Najviac e-mailov od jedného človeka za hodinu. */
    private const PER_USER_PER_HOUR = 5;

    /** Najviac e-mailov na jeden kanál za deň — schránka cudzieho človeka nie je naša. */
    private const PER_CANAL_PER_DAY = 10;

    /** Rovnaký text od rovnakého človeka je dvojklik. */
    private const DUPLICATE_MINUTES = 10;

    public function __construct(private CanalTakeover $takeover) {}

    public function question(Event $event, User $sender, string $body): void
    {
        $canal = $event->canal()->firstOrFail();

        $this->relay($event, $canal, $sender, $body, 'question', fn (string $audience, string $email, string $claimUrl, string $unsubscribeUrl) => new QuestionRelayed(
            $event, $canal, $audience, $body, $sender->displayName(), (string) $sender->email, $claimUrl, $unsubscribeUrl, $email,
        ));
    }

    public function message(Canal|Event $target, User $sender, string $body): void
    {
        $canal = $this->takeover->canalOf($target);
        abort_if($canal === null, 422, __('messages.errors.not_contactable'));

        $this->relay($target, $canal, $sender, $body, 'message', fn (string $audience, string $email, string $claimUrl, string $unsubscribeUrl) => new MessageRelayed(
            $target, $canal, $audience, $body, $sender->displayName(), (string) $sender->email, $claimUrl, $unsubscribeUrl, $email,
        ));
    }

    /**
     * Organizátor odpovedal do konverzácie v Accounte → odpoveď ide pisateľovi.
     * Account webhook opakuje, preto ID správy strážime, aby nešla dvakrát.
     *
     * @param  array<string, mixed>  $message  `message` z webhooku `message.received`
     */
    public function forwardReply(CanalRelayThread $thread, array $message): void
    {
        $reply = trim((string) ($message['reply_text'] ?? $message['text'] ?? ''));
        $from = CanalEmails::normalize($message['from'] ?? null);
        $messageId = (string) ($message['id'] ?? '');

        if ($reply === '' || $from === null
            || ($messageId !== '' && ! Cache::add('canal-relay:reply:'.$messageId, 1, now()->addDays(7)))) {
            return;
        }

        Notification::route('mail', $thread->asker_email)->notify(new CanalRelayReplied(
            $thread, $reply, $from, $message['from_name'] ?? null, ! empty($message['attachments']),
        ));

        $thread->forceFill(['last_reply_at' => now()])->save();

        Recorder::info('canals', 'relay_reply_forwarded', "Odpoveď organizátora preposlaná: {$thread->target_name}",
            status: 'ok',
            recipient: $thread->asker_email,
            subject: $thread->canal,
            context: ['thread_id' => $thread->id, 'kind' => $thread->kind, 'from' => $from],
        );
    }

    /** Brzdy zastavia výnimkou 422/429. */
    private function relay(Model $target, Canal $canal, User $sender, string $body, string $kind, Closure $notice): void
    {
        $errors = $kind === 'question' ? 'questions.errors' : 'messages.errors';
        $email = $this->takeover->addressFor($target);

        abort_if($email === null, 422, __($errors.'.relay_unavailable'));

        $hash = md5($sender->id.'|'.$target->getMorphClass().'|'.$target->getKey().'|'.$body);
        abort_unless(Cache::add('canal-relay:dup:'.$hash, 1, now()->addMinutes(self::DUPLICATE_MINUTES)),
            422, __($errors.'.duplicate'));

        $userKey = 'canal-relay:user:'.$sender->id;
        $canalKey = 'canal-relay:canal:'.$canal->id;

        abort_if(RateLimiter::tooManyAttempts($userKey, self::PER_USER_PER_HOUR)
            || RateLimiter::tooManyAttempts($canalKey, self::PER_CANAL_PER_DAY),
            429, __($errors.'.relay_limit'));

        RateLimiter::hit($userKey, 3600);
        RateLimiter::hit($canalKey, 86400);

        $claimUrl = $this->takeover->claimUrl($this->takeover->invitation($canal, $email));
        $unsubscribeUrl = URL::signedRoute('public.email.unsubscribe', ['email' => $email]);
        $thread = CanalRelayThread::query()->create([
            'canal_id' => $canal->id,
            'kind' => $kind,
            'target_name' => (string) $target->name,
            'asker_name' => $sender->displayName(),
            'asker_email' => (string) $sender->email,
            'organizer_email' => $email,
        ]);
        $build = fn (string $audience) => $notice($audience, $email, $claimUrl, $unsubscribeUrl)->forThread($thread->id);

        $this->takeover->send($email, $build(QuestionRelayed::ORGANIZER), $target);

        // Správca portálu vždy naozaj: vidí, kto píše a komu to šlo.
        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $build(QuestionRelayed::ADMIN));
        }

        $what = $kind === 'question' ? 'otázka návštevníka preposlaná' : 'správa návštevníka preposlaná';
        $simulated = $this->takeover->simulated() ? ' (len simulované, e-mail neodišiel)' : '';

        Recorder::info('canals', $kind.'_relayed', "Kanál {$canal->name} bez správcu: {$what} na kontaktnú adresu{$simulated}",
            status: 'ok',
            recipient: $email,
            userId: $sender->id,
            subject: $target,
            context: [
                'canal_id' => $canal->id,
                'delivery' => $this->takeover->simulated() ? 'simulated' : 'sent',
                'admins_notified' => $admins->count(),
            ],
        );
    }
}
