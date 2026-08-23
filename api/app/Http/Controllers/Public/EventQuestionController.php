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
use App\Services\Questions\PrivateQuestionAlert;
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
 * Otázky sem chodia ako QuestionChannel::EventPage, teda smú niesť e-mail
 * a väzbu na účet — pisateľ z gauča chce odpoveď dostať, nie ju chodiť hľadať.
 * A práve preto je toto jediný vchod, ktorý berie aj **súkromný** vstup:
 * otázku, ktorá sa na plátno nehodí, a počas akcie podnet organizátorovi
 * („v sále je zima"). Bez adresy na odpoveď by súkromná otázka nedávala zmysel,
 * takže na nástenke z QR kódu možná nie je (QuestionDraft).
 */
class EventQuestionController extends Controller
{
    /** Nad tento počet už stránka nie je FAQ, ale archív. */
    private const MAX_QUESTIONS = 50;

    public function __construct(
        protected EventRepository $eventRepository,
        private QuestionSubmitter $submitter,
        private PrivateQuestionAlert $organizerAlert,
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
            'open' => $board->acceptsQuestions(),
            'moderation' => (bool) $board->moderation,
            'show_questions' => (bool) $board->show_questions,
            'allow_upvotes' => (bool) $board->allow_upvotes,
            'ask_for_name' => (bool) $board->ask_for_name,
            // Smie sa tu niečo opýtať súkromne, a čo si to vyžaduje. Pravidlá
            // patria serveru — front z nich len skladá formulár a to isté si
            // pri odoslaní necháva overiť znova.
            'allow_private' => $board->acceptsPrivateQuestions(),
            'private_needs_account' => $phase->requiresAccountForPrivate(),
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

        $this->guardPrivate($request, $model);

        // Ochranné vrstvy sú tie isté ako pri nástenke z QR — líši sa len vchod,
        // a s ním to, či otázka nesie kontakt na pisateľa (QuestionDraft).
        $question = $this->submitter->submit(
            $board,
            $request,
            QuestionDraft::from($request, $board, QuestionChannel::EventPage),
        );

        // O súkromný vstup sa organizátor nemá ako dozvedieť inak — na verejnej
        // stránke nie je a počas akcie by mu bol na nič zajtra.
        if ($question->isPrivate()) {
            $this->organizerAlert->notify($question, $model);
        }

        return response()->json([
            'id' => $question->id,
            'pending' => ! $question->isPublished(),
            // Nie ozvena vstupu: adresu mohol doplniť server z účtu, takže front
            // sa inak nedozvie, či sľúbiť „ozveme sa".
            'notify' => $question->author_email !== null,
            'visibility' => $question->visibility->value,
            // Súkromná otázka nie je vo verejnom zozname ani vtedy, keď je
            // „zverejnená" — front ju do zoznamu nemá čo dopisovať.
            'question' => $question->isPubliclyVisible() ? new QuestionResource($question) : null,
        ], 201);
    }

    /**
     * Podnet počas akcie smie poslať len prihlásený.
     *
     * Je to prevádzková informácia, podľa ktorej niekto niečo urobí — pustí
     * kúrenie, pridá zvuk. Anonymné „v sále je zima" z druhého konca internetu
     * nie je podnet, je to šum, a rozoznať ich od seba sa inak nedá.
     * Lístok nepýtame: voľné podujatia žiadny nemajú a účet je jediná
     * podmienka, ktorá platí na všetkých.
     *
     * Pred akciou a po nej pravidlo neplatí — tam je súkromná otázka bežná
     * otázka a adresa na odpoveď je dostatočný kontakt (QuestionDraft).
     */
    private function guardPrivate(QuestionStoreRequest $request, Event $event): void
    {
        if (! $request->questionVisibility()->isPrivate()) {
            return;
        }

        $needsAccount = QuestionBoardPhase::for($event)->requiresAccountForPrivate();

        // `auth('sanctum')`, nie `$request->user()` — verejná cesta nemá
        // `auth:sanctum` middleware a predvolený guard by prihláseného
        // s tokenom nevidel (viď QuestionDraft).
        if ($needsAccount && auth('sanctum')->check() === false) {
            abort(422, __('questions.errors.private_needs_account'));
        }
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
