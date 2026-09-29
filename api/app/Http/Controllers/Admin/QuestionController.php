<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionStatus;
use App\Enums\QuestionVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Question;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Prehľad otázok z publika naprieč všetkými nástenkami. Len čítanie —
 * moderuje sa v detaile podujatia. `author_email` sa ani tu neukazuje
 * (viď Question::$hidden), admin vidí len, či si pisateľ odpoveď vypýtal.
 */
class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::enum(QuestionStatus::class)],
            'visibility' => ['nullable', Rule::enum(QuestionVisibility::class)],
            'answered' => ['nullable', 'in:yes,no'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $questions = Question::query()
            ->with(['board' => fn ($q) => $q->with(['boardable' => fn (MorphTo $morph) => $morph->morphWith([
                TicketType::class => ['event:id,name'],
            ])])])
            ->tap(fn (Builder $query) => $this->filter($query, $validated))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => $questions->getCollection()->map(fn (Question $question) => $this->row($question))->values(),
            'meta' => [
                'currentPage' => $questions->currentPage(),
                'lastPage' => $questions->lastPage(),
                'total' => $questions->total(),
            ],
            'summary' => $this->summary(),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Question $question): array
    {
        $target = $question->board?->boardable;
        $event = match (true) {
            $target instanceof Event => $target,
            $target instanceof TicketType => $target->event,
            default => null,
        };

        return [
            'id' => $question->id,
            'createdAt' => $question->created_at?->toIso8601String(),
            'body' => $question->body,
            'authorName' => $question->author_name,
            'userId' => $question->user_id,
            'status' => $question->status?->value,
            'statusLabel' => $question->status?->label(),
            'visibility' => $question->visibility?->value,
            'visibilityLabel' => $question->visibility?->label(),
            'upvotesCount' => $question->upvotes_count,
            'answerBody' => $question->answer_body,
            'answeredAt' => $question->answered_at?->toIso8601String(),
            'wantsEmail' => $question->author_email !== null,
            'event' => $event ? ['id' => $event->id, 'name' => $event->name] : null,
            // Nástenka workshopu — názov workshopu vedľa podujatia.
            'workshop' => $target instanceof TicketType ? $target->name : null,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['visibility'] ?? null, fn ($q, $value) => $q->where('visibility', $value))
            ->when(($filters['answered'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('answered_at'))
            ->when(($filters['answered'] ?? null) === 'no', fn ($q) => $q->whereNull('answered_at'))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $pattern = '%'.addcslashes($value, '%_\\').'%';
                $q->where(fn ($q) => $q
                    ->where('body', 'like', $pattern)
                    ->orWhere('author_name', 'like', $pattern)
                    ->orWhere('answer_body', 'like', $pattern));
            });
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $row = DB::table('questions')
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(created_at >= ?), 0) as day', [now()->subDay()])
            ->selectRaw('COALESCE(SUM(created_at >= ?), 0) as week', [now()->subDays(7)])
            ->selectRaw('COALESCE(SUM(status = ?), 0) as pending', [QuestionStatus::Pending->value])
            ->selectRaw('COALESCE(SUM(answered_at IS NULL), 0) as unanswered')
            ->first();

        return [
            'total' => (int) $row->total,
            'day' => (int) $row->day,
            'week' => (int) $row->week,
            'pending' => (int) $row->pending,
            'unanswered' => (int) $row->unanswered,
        ];
    }
}
