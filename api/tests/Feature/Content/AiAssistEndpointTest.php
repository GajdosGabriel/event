<?php

namespace Tests\Feature\Content;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\User;
use App\Services\OpenAI\ChatGPT;
use App\Services\Publishing\PublishReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Panel „Vyplniť pomocou AI" — spoločný endpoint pre všetky tri typy
 * (App\Http\Controllers\AiAssistController).
 */
class AiAssistEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);

        $canal = Canal::factory()->create([
            'status' => ModelStatus::Published->value,
            'municipality_id' => 1,
            'registration_source' => RegistrationSource::SELF,
        ]);

        $canal->users()->attach($this->user->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
        ]);
    }

    private function fakeChatGpt(): void
    {
        $fake = new class extends ChatGPT
        {
            public function __construct()
            {
                parent::__construct();
            }

            public function extractTextEdit(string $text, array $modes): array
            {
                return [
                    'improved_text' => '<p>Vylepšený text.</p>',
                    'changes_summary' => 'Opravil som čiarky.',
                ];
            }

            public function extractProfileDescription(string $kind, string $name, ?string $context = null): ?string
            {
                return $name === 'Neznámy subjekt XYZ' ? null : 'Vecný popis subjektu.';
            }
        };

        app()->instance(ChatGPT::class, $fake);
    }

    /** Text dosť dlhý na to, aby prešiel dolnou hranicou požiadavky. */
    private function longText(): string
    {
        return '<p>Farnosť Belá je rímskokatolícka farnosť v okrese Žilina a stará sa '
            .'o duchovný život v obci.</p>';
    }

    #[Test]
    public function improve_returns_a_suggestion(): void
    {
        $this->fakeChatGpt();

        $this->actingAs($this->user)
            ->postJson('/api/dashboard/ai/assist', [
                'kind' => 'canal',
                'action' => 'improve',
                'text' => $this->longText(),
                'modes' => ['grammar'],
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'changes_summary' => 'Opravil som čiarky.']);
    }

    #[Test]
    public function the_length_limit_counts_text_not_markup(): void
    {
        $this->fakeChatGpt();

        // Surové HTML má cez 50 znakov, viditeľný text nie. Kým sa hranica
        // počítala z HTML, model dostal tri slová a vrátil vymyslený odstavec.
        $this->actingAs($this->user)
            ->postJson('/api/dashboard/ai/assist', [
                'kind' => 'canal',
                'action' => 'improve',
                'text' => '<p><a href="https://example.com/velmi/dlha/adresa">tu</a></p>',
                'modes' => ['grammar'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('text');
    }

    #[Test]
    public function an_event_description_cannot_be_written_from_scratch(): void
    {
        $this->fakeChatGpt();

        // Dátum, program ani cenu si model domyslieť nesmie — a presne to by
        // „napíš popis podujatia" od neho žiadalo.
        $this->actingAs($this->user)
            ->postJson('/api/dashboard/ai/assist', [
                'kind' => 'event',
                'action' => 'draft',
                'name' => 'Púť na Butkov',
            ])
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function a_draft_is_offered_for_a_canal(): void
    {
        $this->fakeChatGpt();

        $this->actingAs($this->user)
            ->postJson('/api/dashboard/ai/assist', [
                'kind' => 'canal',
                'action' => 'draft',
                'name' => 'Farnosť Belá',
                'context' => 'Belá, okres Žilina',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function an_unknown_subject_is_refused_rather_than_invented(): void
    {
        $this->fakeChatGpt();

        $this->actingAs($this->user)
            ->postJson('/api/dashboard/ai/assist', [
                'kind' => 'canal',
                'action' => 'draft',
                'name' => 'Neznámy subjekt XYZ',
            ])
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function an_unknown_kind_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/dashboard/ai/assist', [
                'kind' => 'user',
                'action' => 'improve',
                'text' => $this->longText(),
                'modes' => ['grammar'],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function the_endpoint_needs_authentication(): void
    {
        $this->postJson('/api/dashboard/ai/assist', [
            'kind' => 'canal',
            'action' => 'improve',
            'text' => $this->longText(),
            'modes' => ['grammar'],
        ])->assertStatus(401);
    }

    #[Test]
    public function the_readiness_rules_are_served_to_the_form(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/publish-readiness')
            ->assertOk();

        // Formulár aj server čítajú ten istý zoznam — preto sa vôbec posiela.
        $this->assertSame(
            app(PublishReadiness::class)->allRules(),
            $response->json('data'),
        );
    }
}
