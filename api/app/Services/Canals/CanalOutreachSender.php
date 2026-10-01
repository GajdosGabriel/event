<?php

namespace App\Services\Canals;

use App\Enums\AdmissionStatus;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Admission;
use App\Models\Canal;
use App\Models\CanalOutreach;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\View;
use App\Notifications\CanalOutreachNotice;
use App\Services\Imports\CollectionCanal;
use App\Services\SystemLog\MailSimulator;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Oslovenie organizátora po akcii (docs/canal-ownership.md, fáza 4).
 *
 * Importovaný kanál, ktorý nikto nespravuje, dostane po skončení svojej akcie
 * jeden e-mail: koľko ľudí akciu na portáli videlo a odkaz na prevzatie
 * profilu (systémová pozvánka — prijme ju ktorýkoľvek účet, fáza 3).
 *
 * Pravidlá (config/canals.php → outreach):
 *  - akcia skončila pred `after_days` až `window_days` dňami,
 *  - kanál je importovaný, neprevzatý, nie zberný, s platným kontaktom,
 *  - kontakt si oslovenie neodhlásil (EmailSuppression),
 *  - kanál nebol oslovený posledných `cooldown_days` dní,
 *  - akcia mala aspoň `min_views` zobrazení.
 *
 * Kým platí `canals.simulate_outreach_mail`, e-mail sa len zapíše do denníka
 * a oslovenie sa eviduje ako `simulated` — ostré oslovenie potom počíta odstup
 * len od skutočne odoslaných.
 */
class CanalOutreachSender
{
    public function __construct(
        private CanalInviter $inviter,
        private CanalClaims $claims,
    ) {}

    /**
     * Kandidáti na oslovenie: [canal, event, email, stats] pre každý kanál
     * s poslednou skončenou akciou v okne.
     *
     * @return Collection<int, array{canal: Canal, event: Event, email: string, stats: array<string, int>}>
     */
    public function candidates(?int $limit = null): Collection
    {
        $config = config('canals.outreach');
        $simulate = $this->simulate();
        $limit ??= (int) $config['limit'];

        $events = Event::query()
            ->with('canal')
            ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Archived->value])
            ->whereRaw('COALESCE(end_at, start_at) BETWEEN ? AND ?', [
                now()->subDays((int) $config['window_days']),
                now()->subDays((int) $config['after_days']),
            ])
            ->whereHas('canal', fn ($q) => $q
                ->where('registration_source', RegistrationSource::IMPORT->value)
                ->whereNull('claimed_at'))
            ->orderByRaw('COALESCE(end_at, start_at) DESC')
            ->get()
            ->unique('canal_id');

        $result = collect();

        foreach ($events as $event) {
            $canal = $event->canal;

            if (! $canal || ! $this->claims->isClaimable($canal) || CollectionCanal::is($canal)) {
                continue;
            }

            $email = $this->contactOf($canal, $event);

            if ($email === null || EmailSuppression::has($email) || $this->contactedRecently($canal, $simulate)) {
                continue;
            }

            $stats = $this->stats($canal, $event);

            if ($stats['event_views'] < (int) $config['min_views']) {
                continue;
            }

            $result->push(compact('canal', 'event', 'email', 'stats'));

            if ($result->count() >= $limit) {
                break;
            }
        }

        return $result;
    }

    /** Pošle (alebo zasimuluje) oslovenie jedného kanála. */
    public function send(Canal $canal, Event $event, string $email, array $stats): CanalOutreach
    {
        $simulate = $this->simulate();
        $invitation = $this->inviter->ensureOwnerInvitation($canal, $email);
        $claimUrl = rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$invitation->token;

        MailSimulator::send(
            [Notification::route('mail', $email)],
            new CanalOutreachNotice($canal, $event, $stats, $claimUrl, $this->unsubscribeUrl($email)),
            $simulate,
            $canal,
        );

        $outreach = CanalOutreach::query()->create([
            'canal_id' => $canal->id,
            'event_id' => $event->id,
            'email' => $email,
            'status' => $simulate ? CanalOutreach::SIMULATED : CanalOutreach::SENT,
            'stats' => $stats,
            'invitation_id' => $invitation->id,
        ]);

        Recorder::info('canals', 'outreach_'.$outreach->status,
            "Kanál {$canal->name}: oslovenie po akcii {$event->name}",
            status: 'ok',
            recipient: $email,
            subject: $canal,
            context: ['outreach_id' => $outreach->id, 'event_id' => $event->id, 'stats' => $stats],
        );

        return $outreach;
    }

    /**
     * Čísla do e-mailu. `views` tabuľka má jeden riadok na návštevníka a deň,
     * takže počet riadkov = zobrazenia, počet hashov = rôzni ľudia.
     *
     * @return array<string, int>
     */
    public function stats(Canal $canal, Event $event): array
    {
        $eventViews = View::query()
            ->where('viewable_type', $event->getMorphClass())
            ->where('viewable_id', $event->id);

        return [
            'event_views' => (clone $eventViews)->count(),
            'event_visitors' => (clone $eventViews)->distinct()->count('visitor_hash'),
            'canal_views' => View::query()
                ->where('viewable_type', $canal->getMorphClass())
                ->where('viewable_id', $canal->id)
                ->where('viewed_on', '>=', now()->subDays(90)->toDateString())
                ->count(),
            'signups' => Admission::query()
                ->where('event_id', $event->id)
                ->where('status', AdmissionStatus::Valid->value)
                ->count(),
        ];
    }

    public function unsubscribeUrl(string $email): string
    {
        return URL::signedRoute('public.email.unsubscribe', ['email' => mb_strtolower($email)]);
    }

    /** Kontakt profilu má prednosť; inak kontakt z podujatia. */
    private function contactOf(Canal $canal, Event $event): ?string
    {
        foreach ([$canal->email, $event->email] as $candidate) {
            $candidate = mb_strtolower(trim((string) $candidate));

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Počas simulácie sa neopakuje ani simulované oslovenie (denník by sa
     * každý deň plnil tým istým); ostré oslovenie počíta len skutočne odoslané.
     */
    private function contactedRecently(Canal $canal, bool $simulate): bool
    {
        return CanalOutreach::query()
            ->where('canal_id', $canal->id)
            ->whereIn('status', $simulate ? [CanalOutreach::SENT, CanalOutreach::SIMULATED] : [CanalOutreach::SENT])
            ->where('created_at', '>=', now()->subDays((int) config('canals.outreach.cooldown_days')))
            ->exists();
    }

    private function simulate(): bool
    {
        return (bool) config('canals.simulate_outreach_mail', true);
    }
}
