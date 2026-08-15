<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosterAnalyzeRequest;
use App\Http\Requests\PosterClaimRequest;
use App\Models\PosterDraft;
use App\Models\User;
use App\Notifications\PosterDraftSaved;
use App\Services\OpenAI\Detector;
use App\Services\Posters\PosterAnalysisReport;
use App\Services\Posters\PosterDraftMaterializer;
use App\Services\Posters\PosterExtraction;
use App\Services\Posters\PosterExtractionException;
use App\Services\Posters\PosterTextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * „Nahrajte plagát, o všetko ostatné sa postaráme"
 *
 * Vstupný bod pre človeka, ktorý o portáli nič nevie a nemá tu účet. Tok má
 * štyri kroky a každý má vlastnú routu:
 *
 *   1. `analyze`  — nahrá plagát, AI z neho prečíta podujatie. Bez prihlásenia.
 *   2. `show`     — návrat k rozpracovanému plagátu z odkazu v e-maile.
 *   3. `remember` — uloží e-mail a pošle odkaz späť.
 *   4. `claim`    — po registrácii z konceptu vznikne podujatie (+ kanál a miesto).
 *
 * Účet pýtame zámerne až v kroku 4. Kto ešte nevidel, čo AI z jeho plagátu
 * vytiahla, nemá dôvod sa registrovať — a to je presne tá bariéra, ktorú má
 * tento tok odstrániť. Cenu AI volania medzitým drží `throttle:ai`.
 */
class PosterController extends Controller
{
    /** Kým sa človek zaregistruje a potvrdí e-mail, môže prejsť aj deň. */
    private const DRAFT_TTL_DAYS = 7;

    private const DISK = 'local';

    public function analyze(
        PosterAnalyzeRequest $request,
        PosterTextExtractor $extractor,
        Detector $detector,
        PosterAnalysisReport $report,
    ): JsonResponse {
        $upload = $request->file('file');

        try {
            $extraction = $upload instanceof UploadedFile
                ? $extractor->fromUploadedFile($upload)
                : $extractor->fromText((string) $request->input('text'));
        } catch (PosterExtractionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $detection = $detector->detectFromPoster($extraction->text, $extraction->imageDataUrls);

        if (($detection['success'] ?? false) !== true) {
            Log::warning('PosterController: analýza plagátu zlyhala.', [
                'kind' => $extraction->kind,
                'error' => $detection['error'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('poster.draft.analyze_failed'),
            ], 422);
        }

        // Súbor si odkladáme na privátny disk: po registrácii sa priloží
        // k podujatiu ako plagát. Verejný disk by ho vystavil hneď, aj keď
        // podujatie ešte nikdy nemusí vzniknúť.
        $storedPath = $upload instanceof UploadedFile
            ? ($upload->store('poster-drafts', self::DISK) ?: null)
            : null;

        $token = Str::random(64);

        // Token nie je fillable zámerne — do DB ide len jeho hash a nastavuje sa
        // tu, nie z požiadavky. Preto `new` + priradenie namiesto `create()`.
        $draft = new PosterDraft([
            'source_kind' => $extraction->kind,
            'original_filename' => $upload instanceof UploadedFile
                ? $upload->getClientOriginalName()
                : null,
            'file_disk' => $storedPath !== null ? self::DISK : null,
            'file_path' => $storedPath,
            'extracted_text' => $extraction->text,
            'detection' => $detection,
            'analysis' => $report->build($detection, $extraction),
            'expires_at' => now()->addDays(self::DRAFT_TTL_DAYS),
        ]);

        $draft->token = PosterDraft::hashToken($token);

        // Analýza je už zaplatená a hotová — keby zápis padol, surová výnimka
        // by sa pri APP_DEBUG=true vyliala aj s celým `detection` payloadom
        // klientovi. Verejný endpoint takú chybu nesmie vypustiť von.
        try {
            $draft->save();
        } catch (\Throwable $e) {
            Log::error('PosterController: rozpracovaný plagát sa nepodarilo uložiť.', [
                'kind' => $extraction->kind,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('poster.draft.save_failed'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'draft' => $this->draftPayload($draft, $token),
        ], 201);
    }

    public function show(Request $request, string $draft, PosterAnalysisReport $report): JsonResponse
    {
        $model = $this->resolveDraft($draft, (string) $request->query('token', ''));

        // Report sa prepočítava, neberie sa uložený snapshot. Koncept žije až
        // 7 dní a za ten čas sa logika reportu môže zmeniť — po návrate
        // z e-mailu (alebo z localStorage) by inak človek videl výsledok podľa
        // starého kódu a nemal by ako pochopiť, prečo nesedí.
        $model->analysis = $report->build(
            (array) ($model->detection ?? []),
            PosterExtraction::fromStoredSource(
                $model->analysis['source'] ?? null,
                (string) $model->extracted_text,
            ),
        );

        return response()->json([
            'success' => true,
            'draft' => $this->draftPayload($model),
        ]);
    }

    /**
     * „Aby ste sa k údajom mohli vrátiť." Uloží e-mail a pošle naň odkaz späť
     * na rozpracovaný plagát — človek tak nepríde o analýzu ani vtedy, keď sa
     * rozhodne registrovať až neskôr alebo z iného zariadenia.
     */
    public function remember(Request $request, string $draft): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'email', 'max:250'],
        ]);

        $model = $this->resolveDraft($draft, $validated['token']);

        $model->forceFill(['email' => $validated['email']])->save();

        Notification::route('mail', $validated['email'])
            ->notify(new PosterDraftSaved(
                draftId: $model->id,
                token: $validated['token'],
                eventName: is_string($model->analysis['fields'][0]['value'] ?? null)
                    ? (string) $model->analysis['fields'][0]['value']
                    : null,
                expiresAt: $model->expires_at,
            ));

        return response()->json([
            'success' => true,
            'message' => __('poster.draft.link_sent', ['email' => $validated['email']]),
        ]);
    }

    public function claim(
        PosterClaimRequest $request,
        string $draft,
        PosterDraftMaterializer $materializer,
    ): JsonResponse {
        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            abort(401, __('poster.draft.login_required'));
        }

        $model = $this->resolveDraft($draft, (string) $request->input('token'));

        // Idempotencia: dvojklik na „Uložiť" ani obnovenie stránky nesmú
        // vyrobiť druhé podujatie z toho istého plagátu.
        if ($model->isClaimed()) {
            return response()->json([
                'success' => true,
                'already_claimed' => true,
                'event_id' => $model->event_id,
            ]);
        }

        $overrides = (array) $request->validated('overrides', []);
        if ($overrides !== []) {
            $model->forceFill(['overrides' => $overrides])->save();
        }

        $event = $materializer->materialize($model->refresh(), $user);

        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'canal_id' => $event->canal_id,
            'venue_id' => $event->venue_id,
        ], 201);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function resolveDraft(string $id, string $token): PosterDraft
    {
        if (trim($token) === '') {
            abort(403, __('poster.draft.token_missing'));
        }

        $draft = PosterDraft::query()
            ->where('id', $id)
            ->where('token', PosterDraft::hashToken($token))
            ->first();

        if (! $draft instanceof PosterDraft) {
            abort(404, __('poster.draft.not_found'));
        }

        if ($draft->isExpired()) {
            abort(410, __('poster.draft.expired'));
        }

        return $draft;
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(PosterDraft $draft, ?string $token = null): array
    {
        return [
            'id' => $draft->id,
            'token' => $token,
            'email' => $draft->email,
            'source_kind' => $draft->source_kind,
            'original_filename' => $draft->original_filename,
            'expires_at' => $draft->expires_at?->toIso8601String(),
            'claimed' => $draft->isClaimed(),
            'event_id' => $draft->event_id,
            'analysis' => $draft->analysis,
            // Predvyplnenie formulára opráv: hodnoty tak, ako ich AI vrátila,
            // prekryté tým, čo už človek raz opravil.
            'suggestion' => array_replace_recursive(
                (array) ($draft->detection['event_payload'] ?? []),
                (array) ($draft->overrides ?? []),
            ),
            // Rovnaké poradie ako v materializéri: vlastná oprava → copywriter
            // → prepis plagátu → surový text z dokumentu. Bez prvého kroku by
            // sa človeku po návrate z e-mailu prepísal jeho vlastný text späť
            // na AI verziu; bez ďalších by videl prázdne pole napriek tomu, že
            // text máme.
            'description' => $this->stringOrNull($draft->overrides['description'] ?? null)
                ?? $this->stringOrNull($draft->detection['corrected_text'] ?? null)
                ?? $this->stringOrNull($draft->detection['poster_text'] ?? null)
                ?? $this->stringOrNull($draft->extracted_text),
        ];
    }
}
