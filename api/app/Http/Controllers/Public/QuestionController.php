<?php

namespace App\Http\Controllers\Public;

use App\Enums\QuestionChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionStoreRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionBoard;
use App\Services\Questions\BoardLocator;
use App\Services\Questions\QuestionDraft;
use App\Services\Questions\QuestionSubmitter;
use App\Services\Questions\QuestionVoteToggler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vloženie otázky a hlasovanie — bez účtu, autorizuje token v adrese.
 * Ochranné vrstvy sú popísané v QuestionSubmitter.
 */
class QuestionController extends Controller
{
    public function __construct(
        private BoardLocator $locator,
        private QuestionSubmitter $submitter,
        private QuestionVoteToggler $votes,
    ) {
    }

    public function store(QuestionStoreRequest $request, string $token): JsonResponse
    {
        $board = $this->locator->publicOrFail($token);

        // Nástenka z QR je zámerne bez kontaktu — `QuestionDraft` e-mail aj účet
        // pre tento kanál zahodí, aj keby ich niekto do požiadavky podstrčil.
        $question = $this->submitter->submit(
            $board,
            $request,
            QuestionDraft::from($request, $board, QuestionChannel::Wall),
        );

        // Odpoveď obsahuje aj stav nástenky, aby front vedel povedať „čaká na
        // schválenie" bez druhého dotazu, a `id` pre lokálne označenie „moja".
        return response()->json([
            'id' => $question->id,
            'pending' => ! $question->isPublished(),
            'question' => $question->isPublished() ? new QuestionResource($question) : null,
        ], 201);
    }

    public function vote(Request $request, string $token, int $question): JsonResponse
    {
        return $this->applyVote($request, $token, $question, vote: true);
    }

    public function unvote(Request $request, string $token, int $question): JsonResponse
    {
        return $this->applyVote($request, $token, $question, vote: false);
    }

    private function applyVote(Request $request, string $token, int $questionId, bool $vote): JsonResponse
    {
        $board = $this->locator->publicOrFail($token);

        $voterToken = trim((string) $request->input('voter_token'));

        if (strlen($voterToken) < 16 || strlen($voterToken) > 128) {
            abort(422, __('questions.errors.not_votable'));
        }

        $question = $this->questionOnBoard($board, $questionId);

        $count = $vote
            ? $this->votes->vote($question, $voterToken)
            : $this->votes->unvote($question, $voterToken);

        return response()->json(['id' => $question->id, 'upvotes_count' => $count]);
    }

    /**
     * Otázka sa hľadá vždy v rámci nástenky z tokenu — id samo o sebe nie je
     * autorizácia, inak by sa cez vlastnú nástenku dalo hlasovať za cudziu.
     */
    private function questionOnBoard(QuestionBoard $board, int $questionId): Question
    {
        $question = $board->questions()->whereKey($questionId)->first();

        if ($question === null) {
            abort(404);
        }

        $question->setRelation('board', $board);

        return $question;
    }
}
