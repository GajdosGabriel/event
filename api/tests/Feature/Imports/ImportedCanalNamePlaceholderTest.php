<?php

namespace Tests\Feature\Imports;

use App\Services\Imports\ImportedCanalNameResolver;
use App\Services\OpenAI\ChatGPT;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportedCanalNamePlaceholderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imports.detect_canal_with_ai', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Model občas nevyplní organizátora reťazcom, ale slovom, ktoré vyzerá
     * ako hodnota. `is_string()` taký reťazec pustí ďalej a v produkcii tak
     * vznikol kanál s menom aj slugom „null“ a popisom „null — organizátor
     * podujatí“. Zástupné hodnoty musia skončiť rovnako ako chýbajúci
     * organizátor: podujatie patrí do zberného kanála zdroja.
     */
    #[Test]
    public function a_placeholder_organizer_name_is_treated_as_no_organizer(): void
    {
        foreach (['null', 'N/A', 'neznámy', 'undefined', 'Neuvedené'] as $placeholder) {
            $resolved = $this->resolveWithAiOrganizer($placeholder);

            $this->assertNull(
                $resolved['detected_name'],
                sprintf('„%s“ nie je názov organizátora.', $placeholder),
            );
            $this->assertSame('vyveska.sk', $resolved['name']);
        }
    }

    #[Test]
    public function a_real_organizer_name_still_passes_through(): void
    {
        $resolved = $this->resolveWithAiOrganizer('Farnosť Gaboltov');

        $this->assertSame('Farnosť Gaboltov', $resolved['detected_name']);
        $this->assertSame('Farnosť Gaboltov', $resolved['name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWithAiOrganizer(string $organizerName): array
    {
        $chatGPT = Mockery::mock(ChatGPT::class);
        $chatGPT->shouldReceive('extractData')->andReturn([
            'organizer' => ['name' => $organizerName],
        ]);

        $resolver = new ImportedCanalNameResolver($chatGPT);

        return $resolver->resolve(
            'https://www.vyveska.sk/put-do-medzugoria-pobyt-pri-mori/',
            'Púť do Medžugoria + pobyt pri mori',
            'Pozývame na púť do Medžugoria s pobytom pri mori.',
        );
    }
}
