<?php

namespace Tests\Unit\Rules;

use App\Rules\PhoneNumber;
use App\Rules\Postcode;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneAndPostcodeRuleTest extends TestCase
{
    private function phoneFails(mixed $value): bool
    {
        return Validator::make(['phone' => $value], ['phone' => [new PhoneNumber]])->fails();
    }

    private function postcodeFails(mixed $value, ?string $country = null): bool
    {
        return Validator::make(
            ['postcode' => $value, 'country' => $country],
            ['postcode' => [new Postcode]],
        )->fails();
    }

    #[Test]
    public function phone_accepts_common_formats_and_blank(): void
    {
        foreach (['+421 900 123 456', '0900-123-456', '(02) 1234 5678', '123456', null, ''] as $ok) {
            $this->assertFalse($this->phoneFails($ok), (string) $ok);
        }
    }

    #[Test]
    public function phone_rejects_letters_and_bad_lengths(): void
    {
        foreach (['abc-telefon', '12345', '+421 900 123 456 789 012', '0900 123 456 ext', '--- ---'] as $bad) {
            $this->assertTrue($this->phoneFails($bad), $bad);
        }
    }

    #[Test]
    public function postcode_for_sk_and_cz_is_five_digits(): void
    {
        foreach (['81101', '811 01', '110 00'] as $ok) {
            $this->assertFalse($this->postcodeFails($ok), $ok);
            $this->assertFalse($this->postcodeFails($ok, 'Slovensko'), $ok);
        }

        foreach (['ABC 12', '8110', '811 011', '81 101'] as $bad) {
            $this->assertTrue($this->postcodeFails($bad), $bad);
        }

        $this->assertFalse($this->postcodeFails(null));
    }

    #[Test]
    public function postcode_of_another_country_is_only_loosely_checked(): void
    {
        $this->assertFalse($this->postcodeFails('SW1A 1AA', 'United Kingdom'));
        $this->assertTrue($this->postcodeFails('SW1A 1AA'));
    }
}
