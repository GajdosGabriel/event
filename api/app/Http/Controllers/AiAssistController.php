<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAiSubject;
use App\Http\Requests\AiAssistRequest;
use App\Models\ContentReview;
use App\Services\Imports\HtmlBodyCleaner;
use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\PromptProfile;
use App\Services\Publishing\PublishReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Panel „Vyplniť pomocou AI" — jeden endpoint pre podujatie, miesto aj kanál.
 *
 * Nahrádza tri takmer rovnaké metódy roztrúsené po controlleroch záznamov
 * (dashboard + admin verzia `improveText`, `detect`). Boli to tie isté štyri
 * riadky s inou autorizáciou a rozchádzali sa: admin verzia mala iné limity
 * validácie než dashboardová a `expand` chýbal tam, kde ho ľudia potrebovali
 * najviac — pri miestach a kanáloch, ktoré vznikli importom s dvoma vetami.
 *
 * Sú tu dve operácie a je medzi nimi podstatný rozdiel:
 *
 *   improve — pracuje s textom, ktorý človek napísal. Bezpečné pre všetky tri
 *             typy, lebo model má z čoho vychádzať a má zakázané pridávať fakty.
 *   draft   — píše popis od nuly, len z názvu. Ponúka sa LEN pri mieste
 *             a kanáli: to sú trvalé subjekty, o ktorých sa dá napísať vecná
 *             informácia (a PromptProfile radšej vráti null, keď subjekt
 *             nepozná). Pri podujatí by to bola čistá výmysel — dátum, program
 *             ani cenu si model domyslieť nesmie, a práve to by od neho
 *             „napíš popis podujatia Púť na Butkov" žiadalo.
 *
 * Endpoint sám nič neukladá. Vracia návrh, formulár ho ukáže vedľa pôvodného
 * textu a zapíše sa až po potvrdení človekom.
 */
class AiAssistController extends Controller
{
    use ResolvesAiSubject;

    /**
     * Návrh textu. `success: false` s vysvetlením namiesto 500 — panel je
     * pomôcka a jej výpadok nemá zhodiť rozpísaný formulár.
     */
    public function assist(AiAssistRequest $request, ChatGPT $chatgpt, HtmlBodyCleaner $cleaner): JsonResponse
    {
        $data = $request->validated();
        $kind = $data['kind'];

        $this->authorize('create', $this->aiSubjectClass($kind));

        try {
            $result = $data['action'] === 'draft'
                ? $this->draft($chatgpt, $cleaner, $kind, $data)
                : $this->improve($chatgpt, $cleaner, $data);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, ...$result]);
    }

    /**
     * Uložený posudok zverejneného textu — to isté, o čom prišiel e-mail.
     *
     * Formulár si ho pýta preto, aby výhrady nežili len v e-maile: kto príde
     * upravovať záznam z iného dôvodu, má ich vidieť nad popisom.
     */
    public function review(Request $request, string $kind, int $id): JsonResponse
    {
        $class = $this->aiSubjectClass($kind);
        $model = $class::query()->findOrFail($id);

        $this->authorize('update', $model);

        /** @var ContentReview|null $review */
        $review = $model->contentReview()->first();

        if ($review === null || $review->reviewed_at === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'score' => $review->score,
            'summary' => $review->summary,
            'issues' => $review->issues ?? [],
            'modes' => $review->suggestedModes(),
            'reviewedAt' => $review->reviewed_at?->toIso8601String(),
            // Posudok patrí verzii textu, ktorú videl model. Keď sa medzitým
            // zmenil, formulár výhrady stlmí — ukazovať ich k inému textu by
            // mýlilo viac, než by pomohlo.
            'contentHash' => $review->content_hash,
        ]]);
    }

    /** Podmienky pripravenosti pre všetky typy — číta ich formulár. */
    public function readiness(PublishReadiness $readiness): JsonResponse
    {
        return response()->json(['data' => $readiness->allRules()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{improved_text: string, changes_summary: string}
     */
    private function improve(ChatGPT $chatgpt, HtmlBodyCleaner $cleaner, array $data): array
    {
        // `html` sa pridáva vždy: popis je HTML pole a návrh v holom texte by
        // sa po vložení rozsypal na jeden odsek.
        $modes = array_values(array_unique([...$data['modes'], 'html']));

        $result = $chatgpt->extractTextEdit($data['text'], $modes);

        return [
            'improved_text' => $cleaner->cleanHtmlString((string) $result['improved_text']),
            'changes_summary' => (string) $result['changes_summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{improved_text: string, changes_summary: string}
     */
    private function draft(ChatGPT $chatgpt, HtmlBodyCleaner $cleaner, string $kind, array $data): array
    {
        if ($kind === 'event') {
            // Nedostane sa sem cez formulár (panel voľbu neponúka), ale request
            // sa dá poslať aj ručne — a vymyslený popis podujatia je presne to,
            // čo tento portál nesmie vyrobiť.
            throw new \RuntimeException(__('validation.ai_draft_event_unsupported'));
        }

        $description = $chatgpt->extractProfileDescription(
            $kind === 'venue' ? PromptProfile::KIND_VENUE : PromptProfile::KIND_CANAL,
            $data['name'] ?? '',
            $data['context'] ?? null,
        );

        // PromptProfile vracia null zámerne — keď subjekt nepozná, má mlčať.
        // Ticho vrátiť prázdny návrh by vyzeralo ako chyba, tak to povieme.
        if ($description === null) {
            throw new \RuntimeException(__('validation.ai_draft_unknown_subject'));
        }

        return [
            'improved_text' => $cleaner->fromPlainText($description),
            'changes_summary' => __('validation.ai_draft_summary'),
        ];
    }
}
