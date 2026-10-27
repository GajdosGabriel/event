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
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

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
        $this->assertWritesAllowed();

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
        $this->assertWritesAllowed();

        $response = $this->request()->put("/api/v1/organizations/{$uuid}", $data);

        $this->throwValidationErrors($response);

        $organization = $response->throw()->json('data');

        $this->forget($uuid);

        return $organization;
    }

    /* ---------------------------------------------------------------
     | Komunikácia s klientmi
     |---------------------------------------------------------------*/

    /** Posielať e-maily kanálom cez Account? */
    public function messagingEnabled(): bool
    {
        return $this->enabled() && (bool) config('account.messaging');
    }

    /**
     * Odošle e-mail cez schránku Eventu v Accounte.
     *
     * Volá sa z fronty (AccountMailChannel), takže výpadok Accountu sa
     * vyrieši opakovaním. Blokovaná adresa vyhodí AccountMessageRejected –
     * tú už opakovať nemá zmysel.
     *
     * Rovnako ako zápis firiem ide aj toto len do vlastnej inštancie:
     * mail odoslaný z lokálu cez produkčný Account by dostal skutočný človek.
     *
     * @param  array<string, mixed>  $message
     * @return array{conversation: array<string, mixed>, message: array<string, mixed>}
     */
    public function sendMessage(array $message): array
    {
        $this->assertWritesAllowed();

        $response = $this->request(30)->post('/api/v1/messages', $message);

        if ($response->status() === 422 && $response->json('reason')) {
            throw new AccountMessageRejected($response->json('reason'), (string) $response->json('message'));
        }

        $this->throwValidationErrors($response);

        return $response->throw()->json('data');
    }

    /**
     * Konverzácie k veci v Evente (napr. ku kanálu).
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversations(string $refType, string|int $refId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        try {
            return $this->request()
                ->get('/api/v1/conversations', ['ref_type' => $refType, 'ref_id' => (string) $refId])
                ->throw()
                ->json('data');
        } catch (\Throwable $e) {
            Log::warning('Account: konverzacie sa nepodarilo nacitat', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Odhlásenie z e-mailov Eventu nahlásené aj Accountu, aby na adresu
     * neposielal ani inbox. Najlepšia snaha – lokálna EmailSuppression
     * platí tak či tak.
     */
    public function suppress(string $email, string $reason = 'unsubscribed'): void
    {
        if (! $this->messagingEnabled()) {
            return;
        }

        try {
            $this->assertWritesAllowed();
            $this->request()->post('/api/v1/suppressions', ['email' => $email, 'reason' => $reason])->throw();
        } catch (\Throwable $e) {
            Log::warning('Account: odhlasenie sa nepodarilo nahlasit', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Odhlásenie v Evente prenesené do adresára Emailingu, aby Account
     * neposlal zvyšok postupnosti ponúk. Najlepšia snaha ako suppress().
     */
    public function unsubscribeMailing(string $email): void
    {
        if (! $this->mailingEnabled()) {
            return;
        }

        try {
            $this->upsertMailingContacts([[
                'email' => $email,
                'unsubscribed' => true,
                'changed_at' => now()->format(DATE_ATOM),
            ]]);
        } catch (\Throwable $e) {
            Log::warning('Account: odhlasenie z emailingu sa nepodarilo nahlasit', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    /* ---------------------------------------------------------------
     | Emailing (kampane sú v Accounte, Event hlási udalosti)
     |---------------------------------------------------------------*/

    /** Hlásiť udalosti registrácií pre kampane v Accounte? */
    public function mailingEnabled(): bool
    {
        return $this->enabled() && (bool) config('account.mailing');
    }

    /**
     * Nastala udalosť – aktívne automatizácie v Accounte zaradia maily.
     * Volaj z fronty (ReportMailingEvent), nie priamo v požiadavke.
     *
     * @param  array{type: string, id: string|int, label?: string, url?: string}|null  $ref
     * @param  array<string, scalar|null>  $variables  dosadia sa do textu ako {kluc}
     * @return int počet zaradených mailov
     */
    public function mailingEvent(
        string $event,
        string $email,
        ?array $ref = null,
        array $variables = [],
        ?string $dedupeKey = null,
        ?\DateTimeInterface $occurredAt = null,
        ?string $name = null,
        ?string $threadId = null,
    ): int {
        $this->assertWritesAllowed();

        return (int) $this->request()->post('/api/v1/mailing/events', array_filter([
            'event' => $event,
            'email' => $email,
            'name' => $name,
            'ref' => $ref,
            'variables' => $variables ?: null,
            'dedupe_key' => $dedupeKey,
            'occurred_at' => $occurredAt?->format(DATE_ATOM),
            'thread_id' => $threadId,
        ], fn ($v) => $v !== null))->throw()->json('data.queued');
    }

    /**
     * Vec sa zrušila (zrušená registrácia) – čakajúce maily k nej sa
     * neodošlú, okrem kampaní na udalosti v `$except`.
     *
     * @param  array<int, string>  $except
     */
    public function cancelMailing(string $refType, string|int $refId, array $except = []): int
    {
        $this->assertWritesAllowed();

        return (int) $this->request()->post('/api/v1/mailing/events/cancel', [
            'ref' => ['type' => $refType, 'id' => (string) $refId],
            'except' => $except,
        ])->throw()->json('data.skipped');
    }

    /**
     * Udalosti, ktoré Event hlási – v Accounte z nich operátor vyberá
     * spúšťač automatizácie. Celý zoznam nahradí doterajší.
     *
     * @param  array<int, array{key: string, label: string, short_label?: string, variables?: array<int, string>, quote_kind?: string}>  $triggers
     */
    public function registerMailingTriggers(array $triggers): void
    {
        $this->assertWritesAllowed();

        $this->request()->put('/api/v1/mailing/triggers', ['triggers' => $triggers])->throw();
    }

    /**
     * Kontakty so súhlasom so zasielaním ponúk (max. 1000 naraz).
     * Odhláseného človeka to späť neprihlási.
     *
     * @param  array<int, array{email: string, name?: string, permission_note: string, permission_at?: string, organization_id?: string}>  $contacts
     * @return array{added: int, updated: int}
     */
    public function upsertMailingContacts(array $contacts): array
    {
        $this->assertWritesAllowed();

        return $this->request()->post('/api/v1/mailing/contacts', ['contacts' => $contacts])->throw()->json('data');
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    /**
     * Poistka proti zápisu do cudzej inštancie Accountu.
     *
     * Zápis nemá fronta ani retry — čo sa raz pošle, to v centrálnej evidencii
     * firiem zostane a uvidia to všetky ostatné projekty. Vývojár, ktorý si
     * skopíruje produkčný `.env` (alebo len prehodí `ACCOUNT_URL`, aby si
     * pozrel reálne dáta), tak jedným uložením formulára založí testovaciu
     * firmu v ostrej prevádzke. Preto sa mimo produkcie do vzdialenej inštancie
     * nepíše, kým to niekto vedome nepovolí `ACCOUNT_ALLOW_REMOTE_WRITES=true`.
     *
     * Čítanie sa tým zámerne neobmedzuje — to nič nemení.
     */
    public function assertWritesAllowed(): void
    {
        if (app()->isProduction() || config('account.allow_remote_writes') || $this->targetsLocalInstance()) {
            return;
        }

        // 503, nie 500: nie je to chyba v kóde, ale nastavenie prostredia.
        throw new ServiceUnavailableHttpException(null, __('organizations.account.remote_write_blocked'));
    }

    /** Ukazuje `ACCOUNT_URL` na vývojársku inštanciu na tomto stroji? */
    protected function targetsLocalInstance(): bool
    {
        $host = strtolower((string) parse_url((string) config('account.url'), PHP_URL_HOST));

        if (in_array($host, ['localhost', '127.0.0.1', '::1', ''], true)) {
            return true;
        }

        // `.test` a `.localhost` sú vyhradené (RFC 6761), `.local` patrí mDNS
        // (RFC 6762) — ani jedna sa na verejnom internete vyskytnúť nemôže.
        return (bool) preg_match('/\.(local|test|localhost)$/', $host);
    }

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
