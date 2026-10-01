<?php

namespace Tests\Feature;

use App\Rules\Postcode;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidationAttributeNamesTest extends TestCase
{
    #[Test]
    public function postcode_message_uses_the_translated_field_name(): void
    {
        app()->setLocale('sk');

        $v = Validator::make(['postcode' => 'ABC 12'], ['postcode' => [new Postcode]]);

        $this->assertStringStartsWith('Pole PSČ musí mať 5 číslic', $v->errors()->first('postcode'));
    }

    #[Test]
    public function every_locale_names_the_common_address_fields(): void
    {
        foreach (['sk', 'cs', 'en', 'de'] as $locale) {
            foreach (['postcode', 'city', 'street', 'capacity', 'website', 'phone'] as $field) {
                $this->assertNotSame("validation.attributes.$field", trans("validation.attributes.$field", [], $locale), "$locale/$field");
            }
        }
    }
}
