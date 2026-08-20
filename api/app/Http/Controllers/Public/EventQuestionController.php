<?php

namespace App\Http\Controllers\Public;

use App\Enums\QuestionBoardPhase;
use App\Enums\QuestionChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionStoreRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Event;
use App\Models\QuestionBoard;
use App\Repositories\Contracts\EventRepository;
use App\Services\Questions\QuestionDraft;
use App\Services\Questions\QuestionSubmitter;
use App\Support\SubmissionTicket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Otázky a odpovede na **verejnom detaile podujatia**.
 *
 * Nástenka otázok bola doteraz dostupná výhradne cez `/q/{token}`, teda cez QR
 * premietnutý v sále. Kto sedí doma nad stránkou podujatia, o jej existencii
 * nevedel — hoci práve tam sa pýtajú tie najužitočnejšie otázky („je vstup
 * naozaj zadarmo?", „môžem prísť s deťmi?", „je tam parkovanie?").
 *
 * **Nástenka sa hľadá cez podujatie, nie cez token.** Token je autorizácia
 * a dá sa rotovať (núdzová brzda, stará adresa hneď prestane platiť). Keby ho
 * verejný detail dostal do payloadu, rotácia by stránku rozbila a token by sa
 * šíril mimo QR — čo je presne to, čomu má rotácia zabrániť. Preto ho tento
 * controller nikdy neposiela a viditeľnosť si rieši sám cez `publicShow()`.
 *
 * Nástenka sa tu **nezakladá**. Vzniká lenivo až na žiadosť organizátora
 * (InteractsAsQuestionBoard) a bolo by chybou, aby ju vyrobila návšteva
 * verejnej stránky — importovaných podujatí sú tisíce.
 *
 * Otázky sem chodia ako QuestionChannel::EventPage, teda bez čakania na
 * `opens_at`. To okno stráži QR v sále; tu by len zavrelo formulár presne
 * v čase, keď sú predakčné otázky na mieste.
 */
class EventQuestionController extends Controller
{
    /** Nad tento počet už stránka nie je FAQ, ale archív. */
    private const MAX_QUESTIONS = 50;

    public function __construct(
        protected EventRepository $eventRepository,
        private QuestionSubmitter $submitter,
    ) {
    }

    public function index(int|string $event): JsonResponse
    {
        $model = $this->publicEventOrFail($event);
        $board = $this->boardFor($model);

        if ($board === null) {
            // Nie 404: „toto podujatie nástenku nemá" je legitímna odpoveď a UI
            // podľa nej sekciu jednoducho nevykreslí.
            return response()->json(['available' => false]);
        }

        $phase = QuestionBoardPhase::for($model);
        $questions = $this->visibleQuestions($board, $phase);

        return response()->json([
            'available' => true,
            'phase' => $phase->value,
            'open' => $board->acceptsQuestions(QuestionChannel::EventPage),
            'moderation' => (bool) $board->moderation,
            'show_questions' => (bool) $board->show_questions,
            'allow_upvotes' => (bool) $board->allow_upvotes,
            'ask_for_name' => (bool) $board->ask_for_name,
            'intro' => $board->intro,
            'questions_count' => (int) $board->questions_count,
            'answered_count' => $questions->filter(fn ($q) => $q->answered_at !== null)->count(),
            'ticket' => SubmissionTicket::issue('question:event:' . $model->id),
            'questions' => QuestionResource::collection($questions),
        ]);
    }

    public function store(QuestionStoreRequest $request, int|string $event): JsonResponse
    {
        $model = $this->publicEventOrFail($event);
        $board = $this->boardFor($model);

        if ($board === null) {
            abort(422, __('questions.errors.closed'));
        }

        // Ochranné vrstvy sú tie isté ako pri nástenke z QR — líši sa len vchod,
        // a s ním to, či platí začiatok okna a či otázka nesie kontakt
        // (QuestionChannel, QuestionDraft).
        $question = $this->submitter->submit(
            $board,
            $request,
            QuestionDraft::from($request, $board, QuestionChannel::EventPage),
            QuestionChannel::EventPage,
        );

        return response()->json([
            'id' => $question->id,
            'pending' => ! $question->isPublished(),
            // Nie ozvena vstupu: adresu mohol doplniť server z účtu, takže front
            // sa inak nedozvie, či sľúbiť „ozveme sa".
            'notify' => $question->author_email !== null,
            'question' => $question->isPublished() ? new QuestionResource($question) : null,
        ], 201);
    }

    /**
     * Čo sa na detaile ukáže. Pred podujatím a po ňom je to FAQ (zodpovedané
     * hore), počas podujatia to isté poradie ako na plátne — divák má na oboch
     * obrazovkách vidieť to isté.
     *
     * @return Collection<int, \App\Models\Question>
     */
    private function visibleQuestions(QuestionBoard $board, QuestionBoardPhase $phase): Collection
    {
        if (! $board->show_questions) {
            return new Collection();
        }

        $query = $board->questions()->publiclyVisible();

        return ($phase->isFaq() ? $query->inFaqOrder() : $query->inWallOrder())
            ->limit(self::MAX_QUESTIONS)
            ->get();
    }

    private function boardFor(Event $event): ?QuestionBoard
    {
        return $event->questionBoard()->first();
    }

    /**
     * Viditeľnosť rieši `publicShow()` — rovnako ako verejný detail, takže sa
     * cez otázky nedá prečítať koncept ani naplánované podujatie.
     */
    private function publicEventOrFail(int|string $event): Event
    {
        $model = $this->eventRepository->publicShow($event);

        if ($model === null) {
            abort(404);
        }

        return $model;
    }
}
