<?php

namespace App\Services\Questions;

use App\Enums\QuestionChannel;
use App\Enums\QuestionVisibility;
use App\Http\Requests\QuestionStoreRequest;
use App\Models\QuestionBoard;

/**
 * Čo si z formulára naozaj odnesieme do databázy.
 *
 * Existuje preto, že medzi „čo klient poslal" a „čo sa uloží" stoja tri
 * pravidlá, ktoré musia platiť na serveri — klient sa dá obísť, formulár
 * podstrčiť:
 *
 * 1. **meno** sa zahodí, keď má nástenka vypnuté `ask_for_name`,
 * 2. **e-mail, účet a jazyk** sa zahodia mimo verejného detailu podujatia.
 *    Nástenka z QR je zámerne bez kontaktu — otázka v sále odpoveď e-mailom
 *    nepotrebuje, prednášajúci ju povie nahlas,
 * 3. **súkromná otázka** sa prijme len tam, kde má odpoveď kam doručiť, a len
 *    keď ju nástenka berie (`allow_private`). Inak požiadavka spadne — mlčky
 *    ju zverejniť by bolo porušenie sľubu, s ktorým ju človek písal.
 *
 * Prvé pravidlo bolo doteraz dvakrát opísané v dvoch controlleroch, druhé by
 * pribudlo ako tretie a štvrté miesto. Preto sú všetky tu, na jednom.
 *
 * Prihlásený pisateľ nevypĺňa meno ani adresu — obe vieme z účtu a doplní ich
 * server, rovnako ako `TicketController` dopĺňa `holder_name` a `holder_email`
 * pri objednávke lístka. Klient by to ani nezvládol: `UserResource` posiela
 * e-mail len na admin routách.
 */
class QuestionDraft
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $authorName = null,
        public readonly ?string $authorEmail = null,
        public readonly ?int $userId = null,
        public readonly ?string $locale = null,
        public readonly QuestionVisibility $visibility = QuestionVisibility::Public,
    ) {
    }

    public static function from(
        QuestionStoreRequest $request,
        QuestionBoard $board,
        QuestionChannel $channel,
    ): self {
        // Zámerne `auth('sanctum')`, nie `$request->user()`: verejné cesty nemajú
        // `auth:sanctum` middleware, takže predvolený guard (`web`) na nich
        // prihláseného s tokenom nevidí — a formulár by prihlásenému nedoplnil
        // nič. Rovnaký zápis ako v TicketController a MessageController.
        $user = auth('sanctum')->user();
        $carriesContact = $channel->carriesContact();
        $visibility = self::visibilityFor($request, $board, $carriesContact);

        // Podpis prihláseného berieme z účtu (osobný kanál, inak časť adresy
        // pred zavináčom — nikdy celá adresa, viď User::displayName).
        $name = $request->questionAuthorName() ?? ($carriesContact ? $user?->displayName() : null);

        return new self(
            body: $request->questionBody(),
            // Meno pri súkromnej otázke drží organizátora v obraze, komu
            // odpovedá — a hlavne pri podnete („v sále je zima"), kde je to
            // jediná stopa po tom, kto ho poslal. Vypnuté `ask_for_name` sa
            // rešpektuje aj tak: je to voľba organizátora, nie technická.
            authorName: $board->ask_for_name ? $name : null,
            authorEmail: $carriesContact ? self::emailFor($request, $user?->email) : null,
            userId: $carriesContact ? $user?->id : null,
            locale: $carriesContact ? $request->questionLocale() : null,
            visibility: $visibility,
        );
    }

    /**
     * Súkromná otázka musí mať kam doručiť odpoveď. Ak by ju kanál z QR kódu
     * predsa poslal (formulár sa dá podstrčiť), spadne rovno tu — mlčky ju
     * prehlásiť za verejnú by bolo horšie než odmietnuť: pisateľ ju písal
     * s tým, že ju nikto iný neuvidí.
     */
    private static function visibilityFor(
        QuestionStoreRequest $request,
        QuestionBoard $board,
        bool $carriesContact,
    ): QuestionVisibility {
        $visibility = $request->questionVisibility();

        if (! $visibility->isPrivate()) {
            return $visibility;
        }

        if (! $carriesContact || ! $board->acceptsPrivateQuestions()) {
            abort(422, __('questions.errors.private_unavailable'));
        }

        return $visibility;
    }

    /**
     * Adresa je vyplnená len na výslovné želanie — prázdna hodnota je zároveň
     * záznam o tom, že o notifikáciu nikto nestál. Pri súkromnej otázke je
     * želanie automatické (QuestionStoreRequest::wantsAnswerNotification) a bez
     * adresy sa otázka neprijme: odpoveď by nemala kam prísť.
     */
    private static function emailFor(QuestionStoreRequest $request, ?string $accountEmail): ?string
    {
        if (! $request->wantsAnswerNotification()) {
            return null;
        }

        $email = $request->questionAuthorEmail() ?? $accountEmail;

        if ($email === null && $request->questionVisibility()->requiresContact()) {
            abort(422, __('questions.errors.private_needs_email'));
        }

        return $email;
    }
}
