<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Services\Account\AccountClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

/**
 * Napojenie organizácií na Account.
 *
 * Account sa v testoch nikdy nevolá naozaj — Http::fake overuje, že Event
 * posiela to, čo má, a hlavne že bez ACCOUNT_TOKEN nevolá vôbec nič.
 */
class OrganizationAccountSyncTest extends UserSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('account.url', 'https://account.test');
        config()->set('account.token', 'acc_test');
        config()->set('account.webhook_secret', 'whsec_test');

        $this->user->givePermissionTo('organization.create');
        $this->user->givePermissionTo('organization.update');
    }

    #[Test]
    public function creating_organization_pushes_billing_data_to_account(): void
    {
        Http::fake([
            'https://account.test/api/v1/organizations' => Http::response([
                'data' => ['id' => '11111111-2222-3333-4444-555555555555', 'name' => 'Kultúrny dom'],
                'created' => true,
            ], 201),
        ]);

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Kultúrny dom',
            'status' => 'draft',
            'account' => [
                'ico' => '12345678',
                'street' => 'Hlavná',
                'street_no' => '1',
                'city' => 'Trenčín',
                'postal_code' => '91101',
                'country' => 'SK',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('account_uuid', '11111111-2222-3333-4444-555555555555');

        $this->assertDatabaseHas('organizations', [
            'title' => 'Kultúrny dom',
            'account_uuid' => '11111111-2222-3333-4444-555555555555',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://account.test/api/v1/organizations'
                && $request['name'] === 'Kultúrny dom'   // z `title`, netreba vypĺňať dvakrát
                && $request['ico'] === '12345678'
                && $request['external_ref'] !== null;
        });
    }

    #[Test]
    public function updating_linked_organization_calls_put_not_post(): void
    {
        $organization = Organization::query()->create([
            'title' => 'Divadlo',
            'status' => 'draft',
            'account_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        Http::fake([
            'https://account.test/api/v1/organizations/*' => Http::response([
                'data' => ['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'name' => 'Divadlo'],
            ], 200),
        ]);

        $response = $this->putJson("/api/dashboard/organizations/{$organization->id}", [
            'title' => 'Divadlo',
            'status' => 'draft',
            'account' => ['iban' => 'SK3112000000198742637541'],
        ]);

        $response->assertStatus(200);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/api/v1/organizations/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee')
            && $request['iban'] === 'SK3112000000198742637541');
    }

    #[Test]
    public function account_validation_errors_land_on_the_right_form_field(): void
    {
        Http::fake([
            'https://account.test/api/v1/organizations' => Http::response([
                'message' => 'The given data was invalid.',
                'errors' => ['ico' => ['IČO nemá platný tvar.']],
            ], 422),
        ]);

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Zlé IČO',
            'status' => 'draft',
            'account' => ['ico' => '999'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('account.ico');

        // Transakcia sa musí vrátiť — inak by v Evente zostala organizácia
        // bez fakturačných údajov, o ktorej používateľ nevie.
        $this->assertDatabaseMissing('organizations', ['title' => 'Zlé IČO']);
    }

    /**
     * Account vie najlepšie, čo sa pokazilo. Keď hlášku zahodíme a nahradíme
     * vlastnou, používateľ hľadá výpadok siete tam, kde ide o oprávnenia.
     */
    #[Test]
    public function account_error_message_reaches_the_user(): void
    {
        Http::fake([
            'https://account.test/api/v1/organizations' => Http::response([
                'message' => 'Token nemá oprávnenie organizations:write.',
            ], 403),
        ]);

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Bez práv',
            'status' => 'draft',
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('message', 'Account: Token nemá oprávnenie organizations:write.');

        $this->assertDatabaseMissing('organizations', ['title' => 'Bez práv']);
    }

    /** Holé „Server Error“ používateľovi nepovie nič — nahradí sa vlastnou vetou. */
    #[Test]
    public function unhelpful_account_error_falls_back_to_own_message(): void
    {
        Http::fake([
            'https://account.test/api/v1/organizations' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Rozbitý Account',
            'status' => 'draft',
        ]);

        $response->assertStatus(502);
        $this->assertStringContainsString('HTTP 500', $response->json('message'));
    }

    /** Nedostupný Account je iný problém než Account, ktorý odpovedal chybou. */
    #[Test]
    public function unreachable_account_is_reported_as_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        // Hláška ide používateľovi, takže je v jeho jazyku — bez X-Locale by
        // testovací request spadol na implicitné „Accept-Language: en“.
        $response = $this->withHeader('X-Locale', 'sk')->postJson('/api/dashboard/organizations', [
            'title' => 'Vypnutý Account',
            'status' => 'draft',
        ]);

        $response->assertStatus(503);
        $this->assertStringContainsString('neodpovedá', $response->json('message'));
    }

    /**
     * Vyhľadanie IČO čaká na štátny register, takže potrebuje vlastný,
     * dlhší strop. So spoločným 4-sekundovým by Event spojenie utínal skôr,
     * než register stihne odpovedať – a hlásil by „register nedostupný“
     * pri údajoch, ktoré sa práve našli.
     */
    #[Test]
    public function ico_lookup_waits_longer_than_ordinary_calls(): void
    {
        $this->assertGreaterThan(
            config('account.timeout'),
            config('account.lookup_timeout'),
            'Lookup musí mať dlhší timeout než bežné volania.',
        );

        // Account si na register dáva 10 s; kratší strop v Evente by jeho
        // odpoveď nikdy nestihol prijať.
        $this->assertGreaterThanOrEqual(10, config('account.lookup_timeout'));
    }

    /**
     * Prvé volanie po dlhšej nečinnosti Account iba prebúdza a stihne vypršať,
     * hoci register odpovedal a výsledok už leží v cache Accountu. Formulár
     * preto nesmie nechať používateľa zadávať IČO druhý raz ručne.
     */
    #[Test]
    public function lookup_survives_a_single_timeout_and_asks_again(): void
    {
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response(['data' => ['found' => true, 'name' => 'ESET, spol. s r.o.']]);
        });

        $response = $this->postJson('/api/dashboard/organizations/lookup-ico', ['ico' => '31333532']);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('name', 'ESET, spol. s r.o.');
        $this->assertSame(2, $calls, 'Po vypršaní času sa má Account spýtať ešte raz.');
    }

    /** Vypršaný čas nie je to isté ako nedostupný register – hláška to má povedať. */
    #[Test]
    public function lookup_timeout_is_reported_as_such(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->user->givePermissionTo('organization.create');

        $response = $this->withHeader('X-Locale', 'sk')
            ->postJson('/api/dashboard/organizations/lookup-ico', ['ico' => '31820204']);

        $response->assertOk();
        $response->assertJsonPath('found', false);
        $this->assertStringContainsString('neodpovedal načas', $response->json('error'));
    }

    #[Test]
    public function organization_is_created_locally_when_account_is_not_configured(): void
    {
        config()->set('account.token', null);
        Http::fake();

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Bez Accountu',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('organizations', ['title' => 'Bez Accountu', 'account_uuid' => null]);

        Http::assertNothingSent();
    }

    /**
     * Vývojár si prehodí ACCOUNT_URL na produkciu, aby videl reálne dáta —
     * a jedným uložením formulára založí testovaciu firmu v ostrej evidencii,
     * ktorú vidia všetky ostatné projekty. Zápis preto musí zlyhať skôr,
     * než odíde prvý paket.
     */
    #[Test]
    public function write_to_remote_account_is_blocked_outside_production(): void
    {
        config()->set('account.url', 'https://account.zastavy-vlajky.sk');
        Http::fake();

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Testovacia firma',
            'status' => 'draft',
            'account' => ['ico' => '12345678'],
        ]);

        $response->assertStatus(503);
        Http::assertNothingSent();

        // Zápis beží v transakcii, takže nesmie zostať ani lokálny polotovar.
        $this->assertDatabaseMissing('organizations', ['title' => 'Testovacia firma']);
    }

    #[Test]
    public function remote_write_passes_when_explicitly_allowed(): void
    {
        config()->set('account.url', 'https://account.zastavy-vlajky.sk');
        config()->set('account.allow_remote_writes', true);

        Http::fake([
            'https://account.zastavy-vlajky.sk/api/v1/organizations' => Http::response([
                'data' => ['id' => '99999999-8888-7777-6666-555555555555', 'name' => 'Povolená firma'],
            ], 201),
        ]);

        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Povolená firma',
            'status' => 'draft',
            'account' => ['ico' => '12345678'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('organizations', [
            'title' => 'Povolená firma',
            'account_uuid' => '99999999-8888-7777-6666-555555555555',
        ]);
    }

    #[Test]
    public function detail_attaches_billing_data_read_from_account(): void
    {
        $organization = Organization::query()->create([
            'title' => 'Klub',
            'status' => 'draft',
            'account_uuid' => 'ffffffff-0000-1111-2222-333333333333',
        ]);

        Http::fake([
            'https://account.test/api/v1/organizations/*' => Http::response([
                'data' => ['id' => 'ffffffff-0000-1111-2222-333333333333', 'identifiers' => ['ico' => '87654321']],
            ], 200),
        ]);

        $response = $this->getJson("/api/dashboard/organizations/{$organization->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('account.identifiers.ico', '87654321');
    }

    /**
     * Jazyk používateľa musí prejsť aj cez Event do Accountu — validačné
     * hlášky o IČO či IČ DPH píše Account a číta ich ten, kto vypĺňa
     * formulár, nie server.
     */
    #[Test]
    public function users_language_travels_with_the_request_to_account(): void
    {
        Http::fake([
            'https://account.test/api/v1/organizations' => Http::response([
                'data' => ['id' => '11111111-2222-3333-4444-555555555555', 'name' => 'Kulturhaus'],
                'created' => true,
            ], 201),
        ]);

        $this->withHeader('X-Locale', 'de')->postJson('/api/dashboard/organizations', [
            'title' => 'Kulturhaus',
            'status' => 'draft',
            'account' => ['ico' => '12345678'],
        ])->assertStatus(201);

        Http::assertSent(fn ($request) => $request->header('Accept-Language') === ['de']);
    }

    /**
     * Account posiela časť odpovede preloženú (`billing.missing`). Bez jazyka
     * v kľúči by prvý návštevník zamkol hlášky pre všetkých ostatných.
     */
    #[Test]
    public function billing_data_is_cached_per_language(): void
    {
        $uuid = 'ffffffff-0000-1111-2222-333333333333';

        // Account odpovie tým, čo si vypýtal jazyk — tak je vidieť, či sa
        // druhé volanie vôbec stalo, alebo prišlo z cache prvého jazyka.
        Http::fake(fn ($request) => Http::response([
            'data' => ['id' => $uuid, 'billing' => ['missing' => $request->header('Accept-Language')]],
        ], 200));

        $client = app(AccountClient::class);

        app()->setLocale('sk');
        $this->assertSame(['sk'], $client->organization($uuid)['billing']['missing']);

        app()->setLocale('de');
        $this->assertSame(['de'], $client->organization($uuid)['billing']['missing']);

        // A ten istý jazyk druhýkrát už do Accountu nechodí.
        $this->assertSame(['de'], $client->organization($uuid)['billing']['missing']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function webhook_with_valid_signature_drops_cached_billing_data(): void
    {
        // Cache je po jazykoch — webhook musí zmazať všetky, inak by zvyšné
        // jazyky ďalšiu hodinu ukazovali staré údaje.
        Cache::put('account:org:sk:ffffffff-0000-1111-2222-333333333333', ['id' => 'x'], 3600);
        Cache::put('account:org:de:ffffffff-0000-1111-2222-333333333333', ['id' => 'x'], 3600);

        $payload = [
            'event' => 'organization.updated',
            'data' => ['organization' => ['id' => 'ffffffff-0000-1111-2222-333333333333']],
        ];
        $body = json_encode($payload);
        $timestamp = time();

        $response = $this->call(
            'POST',
            '/api/webhooks/account',
            server: [
                'HTTP_X_ACCOUNTS_TIMESTAMP' => $timestamp,
                'HTTP_X_ACCOUNTS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $body,
        );

        $response->assertStatus(200);
        $this->assertNull(Cache::get('account:org:sk:ffffffff-0000-1111-2222-333333333333'));
        $this->assertNull(Cache::get('account:org:de:ffffffff-0000-1111-2222-333333333333'));
    }

    #[Test]
    public function webhook_with_bad_signature_is_rejected(): void
    {
        $response = $this->call(
            'POST',
            '/api/webhooks/account',
            server: [
                'HTTP_X_ACCOUNTS_TIMESTAMP' => time(),
                'HTTP_X_ACCOUNTS_SIGNATURE' => 'nezmysel',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{"event":"organization.updated","data":{}}',
        );

        $response->assertStatus(400);
    }
}
