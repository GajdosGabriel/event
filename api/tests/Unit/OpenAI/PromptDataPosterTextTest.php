<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\PromptData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromptDataPosterTextTest extends TestCase
{
    #[Test]
    public function poster_text_is_absent_from_the_default_schema(): void
    {
        $schema = (new PromptData())->jsonSchema()['json_schema']['schema'];

        $this->assertArrayNotHasKey('poster_text', $schema['properties']);
        $this->assertNotContains('poster_text', $schema['required']);
    }

    #[Test]
    public function poster_text_is_both_a_property_and_required_when_requested(): void
    {
        // `strict: true` odmietne schému, v ktorej je property mimo `required`.
        $schema = (new PromptData())->jsonSchema(true)['json_schema']['schema'];

        $this->assertArrayHasKey('poster_text', $schema['properties']);
        $this->assertContains('poster_text', $schema['required']);
        $this->assertEqualsCanonicalizing(
            array_keys($schema['properties']),
            $schema['required'],
        );
    }

    #[Test]
    public function the_prompt_asks_for_the_transcript_only_when_the_schema_does(): void
    {
        $prompt = new PromptData();
        $date = Carbon::parse('2026-08-01');

        $withText = json_encode($prompt->prompt('text', $date, true), JSON_UNESCAPED_UNICODE);
        $without = json_encode($prompt->prompt('text', $date), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('poster_text', (string) $withText);
        $this->assertStringNotContainsString('poster_text', (string) $without);
    }
}
