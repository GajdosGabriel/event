<?php

namespace App\Services\Questions;

use App\Enums\QuestionChannel;
use App\Http\Requests\QuestionStoreRequest;
use App\Models\QuestionBoard;

/**
 * Čo si z formulára naozaj odnesieme do databázy.
 *
 * Existuje preto, že medzi „čo klient poslal" a „čo sa uloží" stoja dve
 * pravidlá, ktoré musia platiť na serveri — klient sa dá obísť, formulár
 * podstrčiť:
 *
 * 1. **meno** sa zahodí, keď má nástenka vypnuté `ask_for_name`,
 * 2. **e-mail, účet a jazyk** sa zahodia mimo verejného detailu podujatia.
 *    Nástenka z QR je zámerne bez kontaktu — otázka v sále odpoveď e-mailom
 *    nepotrebuje, prednášajúci ju povie nahlas.
 *
 * Prvé pravidlo bolo doteraz dvakrát opísané v dvoch controlleroch, druhé by
 * pribudlo ako tretie a štvrté miesto. Preto sú obe tu, na jednom.
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
    ) {
    }

    public static function from(
        QuestionStoreRequest $request,
        QuestionBoard $board,
        QuestionChannel $channel,
    ): self {
        $user = $request->user();
        $carriesContact = $channel === QuestionChannel::EventPage;

        // Podpis prihláseného berieme z účtu (osobný kanál, inak časť adresy
        // pred zavináčom — nikdy celá adresa, viď User::displayName).
        $name = $request->questionAuthorName() ?? ($carriesContact ? $user?->displayName() : null);

        return new self(
            body: $request->questionBody(),
            authorName: $board->ask_for_name ? $name : null,
            authorEmail: $carriesContact ? self::emailFor($request, $user?->email) : null,
            userId: $carriesContact ? $user?->id : null,
            locale: $carriesContact ? $request->questionLocale() : null,
        );
    }

    /**
     * Adresa je vyplnená len na výslovné želanie — prázdna hodnota je zároveň
     * záznam o tom, že o notifikáciu nikto nestál.
     */
    private static function emailFor(QuestionStoreRequest $request, ?string $accountEmail): ?string
    {
        if (! $request->wantsAnswerNotification()) {
            return null;
        }

        return $request->questionAuthorEmail() ?? $accountEmail;
    }
}
