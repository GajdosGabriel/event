<?php

namespace Tests\Feature\Events;

use App\Models\Organization;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventIndexCanalPayloadTest extends EventSetupTest
{
    /**
     * Firma nad kanálom je navyše len v admin výpise — dashboard ju
     * nepotrebuje a eager load by tam znamenal dotaz na každú stránku.
     */
    #[Test]
    public function canal_organization_is_omitted_from_the_dashboard_listing(): void
    {
        $organization = Organization::factory()->create();
        $this->canalPrimary->update(['organization_id' => $organization->id]);

        $response = $this->getJson('/api/dashboard/events');

        $response->assertStatus(200);

        foreach ($response->json('data') as $event) {
            $this->assertNull($event['canal']['organization'] ?? null);
        }
    }

    /**
     * Eager load kanála má obmedzený výber stĺpcov a nevybraný stĺpec Eloquent
     * nenahlási — ticho vráti null. Test drží výber a to, čo z kanála vypisuje
     * EventResource, pri sebe.
     */
    #[Test]
    public function canal_website_survives_the_restricted_column_select(): void
    {
        $this->canalPrimary->update(['website' => 'https://kultura-nitra.sk']);

        $response = $this->getJson('/api/dashboard/events');

        $response->assertStatus(200);

        $event = collect($response->json('data'))
            ->firstWhere('canal_id', $this->canalPrimary->id);

        $this->assertNotNull($event, 'Výpis nevrátil event z hlavného kanála.');
        $this->assertSame('https://kultura-nitra.sk', $event['canal']['website']);
    }
}
