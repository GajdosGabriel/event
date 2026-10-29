<?php

namespace App\Services\Views;

use App\Support\VisitorPseudonym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Započíta zobrazenie verejného detailu.
 *
 * Návštevníka rozpoznáva pseudonymom, nie cookie — viď VisitorPseudonym.
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
     * @return bool true, keď zobrazenie naozaj pribudlo
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
                'visitor_hash' => VisitorPseudonym::forRequest($request),
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

        if (! $this->comesFromOurPages($request)) {
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

    /**
     * Zobrazenie posiela len náš front (vrátane embed iframe, ktorý beží na
     * našej doméne). Prehliadač pri POST-e vždy pošle `Origin` a nový aj
     * `Sec-Fetch-Site` — skript, ktorý endpoint volá priamo, ich väčšinou nemá.
     * Podvrhnúť sa dajú, ale odfiltrujú naivné volania mimo stránky.
     */
    private function comesFromOurPages(Request $request): bool
    {
        $fetchSite = $request->header('Sec-Fetch-Site');

        if ($fetchSite !== null && ! in_array($fetchSite, ['same-origin', 'same-site'], true)) {
            return false;
        }

        $originHost = parse_url((string) $request->header('Origin'), PHP_URL_HOST);

        if (! is_string($originHost) || $originHost === '') {
            return false;
        }

        $ourHosts = array_filter([
            $request->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.frontend_url'), PHP_URL_HOST),
        ]);

        return in_array(strtolower($originHost), array_map('strtolower', $ourHosts), true);
    }
}
