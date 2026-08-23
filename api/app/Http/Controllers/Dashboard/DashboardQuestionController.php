<?php

namespace App\Http\Controllers\Dashboard;

use App\Contracts\HasQuestionBoard;
use App\Enums\QuestionStatus;
use App\Enums\TicketTypeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionBoardSettingsRequest;
use App\Http\Resources\QuestionBoardResource;
use App\Http\Resources\QuestionResource;
use App\Models\Event;
use App\Models\Question;
use App\Models\QuestionBoard;
use App\Models\TicketType;
use App\Notifications\QuestionAnswered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Nástenky otázok v dashboarde: zapnutie, nastavenia, moderovanie.
 *
 * Podujatie má jednu nástenku a každý jeho workshop môže mať vlastnú — preto
 * `index()` nevracia záznamy, ale **miesta** („sloty"): pre podujatie a pre
 * každý workshop povie, či nástenka existuje. Zakladá sa až kliknutím, aby
 * pri každom podujatí a workshope v databáze neležal riadok s tokenom, ktorý
 * nikto nikdy nepoužije.
 */
class DashboardQuestionController extends Controller
{
    /** Zoznam slotov: podujatie + jeho workshopy. */
    public function index(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json(['data' => $this->slots($event)]);
    }

    /** Zapnutie nástenky na podujatí alebo na jednom workshope. */
    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'target_type' => ['required', 'string', 'in:event,workshop'],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        $target = $this->resolveTarget($event, $data['target_type'], (int) $data['target_id']);
        $board = $target->ensureQuestionBoard();

        return response()->json(new QuestionBoardResource($board), 201);
    }

    public function update(QuestionBoardSettingsRequest $request, QuestionBoard $board): JsonResponse
    {
        $this->authorize('manage', $board);

        $board->update($request->validated());

        return response()->json(new QuestionBoardResource($board->refresh()));
    }

    /**
     * Nový token. Núdzová brzda, keď odkaz alebo fotka snímky uniknú tam, kam
     * nemali — starý odkaz okamžite prestane fungovať. Otázky ostávajú, mení sa
     * len adresa, takže treba znova stiahnuť snímku.
     */
    public function rotateToken(QuestionBoard $board): JsonResponse
    {
        $this->authorize('manage', $board);

        $board->update(['token' => QuestionBoard::freshToken()]);

        return response()->json(new QuestionBoardResource($board->refresh()));
    }

    /** Moderačný zoznam — na rozdiel od verejného obsahuje aj `pending` a `hidden`. */
    public function questions(Request $request, QuestionBoard $board): JsonResponse
    {
        $this->authorize('manage', $board);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,published,hidden'],
            'visibility' => ['nullable', 'string', 'in:public,private'],
        ]);

        $query = $board->questions()->inWallOrder();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        $questions = $query->limit(500)->get();
        // Jeden dotaz na celý zoznam: podľa neho sa pri každej otázke počíta,
        // či prišla počas akcie (teda či je to podnet, nie otázka do FAQ).
        $event = $board->event();

        return response()->json([
            'data' => $questions->map(fn (Question $q) => (new QuestionResource($q))->withModeration($event)),
            'counts' => $this->statusCounts($board),
        ]);
    }

    /**
     * Jeden endpoint na všetky moderačné zásahy — schválenie, skrytie,
     * zvýraznenie na stene, označenie za zodpovedanú aj dopísanie odpovede.
     * Sú to drobné prepnutia jedného riadku a rozpad na päť ciest by len
     * znásobil routy aj testy.
     */
    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        $this->authorize('moderate', $question);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,published,hidden'],
            'highlighted' => ['sometimes', 'boolean'],
            'answered' => ['sometimes', 'boolean'],
            'answer_body' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        // Zvýraznenie je „práve na toto odpovedáme" na premietacej stene.
        // Súkromná otázka tam nie je a nikdy nebude — a zvýraznením by navyše
        // zhaslo zvýraznenie tej otázky, ktorú sála práve číta.
        if (($data['highlighted'] ?? false) && $question->isPrivate()) {
            abort(422, __('questions.errors.private_not_highlightable'));
        }

        DB::transaction(function () use ($question, $data) {
            $board = $question->board()->lockForUpdate()->first();
            $wasPublic = $question->isPubliclyVisible();

            $attributes = [];

            if (array_key_exists('status', $data)) {
                $attributes['status'] = QuestionStatus::from($data['status']);
            }

            if (array_key_exists('highlighted', $data)) {
                // Zvýraznená je vždy najviac jedna otázka — „práve odpovedáme"
                // v množnom čísle nedáva na stene zmysel.
                if ($data['highlighted']) {
                    $board->questions()->whereKeyNot($question->id)->update(['highlighted_at' => null]);
                }

                $attributes['highlighted_at'] = $data['highlighted'] ? now() : null;
            }

            if (array_key_exists('answered', $data)) {
                $attributes['answered_at'] = $data['answered'] ? now() : null;
            }

            if (array_key_exists('answer_body', $data)) {
                $attributes['answer_body'] = $data['answer_body'];

                // Dopísaná odpoveď otázku zároveň označí za zodpovedanú —
                // klikať to zvlášť by bol zbytočný druhý krok.
                if (filled($data['answer_body']) && $question->answered_at === null) {
                    $attributes['answered_at'] = now();
                }
            }

            $question->update($attributes);

            $this->syncCount($board, $wasPublic, $question->refresh()->isPubliclyVisible());
        });

        // Až po commite: v transakcii by e-mail odišiel aj pri rollbacku
        // a odvolať sa nedá.
        if (filled($data['answer_body'] ?? null)) {
            $this->notifyAuthor($question->refresh());
        }

        $event = $question->board()->first()?->event();

        return response()->json((new QuestionResource($question->refresh()))->withModeration($event));
    }

    /**
     * Odpoveď e-mailom tomu, kto si ju pri otázke vypýtal.
     *
     * Adresa sa hneď maže — svoj jediný účel práve splnila a `questions` má byť
     * tabuľka bez priamych kontaktov (viď migráciu `add_answer_notification`).
     * Fronte to nevadí: `Notification::route()` si adresu serializuje do payloadu
     * jobu, takže prípadné opakovanie ju z databázy nečíta.
     *
     * `answer_notified_at` je poistka proti druhej vlne — keď organizátor
     * odpoveď neskôr preformuluje, druhý e-mail už nechodí. Je to zámer: ide
     * o jednorazovú správu, nie o odber.
     */
    private function notifyAuthor(Question $question): void
    {
        if (! $question->wantsAnswerNotification()) {
            return;
        }

        $email = $question->author_email;

        // Jazyk sedí na notifikácii, nie na adresátovi — `AnonymousNotifiable`
        // ho nepozná, adresa je len reťazec bez preferencií.
        $notification = new QuestionAnswered($question);

        if ($question->locale !== null) {
            $notification->locale($question->locale);
        }

        Notification::route('mail', $email)->notify($notification);

        $question->forceFill([
            'answer_notified_at' => now(),
            'author_email' => null,
        ])->save();
    }

    /**
     * Zmazanie je mäkké — spam sa zvyčajne rieši skrytím, ale keď niekto napíše
     * niečo, čo v databáze nemá čo hľadať, musí byť aj táto možnosť.
     */
    public function destroyQuestion(Question $question): JsonResponse
    {
        $this->authorize('delete', $question);

        DB::transaction(function () use ($question) {
            $board = $question->board()->lockForUpdate()->first();
            $wasPublic = $question->isPubliclyVisible();

            $question->delete();

            $this->syncCount($board, $wasPublic, false);
        });

        return response()->json(['deleted' => true]);
    }

    /**
     * Denormalizované `questions_count` drží len zverejnené otázky. Pri každom
     * moderačnom zásahu ho treba posunúť podľa toho, či otázka do verejného
     * zoznamu pribudla alebo z neho zmizla.
     */
    private function syncCount(QuestionBoard $board, bool $wasPublic, bool $isPublic): void
    {
        if ($wasPublic === $isPublic) {
            return;
        }

        if ($isPublic) {
            $board->increment('questions_count');

            return;
        }

        $board->update(['questions_count' => max(0, (int) $board->questions_count - 1)]);
    }

    /** @return array<string, int> */
    private function statusCounts(QuestionBoard $board): array
    {
        $counts = $board->questions()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[QuestionStatus::Pending->value] ?? 0),
            'published' => (int) ($counts[QuestionStatus::Published->value] ?? 0),
            'hidden' => (int) ($counts[QuestionStatus::Hidden->value] ?? 0),
            // Súkromné idú vedľa stavov, nie medzi ne: sú to iné otázky —
            // nikde sa nezverejnia a čaká sa pri nich odpoveď e-mailom.
            // Zvlášť ešte tie bez odpovede: to je jediné číslo, na ktoré má
            // organizátor počas akcie reagovať.
            'private' => (int) $board->questions()->onlyPrivate()->count(),
            'private_open' => (int) $board->questions()->onlyPrivate()->whereNull('answered_at')->count(),
        ];
    }

    /**
     * Sloty pre prepínač v UI. Bežné typy lístkov („Štandard", „VIP") sa sem
     * nedostanú — pýtať sa dá na program, nie na cenovú hladinu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function slots(Event $event): array
    {
        $slots = [$this->slot('event', $event->id, $event->name, $event->questionBoard()->first())];

        $workshops = $event->ticketTypes()
            ->where('kind', TicketTypeKind::Workshop->value)
            ->with('questionBoard')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($workshops as $workshop) {
            $slots[] = $this->slot('workshop', $workshop->id, $workshop->name, $workshop->questionBoard);
        }

        return $slots;
    }

    /** @return array<string, mixed> */
    private function slot(string $type, int $id, ?string $title, ?QuestionBoard $board): array
    {
        return [
            'target_type' => $type,
            'target_id' => $id,
            'title' => (string) $title,
            'board' => $board !== null ? new QuestionBoardResource($board) : null,
        ];
    }

    private function resolveTarget(Event $event, string $type, int $id): HasQuestionBoard
    {
        if ($type === 'event') {
            abort_unless($id === (int) $event->id, 404);

            return $event;
        }

        /** @var TicketType|null $workshop */
        $workshop = $event->ticketTypes()->whereKey($id)->first();

        abort_if($workshop === null, 404);
        abort_unless($workshop->isWorkshop(), 422, __('questions.errors.workshop_only'));

        $workshop->setRelation('event', $event);

        return $workshop;
    }
}
