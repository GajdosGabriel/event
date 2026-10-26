<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventQuestionStoreRequest;
use App\Http\Resources\EventQuestionResource;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Services\Tickets\Questionnaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Dotazník k registrácii — otázky podujatia (strava, ubytovanie, alergie…).
 * Vzor ako typy lístkov: autorizácia proti podujatiu (event.view / event.update).
 */
class DashboardEventQuestionController extends Controller
{
    public function index($eventId): AnonymousResourceCollection
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return EventQuestionResource::collection($event->questions()->with('options')->get());
    }

    public function store(EventQuestionStoreRequest $request, $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $question = DB::transaction(function () use ($request, $event) {
            $data = Arr::except($request->validated(), ['options']);
            $data['sort_order'] ??= (int) $event->questions()->max('sort_order') + 1;

            /** @var EventQuestion $question */
            $question = $event->questions()->create($data);
            $this->syncOptions($question, $request->validated('options'));

            return $question;
        });

        return response()->json(new EventQuestionResource($question->load('options')), 201);
    }

    public function update(EventQuestionStoreRequest $request, $eventId, $questionId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        /** @var EventQuestion $question */
        $question = $event->questions()->findOrFail($questionId);

        // Zmena typu by zmenila význam už uložených odpovedí.
        if ($request->filled('type') && $request->input('type') !== $question->type->value && $question->answers()->exists()) {
            abort(422, __('tickets.errors.question_type_locked'));
        }

        DB::transaction(function () use ($request, $question) {
            $question->update(Arr::except($request->validated(), ['options']));
            $this->syncOptions($question, $request->validated('options'));
        });

        return response()->json(new EventQuestionResource($question->fresh('options')));
    }

    public function destroy($eventId, $questionId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        // Soft-delete — odpovede ostanú v histórii objednávok.
        $event->questions()->findOrFail($questionId)->delete();

        return response()->json(['status' => 'ok']);
    }

    /** Nové poradie otázok: `ids` v poradí, v akom sa majú zobraziť. */
    public function reorder(Request $request, $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($event, $ids) {
            foreach (array_values($ids) as $position => $id) {
                $event->questions()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(EventQuestionResource::collection($event->questions()->with('options')->get()));
    }

    /** Súčty odpovedí po možnostiach — koľko obedov, koľko vegetariánskych… */
    public function summary($eventId, Questionnaire $questionnaire): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return response()->json(['data' => $questionnaire->summary($event)]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $options
     */
    private function syncOptions(EventQuestion $question, ?array $options): void
    {
        if ($options === null) {
            return;
        }

        if (! $question->type->hasOptions()) {
            $question->options()->update(['is_active' => false]);

            return;
        }

        $keep = [];
        foreach (array_values($options) as $position => $row) {
            $attributes = [
                'label' => $row['label'],
                'price_amount' => $row['price_amount'] ?? null,
                'capacity' => $row['capacity'] ?? null,
                'is_active' => $row['is_active'] ?? true,
                'sort_order' => $position + 1,
            ];

            $option = ! empty($row['id']) ? $question->options()->find($row['id']) : null;

            if ($option) {
                $option->update($attributes);
            } else {
                $option = $question->options()->create($attributes);
            }

            $keep[] = $option->id;
        }

        // Možnosť, ktorú už niekto zvolil, sa len vypne — inak by odpoveď
        // v zozname prihlásených stratila text. Nepoužitú môžeme zmazať.
        foreach ($question->options()->whereKeyNot($keep)->get() as $option) {
            $option->answers()->exists()
                ? $option->update(['is_active' => false])
                : $option->delete();
        }
    }
}
