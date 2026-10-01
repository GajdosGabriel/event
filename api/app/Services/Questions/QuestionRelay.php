<?php

namespace App\Services\Questions;

use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\User;
use App\Notifications\QuestionRelayed;
use App\Services\Canals\CanalInviter;
use App\Services\SystemLog\MailSimulator;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Otázka na podujatie **nespravovaného** (importovaného) kanála.
 *
 * Nástenku otázok zapína organizátor, no importované podujatie žiadneho
 * organizátora v systéme nemá — nikto ju zapnúť nevie a otázka by nemala kam
 * ísť. Prenos e-mailom to rieši: kanál, ktorý má kontaktnú adresu, dostane
 * otázku do schránky a pod ňou ponuku prevziať profil.
 *
 * Adresa sa **nemusí overovať**: kým sa nevráti ako nedoručiteľná (bounce)
 * alebo sa jej majiteľ neodhlási, nemáme dôvod pokladať ju za zlú. Oba prípady
 * končia v EmailSuppression a addressFor() ich preskočí — spracovanie bounce
 * sa tak na tento prenos napája jediným zápisom (EmailSuppression::add).
 *
 * Nič sa neukladá a nástenka sa nezakladá: verejné otázky by pri kanáli bez
 * správcu nemal kto moderovať. E-mail je jediný nosič, preto sú brzdy prísne:
 * len prihlásený s overeným účtom (jeho adresa ide ako Reply-To), limit na
 * človeka aj na kanál a odhlásené adresy (EmailSuppression) sa preskakujú.
 */
class QuestionRelay
{
    /** Najviac otázok od jedného človeka za hodinu. */
    private const PER_USER_PER_HOUR = 5;

    /** Najviac prenesených otázok na jeden kanál za deň — schránka cudzieho človeka nie je naša. */
    private const PER_CANAL_PER_DAY = 10;

    /** Rovnaký text od rovnakého človeka je dvojklik. */
    private const DUPLICATE_MINUTES = 10;

    public function __construct(private CanalInviter $inviter) {}

    /**
     * Adresa, na ktorú sa otázka smie preposlať — alebo null, keď sa nesmie
     * (kanál má správcu, adresu nemá alebo je odhlásená/nedoručiteľná).
     */
    public function addressFor(Event $event): ?string
    {
        $canal = $event->canal()->first();

        if ($canal === null || $canal->isManaged()) {
            return null;
        }

        $email = mb_strtolower(trim((string) $canal->email));

        if ($email === ''
            || ! filter_var($email, FILTER_VALIDATE_EMAIL) || EmailSuppression::has($email)) {
            return null;
        }

        return $email;
    }

    /** Prepošle otázku organizátorovi a kópiu správcovi. Brzdy zastavia výnimkou 422/429. */
    public function forward(Event $event, User $sender, string $body): void
    {
        $canal = $event->canal()->firstOrFail();
        $email = $this->addressFor($event);

        abort_if($email === null, 422, __('questions.errors.relay_unavailable'));

        $hash = md5($sender->id.'|'.$event->id.'|'.$body);
        abort_unless(Cache::add('question-relay:dup:'.$hash, 1, now()->addMinutes(self::DUPLICATE_MINUTES)),
            422, __('questions.errors.duplicate'));

        $userKey = 'question-relay:user:'.$sender->id;
        $canalKey = 'question-relay:canal:'.$canal->id;

        abort_if(RateLimiter::tooManyAttempts($userKey, self::PER_USER_PER_HOUR)
            || RateLimiter::tooManyAttempts($canalKey, self::PER_CANAL_PER_DAY),
            429, __('questions.errors.relay_limit'));

        RateLimiter::hit($userKey, 3600);
        RateLimiter::hit($canalKey, 86400);

        $invitation = $this->inviter->ensureOwnerInvitation($canal, $email);
        $claimUrl = rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$invitation->token;
        $unsubscribeUrl = URL::signedRoute('public.email.unsubscribe', ['email' => $email]);

        $name = $sender->displayName();
        $notice = fn (string $audience) => new QuestionRelayed(
            $event, $canal, $audience, $body, $name, (string) $sender->email, $claimUrl, $unsubscribeUrl,
        );

        // Organizátor: nevyžiadaný e-mail na scrapnutú adresu, preto ide cez
        // simuláciu, kým sa nepotvrdí právny súlad (config/canals.php).
        MailSimulator::send(
            [Notification::route('mail', $email)],
            $notice(QuestionRelayed::ORGANIZER),
            (bool) config('canals.simulate_question_relay_mail', true),
            $event,
        );

        // Správca portálu vždy naozaj: vidí, čo sa pýta a komu to šlo.
        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notice(QuestionRelayed::ADMIN));
        }

        Recorder::info('canals', 'question_relayed', "Otázka na podujatie {$event->name} odoslaná kanálu {$canal->name}",
            status: 'ok',
            recipient: $email,
            userId: $sender->id,
            subject: $event,
            context: [
                'canal_id' => $canal->id,
                'event_id' => $event->id,
                'delivery' => config('canals.simulate_question_relay_mail', true) ? 'simulated' : 'sent',
                'admins_notified' => $admins->count(),
            ],
        );
    }
}
