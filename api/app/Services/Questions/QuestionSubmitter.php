<?php

namespace App\Services\Questions;

use App\Models\Question;
use App\Models\QuestionBoard;
use App\Support\VisitorPseudonym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Uloženie otázky z publika.
 *
 * Formulár je anonymný, takže tu je jediná brzda medzi sálou a databázou.
 * Vrstvy ochrany sú rozložené zámerne — každá sama o sebe sa dá obísť:
 *
 * 1. neuhádnuteľný token v adrese (BoardLocator),
 * 2. otvorená nástenka a časové okno (QuestionBoard::acceptsQuestions),
 * 3. limiter `questions` na IP (routes/api.php),
 * 4. honeypot a minimálny čas vyplnenia (QuestionStoreRequest),
 * 5. dedup podľa pseudonymu pisateľa (tu),
 * 6. moderovanie, keď si ho organizátor zapne.
 */
class QuestionSubmitter
{
    /**
     * Ako dlho sa tá istá otázka od tej istej osoby považuje za dvojklik.
     * Krátko — kto sa naozaj chce opýtať dvakrát to isté, o pár minút môže.
     */
    private const DUPLICATE_WINDOW_MINUTES = 5;

    public function submit(QuestionBoard $board, Request $request, string $body, ?string $authorName): Question
    {
        if (! $board->acceptsQuestions()) {
            abort(422, __('questions.errors.closed'));
        }

        $authorHash = VisitorPseudonym::forRequest($request);

        if ($this->isDuplicate($board, $authorHash, $body)) {
            abort(422, __('questions.errors.duplicate'));
        }

        $status = $board->statusForNewQuestion();

        return DB::transaction(function () use ($board, $body, $authorName, $authorHash, $status) {
            $question = $board->questions()->create([
                'body' => $body,
                'author_name' => $authorName,
                'author_hash' => $authorHash,
                'status' => $status,
            ]);

            // Počítadlo drží len zverejnené otázky — číslo sa ukazuje verejne
            // a nesmie prezradiť, koľko toho visí v moderácii. Pri schválení ho
            // dvíha QuestionModerator.
            if ($question->isPublished()) {
                $board->increment('questions_count');
            }

            return $question;
        });
    }

    /**
     * Zhoda sa hľadá cez presný text — normalizovať medzery či diakritiku by tu
     * bolo prestrelenie: cieľom je odchytiť dvojklik na tlačidle a opakované
     * odoslanie po vrátení späť, nie človeka, čo otázku preformuluje.
     */
    private function isDuplicate(QuestionBoard $board, string $authorHash, string $body): bool
    {
        return $board->questions()
            ->withTrashed()
            ->where('author_hash', $authorHash)
            ->where('body', $body)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->exists();
    }
}
