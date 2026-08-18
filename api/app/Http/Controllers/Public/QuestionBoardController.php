<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionBoard;
use App\Services\Questions\BoardLocator;
use App\Support\BoardToken;
use App\Support\PublicUrl;
use App\Support\SubmissionTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verejná nástenka otázok `/q/{token}` a jej premietacia stena.
 * Nevyžaduje prihlásenie — token v odkaze je autorizáciou, rovnako ako pri RSVP.
 */
class QuestionBoardController extends Controller
{
    public function __construct(
        private BoardLocator $locator,
    ) {
    }

    public function show(string $token): JsonResponse
    {
        $board = $this->locator->publicOrFail($token);

        return response()->json($this->present($board));
    }

    /**
     * Prírastok pre polling. Vracia celý zoznam, nie diff — otázok je na akciu
     * rádovo desiatky a poradie sa mení hlasovaním, takže zliať diff by na
     * klientovi bolo viac kódu než preposlať zoznam.
     *
     * `since` teda neslúži na filtrovanie, ale na odpoveď „nič nové" bez
     * serializácie: keď sa od posledného volania nezmenilo najvyššie id ani
     * najnovší `updated_at`, pošle sa prázdna odpoveď.
     */
    public function stream(Request $request, string $token): JsonResponse
    {
        $board = $this->locator->publicOrFail($token);

        if (! $board->show_questions) {
            return response()->json(['changed' => false]);
        }

        $state = $this->streamState($board);

        if ($request->query('v') === $state) {
            return response()->json(['changed' => false]);
        }

        return response()->json([
            'changed' => true,
            'v' => $state,
            'questions_count' => (int) $board->questions_count,
            'questions' => QuestionResource::collection($this->visibleQuestions($board)),
        ]);
    }

    /**
     * Otisk stavu nástenky. Musí sa hýbať aj pri zmene, ktorá nepridá riadok —
     * schválenie, skrytie, hlas, zvýraznenie — preto v ňom je aj najnovší
     * `updated_at` a počet, nielen najvyššie id.
     */
    private function streamState(QuestionBoard $board): string
    {
        $row = $board->questions()->publiclyVisible()
            ->selectRaw('COUNT(*) AS c, COALESCE(MAX(id), 0) AS max_id, COALESCE(MAX(updated_at), 0) AS max_updated')
            ->first();

        return implode('-', [(int) $row->c, (int) $row->max_id, (string) $row->max_updated]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Question> */
    private function visibleQuestions(QuestionBoard $board)
    {
        return $board->questions()->publiclyVisible()->inWallOrder()->limit(200)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(QuestionBoard $board): array
    {
        $event = $board->event();
        $isWorkshop = $board->targetType() === 'workshop';
        $venue = $event?->venue;

        return [
            'code' => BoardToken::forDisplay($board->token),
            'title' => $board->title(),
            // Pri workshope treba nad názov bloku dopísať, na akej akcii sme —
            // človek naskenoval kód v sále a nemusí vedieť, kam prišiel.
            'event_name' => $isWorkshop ? $event?->name : null,
            'event_url' => $event ? PublicUrl::eventPath($event) : null,
            'starts_at' => $event?->start_at,
            'ends_at' => $event?->end_at,
            'venue_name' => $venue?->name,
            'municipality_name' => $event?->municipality?->name,
            'organizer_name' => $event?->canal?->name,
            'intro' => $board->intro,
            'open' => $board->acceptsQuestions(),
            'moderation' => (bool) $board->moderation,
            'show_questions' => (bool) $board->show_questions,
            'allow_upvotes' => (bool) $board->allow_upvotes,
            'ask_for_name' => (bool) $board->ask_for_name,
            'questions_count' => (int) $board->questions_count,
            // Známka putuje na klienta a vracia sa pri odoslaní — viď SubmissionTicket.
            'ticket' => SubmissionTicket::issue('question:' . $board->token),
            'v' => $board->show_questions ? $this->streamState($board) : null,
            'questions' => $board->show_questions
                ? QuestionResource::collection($this->visibleQuestions($board))
                : [],
        ];
    }
}
