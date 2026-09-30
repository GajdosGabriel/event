<?php

namespace Tests\Unit\Support;

use App\Support\ContactList;
use PHPUnit\Framework\TestCase;

class ContactListTest extends TestCase
{
    public function test_splits_joined_phones_and_keeps_primary_first(): void
    {
        $result = ContactList::fromPayload([
            'phone' => '(055) 623 01 37, 0905 244 583, 0903 982 541',
            'phones' => ['0905 244 583', '0911 111 111'],
        ]);

        $this->assertSame('(055) 623 01 37', $result['phone']);
        $this->assertSame(['0905 244 583', '0903 982 541', '0911 111 111'], $result['additional_phones']);
    }

    public function test_phones_split_on_or_word_and_drop_short_values(): void
    {
        $this->assertSame(['0905 244 583', '0903 982 541'], ContactList::phones('0905 244 583 alebo 0903 982 541, 12'));
    }

    public function test_emails_are_validated_lowercased_and_deduplicated(): void
    {
        $result = ContactList::fromPayload([
            'email' => 'DMC@dmc.sk',
            'emails' => ['dmc@dmc.sk', 'info@dmc.sk; nie-email', 'x@y.sk'],
        ]);

        $this->assertSame('dmc@dmc.sk', $result['email']);
        $this->assertSame(['info@dmc.sk', 'x@y.sk'], $result['additional_emails']);
    }

    public function test_empty_payload_gives_nulls_and_empty_lists(): void
    {
        $this->assertSame(
            ['email' => null, 'additional_emails' => [], 'phone' => null, 'additional_phones' => []],
            ContactList::fromPayload(['email' => null, 'phone' => ' ', 'emails' => [], 'phones' => []]),
        );
    }
}
