<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
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

        $response = $this->postJson('/api/dashboard/organizations', [
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

    /** Vypršaný čas nie je to isté ako nedostupný register – hláška to má povedať. */
    #[Test]
    public function lookup_timeout_is_reported_as_such(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->user->givePermissionTo('organization.create');

        $response = $this->postJson('/api/dashboard/organizations/lookup-ico', ['ico' => '31820204']);

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

    #[Test]
    public function webhook_with_valid_signature_drops_cached_billing_data(): void
    {
        Cache::put('account:org:ffffffff-0000-1111-2222-333333333333', ['id' => 'x'], 3600);

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
        $this->assertNull(Cache::get('account:org:ffffffff-0000-1111-2222-333333333333'));
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
