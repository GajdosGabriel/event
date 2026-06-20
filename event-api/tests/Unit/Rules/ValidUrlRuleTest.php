<?php

namespace Tests\Unit\Rules;

use Tests\TestCase;
use App\Rules\ValidUrlRule;
use Illuminate\Support\Facades\Validator;

class ValidUrlRuleTest extends TestCase
{
     public function test_valid_urls_pass_validation()
    {
        $rule = new ValidUrlRule(false, false);
        
        $validator = Validator::make(
            ['url' => 'https://example.com'],
            ['url' => [$rule]]
        );
        
        $this->assertFalse($validator->fails());
    }

    public function test_invalid_urls_fail_validation()
    {
        $rule = new ValidUrlRule(false, false);
        
        $validator = Validator::make(
            ['url' => 'invalid-url'],
            ['url' => [$rule]]
        );
        
        $this->assertTrue($validator->fails());
    }

    public function test_https_requirement()
    {
        $rule = new ValidUrlRule(true, false);
        
        $validator = Validator::make(
            ['url' => 'http://insecure.com'],
            ['url' => [$rule]]
        );
        
        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'The url must start with https://',
            $validator->errors()->first('url')
        );
    }
}
