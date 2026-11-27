<?php

namespace App\Services\Insights;

use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\CoordinateLookup;
use App\Models\Event;
use App\Models\ProfileEnrichment;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * „Údaje o zázname" na detaile kanála a miesta v administrácii: kedy vznikol,
 * ako sa používa a čo sa s ním dialo — denník udalostí, volania AI a doplnenie
 * údajov v jednej časovej osi. Len čítanie a len pre super-admina.
 */
class RecordInsights
{
    /** Koľko najnovších záznamov z každého zdroja a spolu v časovej osi. */
    private const LIMIT = 50;

    /** @return array{facts: array<string, mixed>, timeline: list<array<string, mixed>>} */
    public function for(Canal|Venue $record): array
    {
        return [
            'facts' => $this->facts($record),
            'timeline' => $this->timeline($record),
        ];
    }

    /** @return array<string, mixed> */
    private function facts(Canal|Venue $record): array
    {
        $now = now();

        $events = $this->events($record)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(COALESCE(end_at, start_at) >= ?), 0) as upcoming', [$now])
            ->selectRaw('MIN(start_at) as first_at')
            ->selectRaw('MAX(start_at) as last_at')
            ->first();

        $ai = $this->aiUsages($record)
            ->toBase()
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(success = 0), 0) as failed')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost_usd')
            ->first();

        $review = $record->contentReview;

        return [
            'createdAt' => $record->created_at?->toIso8601String(),
            'updatedAt' => $record->updated_at?->toIso8601String(),
            'publishedAt' => $this->date($record->getAttribute('published_at')),
            'viewsTotal' => (int) $record->views_count,
            // Staré riadky maže app:views-prune, celkový počet drží views_count.
            'viewsMonth' => $record->views()->where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'eventsTotal' => (int) $events->total,
            'eventsUpcoming' => (int) $events->upcoming,
            'firstEventAt' => $this->date($events->first_at),
            'lastEventAt' => $this->date($events->last_at),
            'filesCount' => $record->files()->count(),
            // Odoberať sa dá len kanál, na mieste sa zas stretávajú kanály.
            'followers' => $record instanceof Canal
                ? Subscription::query()->active()
                    ->where('subscribable_type', $record->getMorphClass())
                    ->where('subscribable_id', $record->id)
                    ->count()
                : null,
            'canalsCount' => $record instanceof Venue ? $record->canals()->count() : null,
            'contentReview' => $review?->reviewed_at
                ? ['score' => $review->score, 'reviewedAt' => $review->reviewed_at->toIso8601String()]
                : null,
            'ai' => [
                'calls' => (int) $ai->calls,
                'failed' => (int) $ai->failed,
                'costUsd' => round((float) $ai->cost_usd, 6),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function timeline(Canal|Venue $record): array
    {
        return collect()
            ->concat($this->logEntries($record))
            ->concat($this->aiEntries($record))
            ->concat($this->lookupEntries($record))
            ->sortByDesc('createdAt')
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function logEntries(Canal|Venue $record): Collection
    {
        // Kanálu patria aj maily na jeho adresy. Pri mieste nie: tú istú
        // adresu často nesie viac miest, pošta by sa pripisovala cudziemu.
        $emails = $record instanceof Canal
            ? $record->emails()->pluck('email')->push(trim((string) $record->email))->filter()->unique()->values()
            : collect();

        return SystemLog::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('subject_type', $record->getMorphClass())->where('subject_id', $record->id))
                ->when($emails->isNotEmpty(), fn ($q) => $q->orWhereIn('recipient', $emails)))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (SystemLog $log) => [
                'id' => 'log-'.$log->id,
                'type' => 'log',
                'createdAt' => $log->created_at?->toIso8601String(),
                'level' => $log->level,
                'event' => $log->event,
                'status' => $log->status,
                'message' => $log->message,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function aiEntries(Canal|Venue $record): Collection
    {
        $usages = $this->aiUsages($record)->latest('id')->limit(self::LIMIT)->get();

        // Pri kanáli idú volania aj za jeho podujatia — bez názvu by desať
        // kontrol obsahu vyzeralo ako desať kontrol toho istého.
        $eventNames = Event::withTrashed()
            ->whereIn('id', $usages->where('subject_type', 'Event')->pluck('subject_id')->filter()->unique())
            ->pluck('name', 'id');

        return $usages->map(fn (AiUsage $usage) => [
            'id' => 'ai-'.$usage->id,
            'type' => 'ai',
            'createdAt' => $usage->created_at?->toIso8601String(),
            'level' => $usage->success ? 'info' : 'error',
            'feature' => $usage->feature,
            'message' => $usage->subject_type === 'Event' ? $eventNames->get($usage->subject_id) : null,
            'model' => $usage->model,
            'costUsd' => round($usage->cost_usd, 6),
        ]);
    }

    /**
     * Posledné doplnenie profilu a hľadanie polohy. Ich tabuľky držia len
     * aktuálny stav, na rozdiel od denníka sa však nepremazávajú.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lookupEntries(Canal|Venue $record): Collection
    {
        $entries = collect();

        $profile = ProfileEnrichment::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->id)
            ->first();

        if ($profile?->completed_at) {
            $entries->push([
                'id' => 'profile-'.$profile->id,
                'type' => 'profile',
                'createdAt' => $profile->completed_at->toIso8601String(),
                'level' => 'info',
                'fields' => array_keys((array) $profile->changes),
            ]);
        }

        $coordinates = CoordinateLookup::for($record);

        if ($coordinates?->looked_up_at) {
            $entries->push([
                'id' => 'coordinates-'.$coordinates->id,
                'type' => 'coordinates',
                'createdAt' => $coordinates->looked_up_at->toIso8601String(),
                'level' => 'info',
                'source' => $coordinates->source,
            ]);
        }

        return $entries;
    }

    /** @return Builder<Event> */
    private function events(Canal|Venue $record): Builder
    {
        return Event::query()->where($record instanceof Canal ? 'canal_id' : 'venue_id', $record->id);
    }

    /** @return Builder<AiUsage> */
    private function aiUsages(Canal|Venue $record): Builder
    {
        // Predmet je v ai_usages ako class_basename (AiUsageRecorder).
        return AiUsage::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('subject_type', class_basename($record))->where('subject_id', $record->id))
            ->when($record instanceof Canal, fn ($q) => $q->orWhere('canal_id', $record->id)));
    }

    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
