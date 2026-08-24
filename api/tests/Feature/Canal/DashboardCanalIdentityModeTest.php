<?php

namespace Tests\Feature\Canals;

use App\Enums\CanalIdentityMode;
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalIdentityModeTest extends CanalSetupTest
{
    /**
     * Symfony dáva testovaciemu requestu implicitné „Accept-Language: en-us,en"
     * a SetLocale ju od commitu „lang + organization with lang" rešpektuje.
     * Bez prebitia by odpoveď prišla po anglicky, hoci default je sk.
     */
    private function withoutLanguagePreference(): self
    {
        return $this->withHeader('Accept-Language', '');
    }

    public function test_identity_mode_options_come_from_lang(): void
    {
        $response = $this->withoutLanguagePreference()
            ->getJson('/api/dashboard/canals/identity-modes');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                ['value' => 'personal', 'label' => 'Osobný'],
                ['value' => 'organization', 'label' => 'Organizácia'],
                ['value' => 'pseudonymous', 'label' => 'Pseudonymný'],
            ],
        ]);
    }

    public function test_show_exposes_translated_identity_mode_label(): void
    {
        $this->canalPrimary->update(['identity_mode' => CanalIdentityMode::Organization->value]);

        $response = $this->withoutLanguagePreference()
            ->getJson('/api/dashboard/canals/'.$this->canalPrimary->id);

        $response->assertOk();
        $response->assertJsonFragment(['identity_mode_label' => 'Organizácia']);
    }

    public function test_canal_created_on_registration_is_personal(): void
    {
        $canal = $this->user->canals()->first();

        $this->assertSame(CanalIdentityMode::Personal, $canal->identity_mode);
    }
}
