<?php

namespace App\Services\Canals;

use App\Enums\AdmissionStatus;
use App\Enums\MailingTrigger;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Jobs\ReportCanalOutreach;
use App\Models\Admission;
use App\Models\Canal;
use App\Models\CanalOutreach;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\View;
use App\Services\Account\AccountClient;
use App\Services\Imports\CollectionCanal;
use App\Services\SystemLog\Recorder;
use App\Support\EventDateRange;
use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ponuka prevzatia importovaného kanála (docs/canal-ownership.md, fázy 4 a 5).
 *
 * Kanál, ktorý nikto nespravuje, dostane postupnosť e-mailov s odkazom na
 * prevzatie profilu (systémová pozvánka — prijme ju ktorýkoľvek účet, fáza 3).
 * Triggery v poradí priority (MailingTrigger::Canal*):
 *  - `canal_event_upcoming`  akcia začína najneskôr o `upcoming_days` dní,
 *  - `canal_views_milestone` akcia pred koncom dosiahla prah `view_milestones`,
 *  - `canal_event_finished`  akcia skončila pred `after_days` až `window_days`
 *    dňami a mala aspoň `min_views` zobrazení.
 *
 * Spoločné pravidlá (config/canals.php → outreach):
 *  - kanál je importovaný, neprevzatý, nie zberný, s platným kontaktom,
 *  - kontakt si oslovenie neodhlásil a nie je nedoručiteľný (EmailSuppression),
 *  - kanál dostal za `cooldown_days` najviac `max_per_cooldown` oslovení,
 *    posledné aspoň pred `min_gap_days` dňami; jeden trigger k akcii len raz.
 *
 * Doručenie ide len cez Account Emailing (ReportCanalOutreach) — text
 * a oneskorenie sú v kampani; kým kampaň nie je aktívna, nič neodíde
 * (`not_queued`, nahlási sa znova). Bez ACCOUNT_MAILING sa neoslovuje.
 */
class CanalOutreachSender
{
    public function __construct(
        private CanalInviter $inviter,
        private CanalClaims $claims,
        private AccountClient $account,
    ) {}

    /**
     * Kandidáti na oslovenie — najviac jeden na kanál.
     *
     * @return Collection<int, array{trigger: MailingTrigger, canal: Canal, event: Event, email: string, stats: array<string, int>, milestone: ?int, dedupe_key: string}>
     */
    public function candidates(?int $limit = null, ?MailingTrigger $only = null): Collection
    {
        $limit ??= (int) config('canals.outreach.limit');
        $result = collect();

        foreach ($this->triggers() as $trigger) {
            if ($only !== null && $only !== $trigger) {
                continue;
            }

            foreach ($this->eventsFor($trigger) as $event) {
                if ($result->count() >= $limit) {
                    return $result;
                }

                if ($result->contains(fn ($c) => $c['canal']->id === $event->canal_id)) {
                    continue;
                }

                if ($candidate = $this->candidate($trigger, $event)) {
                    $result->push($candidate);
                }
            }
        }

        return $result;
    }

    /**
     * Nahlási oslovenie jedného kandidáta do Account Emailingu.
     *
     * @param  array{trigger: MailingTrigger, canal: Canal, event: Event, email: string, stats: array<string, int>, milestone: ?int, dedupe_key: string}  $c
     */
    public function send(array $c): CanalOutreach
    {
        return $this->report($c);
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

    /** Hlási sa do Account Emailingu? */
    public function viaAccount(): bool
    {
        return $this->account->mailingEnabled();
    }

    /** @return array<int, MailingTrigger> */
    public function triggers(): array
    {
        return [MailingTrigger::CanalEventUpcoming, MailingTrigger::CanalViewsMilestone, MailingTrigger::CanalEventFinished];
    }

    /* ---------------------------------------------------------------
     | Výber
     |---------------------------------------------------------------*/

    /** @return Collection<int, Event> */
    private function eventsFor(MailingTrigger $trigger): Collection
    {
        $config = config('canals.outreach');

        return match ($trigger) {
            MailingTrigger::CanalEventUpcoming => $this->events()
                ->where('status', ModelStatus::Published->value)
                ->whereBetween('start_at', [now(), now()->addDays((int) $config['upcoming_days'])])
                ->orderBy('start_at')
                ->get(),

            MailingTrigger::CanalViewsMilestone => $this->milestoneEvents(),

            default => $this->events()
                ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Archived->value])
                ->whereRaw('COALESCE(end_at, start_at) BETWEEN ? AND ?', [
                    now()->subDays((int) $config['window_days']),
                    now()->subDays((int) $config['after_days']),
                ])
                ->orderByRaw('COALESCE(end_at, start_at) DESC')
                ->get()
                ->unique('canal_id'),
        };
    }

    /** Akcie importovaných neprevzatých kanálov. */
    private function events(): Builder
    {
        return Event::query()
            ->with(['canal', 'venue'])
            ->whereHas('canal', fn ($q) => $q
                ->where('registration_source', RegistrationSource::IMPORT->value)
                ->whereNull('claimed_at'));
    }

    /**
     * Zverejnené akcie pred koncom, ktoré majú aspoň najnižší prah zobrazení.
     * Zobrazenia sa rátajú jedným zoskupeným dotazom, nie pre každú akciu.
     *
     * @return Collection<int, Event>
     */
    private function milestoneEvents(): Collection
    {
        $milestones = $this->milestones();

        if ($milestones === []) {
            return collect();
        }

        $ids = $this->events()
            ->where('status', ModelStatus::Published->value)
            ->whereRaw('COALESCE(end_at, start_at) >= ?', [now()])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $views = View::query()
            ->where('viewable_type', (new Event)->getMorphClass())
            ->whereIn('viewable_id', $ids)
            ->groupBy('viewable_id')
            ->havingRaw('COUNT(*) >= ?', [min($milestones)])
            ->selectRaw('viewable_id, COUNT(*) as total')
            ->orderByDesc('total')
            ->pluck('total', 'viewable_id');

        return $this->events()->whereIn('id', $views->keys())->get()
            ->sortByDesc(fn (Event $event) => $views[$event->id])
            ->values();
    }

    /** @return array{trigger: MailingTrigger, canal: Canal, event: Event, email: string, stats: array<string, int>, milestone: ?int, dedupe_key: string}|null */
    private function candidate(MailingTrigger $trigger, Event $event): ?array
    {
        $canal = $event->canal;

        if (! $canal || ! $this->claims->isClaimable($canal) || CollectionCanal::is($canal)) {
            return null;
        }

        $email = $this->contactOf($canal, $event);

        if ($email === null || EmailSuppression::has($email) || ! $this->mayContact($canal)) {
            return null;
        }

        $stats = $this->stats($canal, $event);
        $milestone = null;

        if ($trigger === MailingTrigger::CanalViewsMilestone) {
            $milestone = collect($this->milestones())->filter(fn ($m) => $stats['event_views'] >= $m)->max();

            if ($milestone === null) {
                return null;
            }
        }

        if ($trigger === MailingTrigger::CanalEventFinished && $stats['event_views'] < (int) config('canals.outreach.min_views')) {
            return null;
        }

        $dedupeKey = implode(':', array_filter(['canal', $canal->id, $trigger->value, $event->id, $milestone]));

        if ($this->alreadyDone($dedupeKey)) {
            return null;
        }

        return [
            'trigger' => $trigger,
            'canal' => $canal,
            'event' => $event,
            'email' => $email,
            'stats' => $stats,
            'milestone' => $milestone,
            'dedupe_key' => $dedupeKey,
        ];
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
     * Strop postupnosti: najviac `max_per_cooldown` oslovení za `cooldown_days`
     * a medzi dvoma aspoň `min_gap_days`.
     */
    private function mayContact(Canal $canal): bool
    {
        $config = config('canals.outreach');

        $recent = CanalOutreach::query()
            ->where('canal_id', $canal->id)
            ->whereIn('status', CanalOutreach::COUNTED)
            ->where('created_at', '>=', now()->subDays((int) $config['cooldown_days']));

        if ((clone $recent)->count() >= max(1, (int) $config['max_per_cooldown'])) {
            return false;
        }

        $last = (clone $recent)->max('created_at');

        return $last === null || now()->subDays((int) $config['min_gap_days'])->gte($last);
    }

    /** Ten istý trigger k tej istej akcii už bol — okrem udalosti, ktorú žiadna kampaň nezaradila. */
    private function alreadyDone(string $dedupeKey): bool
    {
        return CanalOutreach::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('status', '!=', CanalOutreach::NOT_QUEUED)
            ->exists();
    }

    /** @return array<int, int> */
    private function milestones(): array
    {
        return array_values(array_filter((array) config('canals.outreach.view_milestones'), fn ($m) => (int) $m > 0));
    }

    /* ---------------------------------------------------------------
     | Doručenie
     |---------------------------------------------------------------*/

    /** @param  array<string, mixed>  $c */
    private function report(array $c): CanalOutreach
    {
        /** @var Canal $canal */
        $canal = $c['canal'];
        /** @var Event $event */
        $event = $c['event'];
        /** @var MailingTrigger $trigger */
        $trigger = $c['trigger'];

        $invitation = $this->inviter->ensureOwnerInvitation($canal, $c['email'], (int) config('canals.outreach.invitation_days'));

        $outreach = CanalOutreach::query()->updateOrCreate(['dedupe_key' => $c['dedupe_key']], [
            'canal_id' => $canal->id,
            'event_id' => $event->id,
            'trigger' => $trigger->value,
            'email' => $c['email'],
            'status' => CanalOutreach::REPORTED,
            'account_queued' => null,
            'stats' => $c['stats'],
            'invitation_id' => $invitation->id,
        ]);

        $start = $event->start_at;

        ReportCanalOutreach::dispatch(
            $outreach->id,
            [
                'email' => $c['email'],
                'name' => $canal->name,
                'tags' => ['canal_contact'],
                'attributes' => ['kanal' => $canal->name],
                'permission_note' => 'Verejný kontakt organizátora podujatí (import)',
                'permission_at' => ($canal->created_at ?? now())->format(DATE_ATOM),
            ],
            $trigger->value,
            [
                'type' => 'canal',
                'id' => (string) $canal->id,
                'label' => 'Kanál: '.$canal->name,
                'url' => rtrim((string) config('app.frontend_url'), '/').'/admin/canals/'.$canal->id,
            ],
            [
                'kanal' => $canal->name,
                'podujatie' => $event->name,
                'termin' => EventDateRange::label($event->start_at, $event->end_at),
                'miesto' => $event->venue?->name,
                'odkaz_podujatia' => PublicUrl::event($event),
                'odkaz_kanala' => PublicUrl::canal($canal),
                'odkaz_prevzatia' => $this->claimUrl($invitation->token),
                'zobrazenia' => $c['stats']['event_views'],
                'ludia' => $c['stats']['event_visitors'],
                'prihlaseni' => $c['stats']['signups'],
                'dni_do_zaciatku' => $start && $start->isFuture() ? (int) today()->diffInDays($start->copy()->startOfDay()) : null,
                'prah_zobrazeni' => $c['milestone'],
            ],
            $c['dedupe_key'],
        )->afterCommit();

        $this->log($outreach, $canal, $event);

        return $outreach;
    }

    private function log(CanalOutreach $outreach, Canal $canal, Event $event): void
    {
        $trigger = MailingTrigger::tryFrom($outreach->trigger)?->label() ?? $outreach->trigger;

        Recorder::info('canals', 'outreach_'.$outreach->status,
            "Kanál {$canal->name}: ponuka prevzatia k akcii {$event->name} odovzdaná e-mailingu v Accounte, e-mail ešte neodišiel ({$trigger})",
            status: 'ok',
            recipient: $outreach->email,
            subject: $canal,
            context: ['outreach_id' => $outreach->id, 'event_id' => $event->id, 'trigger' => $outreach->trigger, 'stats' => $outreach->stats],
        );
    }

    private function claimUrl(string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$token;
    }
}
