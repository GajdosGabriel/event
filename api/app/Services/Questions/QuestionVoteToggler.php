<?php

namespace App\Services\Questions;

use App\Models\Question;
use App\Models\QuestionVote;
use Illuminate\Support\Facades\DB;

/**
 * Hlas za otázku. Namiesto piatich variácií tej istej otázky sa jedna vyzbiera
 * hore a prednášajúci vie, čo sálu naozaj zaujíma.
 *
 * Hlasujúci sa rozpoznáva podľa náhodného tokenu z localStorage prehliadača —
 * viď migráciu `create_question_votes_table`, prečo nie podľa IP.
 */
class QuestionVoteToggler
{
    /** @return int nový počet hlasov */
    public function vote(Question $question, string $voterToken): int
    {
        $this->guard($question);

        $hash = $this->hash($voterToken);

        return DB::transaction(function () use ($question, $hash) {
            // insertOrIgnore + unikátny index: dva súčasné kliky nezvýšia
            // počítadlo dvakrát a nepotrebujeme na to zámok nad riadkom.
            $inserted = DB::table('question_votes')->insertOrIgnore([
                'question_id' => $question->id,
                'voter_hash' => $hash,
                'created_at' => now(),
            ]);

            if ($inserted > 0) {
                $question->increment('upvotes_count');
            }

            return (int) $question->refresh()->upvotes_count;
        });
    }

    /** @return int nový počet hlasov */
    public function unvote(Question $question, string $voterToken): int
    {
        $this->guard($question);

        $hash = $this->hash($voterToken);

        return DB::transaction(function () use ($question, $hash) {
            $deleted = QuestionVote::query()
                ->where('question_id', $question->id)
                ->where('voter_hash', $hash)
                ->delete();

            if ($deleted > 0) {
                // decrement() by pri rozjazdenom počítadle mohol ísť pod nulu;
                // unsignedInteger by to odmietol chybou z databázy.
                $question->update(['upvotes_count' => max(0, (int) $question->upvotes_count - 1)]);
            }

            return (int) $question->refresh()->upvotes_count;
        });
    }

    /**
     * Hlasovať sa dá len za zverejnenú otázku na nástenke, ktorá hlasovanie
     * povoľuje. Zavretá nástenka hlasovanie NEblokuje — po skončení prednášky
     * má zmysel ešte dohlasovať, čo sa má zodpovedať písomne.
     */
    private function guard(Question $question): void
    {
        $board = $question->relationLoaded('board')
            ? $question->getRelation('board')
            : $question->board()->first();

        if ($board === null || ! $board->allow_upvotes) {
            abort(422, __('questions.errors.votes_disabled'));
        }

        // Súkromná otázka vo verejnom zozname nie je, takže jej id sa nemá ako
        // dostať von — ale pozná ho ten, kto ju poslal. Hlas za ňu by nič
        // neprezradil, len rozhýbal počítadlo pri niečom, čo nikto nevidí.
        if (! $question->isPubliclyVisible()) {
            abort(422, __('questions.errors.not_votable'));
        }
    }

    private function hash(string $voterToken): string
    {
        return hash('sha256', $voterToken . '|' . config('app.key'));
    }
}
