<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\PromptTags;
use PHPUnit\Framework\Attributes\Test;
// Nie PHPUnit\Framework\TestCase — posledný test používa helper validator(),
// ktorý potrebuje kontajner.
use Tests\TestCase;

class PromptTagsTest extends TestCase
{
    private function catalog(): array
    {
        return [
            'format' => [
                ['slug' => 'koncert', 'name' => 'Koncert'],
                ['slug' => 'divadlo', 'name' => 'Divadlo'],
            ],
            'topic' => [
                ['slug' => 'folklor', 'name' => 'Folklór'],
            ],
        ];
    }

    #[Test]
    public function schema_restricts_slugs_to_the_catalog(): void
    {
        $schema = (new PromptTags())->jsonSchema(['koncert', 'divadlo', 'folklor']);

        $slugProperty = $schema['json_schema']['schema']['properties']['tags']['items']['properties']['slug'];

        // Toto je jediné, čo modelu bráni vymyslieť si vlastný štítok
        // a zapleviť číselník synonymami.
        $this->assertSame(['koncert', 'divadlo', 'folklor'], $slugProperty['enum']);
        $this->assertTrue($schema['json_schema']['strict']);
        $this->assertFalse($schema['json_schema']['schema']['additionalProperties']);
    }

    #[Test]
    public function schema_requires_both_tags_and_suggestions(): void
    {
        $schema = (new PromptTags())->jsonSchema(['koncert']);

        $this->assertSame(['tags', 'suggested'], $schema['json_schema']['schema']['required']);
        $this->assertSame(
            ['slug', 'confidence'],
            $schema['json_schema']['schema']['properties']['tags']['items']['required'],
        );
    }

    #[Test]
    public function prompt_lists_every_catalog_entry_with_its_facet(): void
    {
        $messages = (new PromptTags())->prompt('Folklórny festival', $this->catalog());

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);

        $userMessage = $messages[1]['content'];

        // Model potrebuje aj názvy, inak zo samotného slugu nevie, čo štítok znamená.
        $this->assertStringContainsString('FORMAT: koncert = Koncert | divadlo = Divadlo', $userMessage);
        $this->assertStringContainsString('TOPIC: folklor = Folklór', $userMessage);
        $this->assertStringContainsString('Folklórny festival', $userMessage);
    }

    #[Test]
    public function validator_accepts_empty_arrays(): void
    {
        // Podujatie, ktoré sa nedá zaradiť, je legitímny výsledok — nesmie
        // spadnúť na validácii.
        $validator = validator(
            ['tags' => [], 'suggested' => []],
            (new PromptTags())->validator(),
        );

        $this->assertFalse($validator->fails());
    }
}
