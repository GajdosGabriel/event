<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\PromptCopywriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Copywriter smie text len prepísať a členiť. Keď ho prompt vyzýval „rozšír
 * o motivačné vysvetlenie", AI k krátkej pozvánke dopísala vymyslený popis,
 * ktorý v zdroji nebol.
 */
class PromptCopywriterTest extends TestCase
{
    #[Test]
    public function prompt_forbids_adding_information_and_never_asks_to_expand(): void
    {
        $messages = (new PromptCopywriter)->prompt('Koncert zboru v sobotu o 18:00 v kostole.');
        $all = mb_strtolower($messages[0]['content'].' '.$messages[1]['content']);

        $this->assertStringContainsString('vylucne zo vstupneho textu', $all);
        $this->assertStringContainsString('nepridavaj ziadne informacie', $all);
        $this->assertStringNotContainsString('rozsir o motivacne', $all);
        $this->assertStringNotContainsString('ulohou je rozsirit', $all);
    }

    #[Test]
    public function short_text_is_not_forced_into_three_sections(): void
    {
        $user = (new PromptCopywriter)->prompt('Koncert zboru.')[1]['content'];

        $this->assertStringNotContainsString('Rozdel do 3 sekcii', $user);
        $this->assertStringContainsString('bez nadpisov', $user);
    }
}
