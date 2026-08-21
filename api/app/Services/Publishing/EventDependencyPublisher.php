<?php

namespace App\Services\Publishing;

use App\Enums\ModelStatus;
use App\Exceptions\DependenciesNotPublishedException;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Publikované podujatie musí mať publikované miesto aj kanál.
 *
 * Bez toho ukazuje verejná karta na profil, ktorý sa tvári ako rozrobený —
 * import to robil systematicky (eventy `published`, miesta `draft`) a ručné
 * publikovanie to nekontrolovalo vôbec. Kanál si takto už dopĺňal
 * PosterDraftMaterializer; tu je to isté pravidlo pre obe závislosti a pre
 * všetky cesty, ktorými sa podujatie dostane von.
 */
class EventDependencyPublisher
{
    public function __construct(private readonly RecordPublisher $publisher = new RecordPublisher()) {}

    /**
     * Závislosti, ktoré ešte nie sú publikované.
     *
     * @return array<int, array{type: string, id: int, name: string, status: string, label: string}>
     */
    public function unpublished(Event $event): array
    {
        return $this->unpublishedFor($event->venue_id, $event->canal_id);
    }

    /**
     * To isté, ale nad holými id — pri ukladaní formulára ešte podujatie
     * nemusí mať nový vzťah zapísaný.
     *
     * @return array<int, array{type: string, id: int, name: string, status: string, label: string}>
     */
    public function unpublishedFor(?int $venueId, ?int $canalId): array
    {
        return collect($this->resolve($venueId, $canalId))
            ->filter(fn (array $pair) => $pair['model']->status !== ModelStatus::Published)
            ->map(fn (array $pair) => [
                'type' => $pair['type'],
                'id' => (int) $pair['model']->id,
                'name' => (string) $pair['model']->name,
                'status' => (string) ($pair['model']->status?->value ?? ''),
                'label' => __('events.dependencies.' . $pair['type'], [
                    'name' => '„' . $pair['model']->name . '"',
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * Vyhodí výnimku s ponukou dopublikovať, keď niektorá závislosť visí.
     */
    public function assertPublishable(Event $event): void
    {
        $this->assertPublishableFor($event->venue_id, $event->canal_id);
    }

    public function assertPublishableFor(?int $venueId, ?int $canalId): void
    {
        $unpublished = $this->unpublishedFor($venueId, $canalId);

        if ($unpublished !== []) {
            throw new DependenciesNotPublishedException($unpublished);
        }
    }

    /**
     * Preklopí miesto aj kanál na `published`.
     *
     * `$actor` je človek, ktorý o to požiadal — vtedy sa overuje právo. Import
     * a cron ho nemajú, tam sa publikuje bezpodmienečne: podujatie sa von
     * dostalo systémovým rozhodnutím a jeho profily musia byť otvorené.
     */
    public function publishAll(Event $event, ?User $actor = null): void
    {
        $this->publishAllFor($event->venue_id, $event->canal_id, $actor);
    }

    public function publishAllFor(?int $venueId, ?int $canalId, ?User $actor = null): void
    {
        DB::transaction(function () use ($venueId, $canalId, $actor): void {
            foreach ($this->resolve($venueId, $canalId) as $pair) {
                /** @var Venue|Canal $model */
                $model = $pair['model'];

                if ($model->status === ModelStatus::Published) {
                    continue;
                }

                if ($actor !== null && ! $actor->can('publish', $model)) {
                    abort(403, __('events.errors.dependency_forbidden', [
                        'name' => __('events.dependencies.' . $pair['type'], [
                            'name' => '„' . $model->name . '"',
                        ]),
                    ]));
                }

                $this->publisher->apply($model, true);
            }
        });
    }

    /**
     * @return array<int, array{type: string, model: Model}>
     */
    private function resolve(?int $venueId, ?int $canalId): array
    {
        $pairs = [];

        // Miesto je prvé zámerne — je to častejšia prekážka a vo vete znie
        // prirodzenejšie („miesto X + kanál Y").
        if ($venueId !== null && ($venue = Venue::query()->find($venueId))) {
            $pairs[] = ['type' => 'venue', 'model' => $venue];
        }

        if ($canalId !== null && ($canal = Canal::query()->find($canalId))) {
            $pairs[] = ['type' => 'canal', 'model' => $canal];
        }

        return $pairs;
    }
}
