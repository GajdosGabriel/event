<?php

namespace App\Services\Account;

use App\Models\Organization;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Prenos firemných údajov z formulára Eventu do Accountu.
 *
 * Delenie zodpovednosti:
 *   Event   — verejný profil organizátora (názov, popis, avatar, obec, web)
 *   Account — fakturačná identita (IČO, DIČ, IČ DPH, sídlo, banka, register)
 *
 * Event si z Accountu drží iba `account_uuid`. Fakturačné polia sa sem
 * neukladajú ani do cache tabuľky — čítajú sa cez AccountClient.
 */
class OrganizationSync
{
    /**
     * Polia, ktoré Event posiela do Accountu. Zoznam je zámerne explicitný —
     * čokoľvek navyše by Account odmietol a čokoľvek chýbajúce by sa
     * pri úprave ticho stratilo.
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        // identifikácia
        'name', 'legal_name', 'legal_form', 'subject_type',
        'ico', 'dic', 'ic_dph', 'vat_mode', 'oss_registered',
        // zápis v registri
        'register_court', 'register_section', 'register_insert', 'established_at',
        // sídlo
        'street', 'street_no', 'city', 'postal_code', 'region', 'country',
        // kontakt
        'email', 'billing_email', 'phone', 'website',
        // banka
        'bank_name', 'iban', 'swift',
        // fakturačné preferencie
        'currency', 'payment_terms_days', 'payment_method',
        'invoice_language', 'invoice_delivery', 'supplier_number',
    ];

    public function __construct(private readonly AccountClient $account) {}

    /**
     * Odošle firmu do Accountu a uloží väzbu.
     *
     * Pri zakladaní Account najprv hľadá podľa IČO — ak tá istá firma už
     * prišla z iného projektu, iba sa na ňu naviažeme a dostaneme jej uuid.
     *
     * @param  array<string, mixed>  $input  fakturačné polia z formulára
     * @return array<string, mixed>|null údaje firmy z Accountu
     *
     * @throws \Illuminate\Validation\ValidationException chyby validácie z Accountu
     */
    public function push(Organization $organization, array $input): ?array
    {
        if (! $this->account->enabled()) {
            return null;
        }

        $payload = $this->payload($organization, $input);

        // Pri zakladaní musí Account dostať aspoň názov; pri úprave posiela
        // Event len to, čo používateľ vyplnil (API rieši `sometimes`).
        if (! $organization->isLinkedToAccount() && blank(Arr::get($payload, 'name'))) {
            return null;
        }

        try {
            $data = $organization->isLinkedToAccount()
                ? $this->account->updateOrganization($organization->account_uuid, $payload)
                : $this->account->createOrLinkOrganization($payload + ['external_ref' => (string) $organization->id]);
        } catch (ValidationException $e) {
            throw $this->prefixErrors($e);
        } catch (\Throwable $e) {
            // Zápis sa zámerne nezaraďuje do fronty na neskôr. Tichý retry by
            // znamenal, že používateľ vidí uložené údaje, ktoré v Accounte
            // nie sú — a pri ďalšej úprave by sa obe verzie rozišli.
            $status = $e instanceof RequestException ? $e->response->status() : null;

            Log::error('Account: zapis organizacie zlyhal', [
                'organization' => $organization->id,
                'account_uuid' => $organization->account_uuid,
                'status' => $status,
                // Celé telo, nie len `getMessage()` — Guzzle správu po 120
                // znakoch odreže a odrezané býva práve to podstatné.
                'response' => $e instanceof RequestException ? $e->response->body() : null,
                'error' => $e->getMessage(),
            ]);

            // Nedostupný Account a Account, ktorý odpovedal chybou, sú dva
            // rôzne problémy. Jedna spoločná hláška posiela človeka hľadať
            // výpadok siete tam, kde je chyba na strane Accountu.
            throw $e instanceof RequestException
                // 502 – chyba nastala vyššie po prúde, nie v Evente.
                ? new HttpException(502, $this->upstreamMessage($e), $e)
                : new ServiceUnavailableHttpException(
                    null,
                    'Fakturačné údaje sa nepodarilo uložiť — Account neodpovedá. Skús to o chvíľu znova.',
                    $e,
                );
        }

        $organization->forceFill([
            'account_uuid' => $data['id'] ?? $organization->account_uuid,
            'account_synced_at' => now(),
        ])->save();

        return $data;
    }

    /** Fakturačné údaje firmy na zobrazenie v Evente (z cache, ak sa dá). */
    /** @return array<string, mixed>|null */
    public function pull(Organization $organization): ?array
    {
        if (! $organization->isLinkedToAccount()) {
            return null;
        }

        return $this->account->organization($organization->account_uuid);
    }

    /**
     * Zloženie tela požiadavky.
     *
     * Prázdny reťazec a `null` sa nerozlišujú — Account by prázdny reťazec
     * bral ako vyplnenú hodnotu a napr. IČ DPH by neúspešne overoval.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function payload(Organization $organization, array $input): array
    {
        $payload = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            $payload[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        // Názov firmy je v Evente `title`; do Accountu ide ako `name`.
        // Pri zakladaní ho tak netreba vypĺňať dvakrát.
        if (blank($payload['name'] ?? null)) {
            $payload['name'] = $organization->title;
        }

        // Event rozlišuje osobu a organizáciu stĺpcom `person`, Account
        // enumom `subject_type`. Prekladá sa to tu, aby o tom formulár
        // ani zvyšok Eventu nemusel vedieť.
        $payload['subject_type'] = $organization->person ? 'person' : 'company';

        return $payload;
    }

    /**
     * Hláška z odpovede Accountu.
     *
     * Account vie najlepšie, čo sa pokazilo — „token nemá oprávnenie
     * organizations:write“ alebo „firma nie je naviazaná na projekt“ sú
     * hotové vety pre človeka. Vlastná náhrada by ich zahodila a nechala
     * hľadať príčinu v logu.
     *
     * Stav sa naopak nepreposiela: Event odhlasuje pri 401, takže odvolaný
     * service token by vyhodil používateľa z Eventu. Navonok je to preto
     * vždy 502 – chyba nastala vyššie po prúde.
     */
    protected function upstreamMessage(RequestException $e): string
    {
        $message = $e->response->json('message');

        // Pri APP_DEBUG=false vracia Laravel na 5xx holé „Server Error“.
        // Také niečo používateľovi nepovie nič, tak si necháme vlastnú vetu.
        if (! is_string($message) || trim($message) === '' || $message === 'Server Error') {
            return "Fakturačné údaje sa nepodarilo uložiť — Account odpovedal chybou (HTTP {$e->response->status()}). Podrobnosti sú v logu Eventu.";
        }

        return 'Account: '.$message;
    }

    /**
     * Account vracia chyby pod holým názvom poľa (`ico`), formulár v SPA
     * ich má vnorené v `account.ico`. Bez premenovania by sa chyba
     * nezobrazila pri poli a používateľ by videl len prázdnu červenú hlášku.
     */
    protected function prefixErrors(ValidationException $e): ValidationException
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $errors['account.'.$field] = $messages;
        }

        return ValidationException::withMessages($errors);
    }
}
