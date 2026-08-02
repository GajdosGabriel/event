<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Overuje celú cestu formulár → DB → verejná odpoveď. Jednotkové pokrytie
 * mutatora je v tests/Unit/Events/BodySanitizationTest.php; tento test drží
 * stráž nad tým, že sa čistenie po ceste nikde neobíde.
 */
class DashboardEventBodyXssTest extends EventSetupTest
{
    #[Test]
    public function a_script_in_the_body_never_reaches_the_database(): void
    {
        $this->putJson('/api/dashboard/events/'.$this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'body' => '<p>Popis podujatia.</p><script>alert(1)</script>',
        ])->assertOk();

        $body = (string) $this->futureEvent->fresh()->body;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringContainsString('Popis podujatia.', $body);
    }

    #[Test]
    public function a_script_in_the_body_never_reaches_the_public_detail(): void
    {
        // EventFactory dáva podujatiu náhodný status, ale verejný detail vidno
        // len pri publikovanom — bez tohto by test padal podľa toho, čo faker
        // vylosoval.
        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);

        $this->putJson('/api/dashboard/events/'.$this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'body' => '<p>Popis podujatia.</p><img src=x onerror="alert(1)">',
        ])->assertOk();

        $response = $this->getJson('/api/events/'.$this->futureEvent->id);
        $response->assertOk();

        $this->assertStringNotContainsString('onerror', (string) $response->json('body'));
    }
}
