<?php

namespace App\Services\Canals;

use App\Enums\CanalNotificationTopic;
use App\Enums\CanalRole;
use App\Models\Canal;
use App\Models\CanalNotificationSetting;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\SystemLog\MailSimulator;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Komu z tímu kanála poslať notifikáciu na danú tému (docs/canal-ownership.md).
 *
 * Doterajší adresát (vlastník, autor podujatia…) ostáva — volajúci ho určí
 * ako doteraz a cez mayNotify() sa len opýta, či si tému nevypol. Ostatní
 * prihlásení členovia dostanú kópiu cez fanOut(). Kým platí
 * `canals.simulate_team_notifications`, sú to noví adresáti, a tak sa im
 * e-mail len zapíše do denníka (MailSimulator).
 */
class CanalRecipients
{
    /**
     * Prihlásení členovia spravovaného kanála, ktorí e-mail prijať môžu.
     * Poradie: silnejšia rola, potom kto je v tíme dlhšie.
     *
     * @return Collection<int, User>
     */
    public function subscribers(Canal $canal, CanalNotificationTopic $topic): Collection
    {
        if (! $canal->isManaged()) {
            return collect();
        }

        $overrides = $this->overrides($canal, $topic);

        return $canal->users()->get()
            ->filter(function (User $member) use ($topic, $overrides) {
                $role = $this->roleOf($member->pivot);

                return $role !== null
                    && ($overrides[$member->id] ?? $topic->defaultFor($role))
                    && $member->canReceiveMessages();
            })
            ->sort(function (User $a, User $b) {
                return [$this->weight($b), (string) $a->pivot->created_at, $a->id]
                    <=> [$this->weight($a), (string) $b->pivot->created_at, $b->id];
            })
            ->values();
    }

    /**
     * Hlavný adresát témy — pri správach ten, komu správa v inboxe patrí.
     * Kým beží simulácia, môže ním byť len vlastník (ako doteraz), aby žiadny
     * nový adresát nedostal skutočný e-mail.
     */
    public function primary(Canal $canal, CanalNotificationTopic $topic): ?User
    {
        return $this->subscribers($canal, $topic)
            ->first(fn (User $user) => ! $this->simulate() || $this->weight($user) === CanalRole::Owner->weight());
    }

    /**
     * Smie doterajší adresát dostať notifikáciu? Nečlen kanála áno (nastavenia
     * sa ho netýkajú), člen len keď tému nemá vypnutú. Vypnutú zapíše do
     * denníka ako `mail.skipped`.
     */
    public function mayNotify(?Canal $canal, User $recipient, CanalNotificationTopic $topic, Notification $notification, ?Model $subject = null): bool
    {
        if ($canal === null) {
            return true;
        }

        // Autor podujatia dostáva e-maily k vlastnému podujatiu vždy —
        // nastavenia tímu riadia len to, čo chodí „za kanál".
        if ($subject instanceof Event && (int) $subject->user_id === (int) $recipient->id) {
            return true;
        }

        $member = $canal->users()->where('users.id', $recipient->id)->first();
        $role = $member ? $this->roleOf($member->pivot) : null;

        if ($role === null) {
            return true;
        }

        $enabled = $this->overrides($canal, $topic)[$recipient->id] ?? $topic->defaultFor($role);

        if (! $enabled) {
            Recorder::info('mail', 'skipped', class_basename($notification),
                status: 'skipped',
                recipient: $recipient->email,
                userId: $recipient->id,
                subject: $subject ?? $canal,
                context: ['class' => get_class($notification), 'reason' => 'muted', 'topic' => $topic->value, 'canal_id' => $canal->id],
            );
        }

        return $enabled;
    }

    /**
     * Kópia notifikácie ďalším prihláseným členom tímu (okrem tých, ktorým už
     * odišla). Vráti, komu išla.
     *
     * @param  iterable<int, User>  $alreadyNotified
     * @return Collection<int, User>
     */
    public function fanOut(?Canal $canal, CanalNotificationTopic $topic, Notification $notification, iterable $alreadyNotified = [], ?Model $subject = null): Collection
    {
        if ($canal === null) {
            return collect();
        }

        $skip = collect($alreadyNotified)->filter()->map(fn (User $user) => (int) $user->id)->all();

        $recipients = $this->subscribers($canal, $topic)
            ->reject(fn (User $user) => in_array((int) $user->id, $skip, true))
            ->values();

        MailSimulator::send($recipients, $notification, $this->simulate(), $subject ?? $canal);

        return $recipients;
    }

    /**
     * Kanál, ktorého tím sa o daný záznam stará: kanál sám, kanál podujatia,
     * alebo spravovaný vlastnícky kanál miesta.
     */
    public function canalOf(?Model $model): ?Canal
    {
        return match (true) {
            $model instanceof Canal => $model,
            $model instanceof Event => $model->canal()->first(),
            $model instanceof Venue => $model->ownerCanals()->get()->first(fn (Canal $canal) => $canal->isManaged()),
            default => null,
        };
    }

    /**
     * Nastavenia celého tímu: [user_id => [topic => bool]].
     *
     * @return array<int, array<string, bool>>
     */
    public function matrix(Canal $canal): array
    {
        $overrides = CanalNotificationSetting::query()->where('canal_id', $canal->id)->get()
            ->groupBy('user_id');

        $matrix = [];

        foreach ($canal->users()->get() as $member) {
            $role = $this->roleOf($member->pivot) ?? CanalRole::Editor;
            $own = $overrides->get($member->id, collect())->keyBy(fn ($row) => $row->topic->value);

            foreach (CanalNotificationTopic::cases() as $topic) {
                $matrix[$member->id][$topic->value] = (bool) ($own->get($topic->value)?->enabled ?? $topic->defaultFor($role));
            }
        }

        return $matrix;
    }

    /**
     * Zapne/vypne tému členovi. Hodnota rovná predvoľbe roly sa neukladá —
     * riadok by potom prežil zmenu roly a tvrdohlavo držal starý stav.
     */
    public function set(Canal $canal, User $member, CanalNotificationTopic $topic, bool $enabled): void
    {
        $pivot = $canal->users()->where('users.id', $member->id)->first()?->pivot;
        $role = $this->roleOf($pivot);

        if ($role === null) {
            return;
        }

        $before = $this->matrix($canal)[$member->id][$topic->value] ?? $topic->defaultFor($role);

        $query = CanalNotificationSetting::query()
            ->where('canal_id', $canal->id)->where('user_id', $member->id)->where('topic', $topic->value);

        if ($enabled === $topic->defaultFor($role)) {
            $query->delete();
        } else {
            CanalNotificationSetting::query()->updateOrCreate(
                ['canal_id' => $canal->id, 'user_id' => $member->id, 'topic' => $topic->value],
                ['enabled' => $enabled],
            );
        }

        if ($before !== $enabled) {
            $actor = Auth::user();

            Recorder::info('canals', 'notifications_changed',
                "Kanál {$canal->name}: {$topic->value} ".($enabled ? 'zapnuté' : 'vypnuté'),
                status: 'ok',
                recipient: $member->email,
                userId: $member->id,
                subject: $canal,
                context: ['topic' => $topic->value, 'enabled' => $enabled, 'actor_id' => $actor instanceof User ? $actor->id : null],
            );
        }
    }

    /** @return array<int, bool> [user_id => enabled] */
    private function overrides(Canal $canal, CanalNotificationTopic $topic): array
    {
        return CanalNotificationSetting::query()
            ->where('canal_id', $canal->id)
            ->where('topic', $topic->value)
            ->pluck('enabled', 'user_id')
            ->map(fn ($enabled) => (bool) $enabled)
            ->all();
    }

    /**
     * Rola z pivotu. `is_owner` má prednosť — tak ako pri owners(); starší
     * zápis mohol nechať `role` na DB defaulte `editor`.
     */
    private function roleOf(?object $pivot): ?CanalRole
    {
        if ($pivot === null) {
            return null;
        }

        return (bool) ($pivot->is_owner ?? false) ? CanalRole::Owner : CanalRole::tryFrom((string) $pivot->role);
    }

    private function weight(User $member): int
    {
        return $this->roleOf($member->pivot)?->weight() ?? 0;
    }

    private function simulate(): bool
    {
        return (bool) config('canals.simulate_team_notifications', true);
    }
}
