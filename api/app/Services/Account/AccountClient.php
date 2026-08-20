<?php

namespace App\Services\Account;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Klient pre Account — centrálnu evidenciu firiem.
 *
 * Zásada: čítanie degraduje elegantne, zápis nie.
 *
 *  - keď Account nebeží, čítanie vráti poslednú známu hodnotu z cache,
 *  - zápis v takom prípade poctivo zlyhá, lebo tichá fronta by po čase
 *    vytvorila dve rozchádzajúce sa verzie tej istej firmy.
 *
 * Bez nastaveného `ACCOUNT_TOKEN` je klient vypnutý — Event potom pracuje
 * len s lokálnym profilom organizácie a nikam nevolá.
 */
class AccountClient
{
    public function enabled(): bool
    {
        return filled(config('account.token'));
    }

    /* ---------------------------------------------------------------
     | Čítanie
     |---------------------------------------------------------------*/

    /**
     * Fakturačné údaje firmy. Cache invaliduje webhook `organization.updated`.
     *
     * @return array<string, mixed>|null
     */
    public function organization(string $uuid): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        if (($cached = Cache::get($this->key($uuid))) !== null) {
            return $cached;
        }

        try {
            $data = $this->request()->get("/api/v1/organizations/{$uuid}")->throw()->json('data');

            Cache::put($this->key($uuid), $data, config('account.cache.organization_ttl'));
            Cache::put($this->staleKey($uuid), $data, config('account.cache.stale_ttl'));

            return $data;
        } catch (\Throwable $e) {
            Log::warning('Account: nepodarilo sa nacitat organizaciu', [
                'organization' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return Cache::get($this->staleKey($uuid));
        }
    }

    /**
     * Vyhľadanie firmy v registri (RPO pre SK, ARES pre CZ) podľa IČO.
     * Slúži na predvyplnenie formulára, preto zlyhanie nie je chyba —
     * používateľ údaje jednoducho dopíše ručne.
     *
     * @return array<string, mixed>
     */
    public function lookupIco(string $ico, string $country = 'sk'): array
    {
        if (! $this->enabled()) {
            return ['found' => false, 'error' => __('organizations.account.disabled')];
        }

        // Prvé volanie po dlhšej nečinnosti Account iba „prebúdza“ a stihne
        // prekročiť strop — hoci register medzitým odpovedal a Account si
        // výsledok uložil. Práve preto druhý pokus vráti údaje obratom; presne
        // to používateľ pozná ako „prvýkrát nenájde, druhýkrát hneď nájde“.
        // Zopakujeme si to teda sami a predlžujeme tým iba čakanie, ktoré by
        // aj tak skončilo naprázdno.
        $attempts = max(1, (int) config('account.lookup_attempts'));

        try {
            return $this->request(config('account.lookup_timeout'))
                ->retry($attempts, 250, fn ($e) => $e instanceof ConnectionException, throw: true)
                ->post('/api/v1/organizations/lookup', ['ico' => $ico, 'country' => $country])
                ->throw()
                ->json('data');
        } catch (ConnectionException $e) {
            Log::warning('Account: vyhladanie ICO neodpovedalo vcas', [
                'ico' => $ico,
                'attempts' => $attempts,
                'error' => $e->getMessage(),
            ]);

            return ['found' => false, 'error' => __('organizations.account.lookup_timeout')];
        } catch (\Throwable $e) {
            Log::warning('Account: vyhladanie ICO zlyhalo', [
                'ico' => $ico,
                'status' => $e instanceof RequestException ? $e->response->status() : null,
                'response' => $e instanceof RequestException ? $e->response->body() : null,
                'error' => $e->getMessage(),
            ]);

            return ['found' => false, 'error' => __('organizations.account.lookup_failed')];
        }
    }

    /* ---------------------------------------------------------------
     | Zápis
     |---------------------------------------------------------------*/

    /**
     * Založenie firmy v Accounte, alebo naviazanie na už existujúcu.
     *
     * Account najprv hľadá podľa IČO — ak tá istá firma už prišla z iného
     * projektu, iba sa na ňu naviažeme. Preto sa NIKDY nevolá vlastné
     * "create"; inak by tá istá firma vznikla trikrát a centrálna
     * evidencia by stratila zmysel.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createOrLinkOrganization(array $data): array
    {
        $response = $this->request()->post('/api/v1/organizations', $data);

        $this->throwValidationErrors($response);

        return $response->throw()->json('data');
    }

    /**
     * Úprava firmy z formulára Eventu.
     *
     * Validáciu robí Account (pravidlá pre IČO a IČ DPH sú tam, aby sa
     * všetky projekty správali rovnako). Chyby prehodíme ako bežnú
     * Laravel ValidationException, takže sa v SPA zobrazia pri poliach
     * a používateľ netuší, že prišli z inej aplikácie.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateOrganization(string $uuid, array $data): array
    {
        $response = $this->request()->put("/api/v1/organizations/{$uuid}", $data);

        $this->throwValidationErrors($response);

        $organization = $response->throw()->json('data');

        $this->forget($uuid);

        return $organization;
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    public function forget(string $uuid): void
    {
        // Odpoveď je uložená zvlášť pre každý jazyk, takže zmazať jeden kľúč
        // nestačí — ostatné jazyky by ďalšiu hodinu ukazovali staré údaje.
        foreach (config('app.supported_locales', []) as $locale) {
            Cache::forget($this->key($uuid, $locale));
        }
    }

    /**
     * Account posiela časť odpovede preloženú (napr. `billing.missing`, čo
     * firme chýba na faktúru), preto je jazyk súčasťou kľúča. Bez neho by
     * si prvý návštevník „zamkol“ hlášky pre všetkých ostatných.
     */
    protected function key(string $uuid, ?string $locale = null): string
    {
        return 'account:org:'.($locale ?? app()->getLocale()).":{$uuid}";
    }

    protected function staleKey(string $uuid): string
    {
        return 'account:org:stale:'.app()->getLocale().":{$uuid}";
    }

    protected function request(?int $timeout = null): PendingRequest
    {
        return Http::withToken(config('account.token'))
            ->acceptJson()
            // Validačné chyby uvidí koncový používateľ, nech chodia v jeho jazyku.
            ->withHeaders(['Accept-Language' => app()->getLocale()])
            ->timeout($timeout ?? config('account.timeout'))
            ->baseUrl(config('account.url'));
    }

    protected function throwValidationErrors(Response $response): void
    {
        if ($response->status() === 422) {
            throw ValidationException::withMessages($response->json('errors', []));
        }
    }
}
