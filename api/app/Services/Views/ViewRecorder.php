<?php

namespace App\Services\Views;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Započíta zobrazenie verejného detailu.
 *
 * Návštevníka rozpoznáva pseudonymom, nie cookie: sha256 z IP, user-agenta,
 * aplikačného kľúča a dnešného dátumu. IP sa nikam neukladá a keďže je v hashi
 * dátum, pseudonym sa každý deň mení — z tabuľky sa teda nedá poskladať, čo
 * konkrétny človek prezeral naprieč dňami.
 *
 * Nikdy nevyhadzuje výnimku: zlyhanie štatistiky nesmie zhodiť zobrazenie
 * stránky.
 */
class ViewRecorder
{
    /**
     * Naivné boty. Presnú detekciu tu nerobíme — cieľom je, aby počítadlo
     * nemeralo crawlerov, nie neprestrelná ochrana.
     */
    private const BOT_PATTERN = '~bot|crawl|spider|slurp|headless|preview|monitor|curl|wget|python-requests|facebookexternalhit|whatsapp|telegram~i';

    /**
     * @return bool  true, keď zobrazenie naozaj pribudlo
     */
    public function record(Model $model, Request $request): bool
    {
        try {
            if (! $this->isCountable($model, $request)) {
                return false;
            }

            // insertOrIgnore v spojení s unikátnym indexom rieši dedup na deň:
            // vráti 0, keď riadok už existuje, takže druhé zobrazenie toho
            // istého návštevníka v ten istý deň počítadlo nezvýši.
            $inserted = DB::table('views')->insertOrIgnore([
                'viewable_type' => $model->getMorphClass(),
                'viewable_id' => $model->getKey(),
                'visitor_hash' => $this->visitorHash($request),
                'viewed_on' => now()->toDateString(),
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            // increment() namiesto save() — nesmie hýbať updated_at ani
            // spúšťať observery.
            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->increment('views_count');

            $model->setAttribute('views_count', (int) $model->getAttribute('views_count') + 1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isCountable(Model $model, Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        if ($userAgent === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return false;
        }

        // Organizátor ani admin si vlastnú štatistiku nenafukujú. Právo `view`
        // majú na svojich záznamoch práve tí, čo k nim majú prístup v dashboarde.
        $user = auth('sanctum')->user();

        if ($user !== null && Gate::forUser($user)->allows('view', $model)) {
            return false;
        }

        return true;
    }

    private function visitorHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) config('app.key'),
            now()->toDateString(),
        ]));
    }
}
