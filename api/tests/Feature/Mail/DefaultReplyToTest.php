<?php

namespace Tests\Feature\Mail;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Lokálna pošta ide z noreply@; odpoveď smeruje na kontakt, ak mail
 * nemá vlastný Reply-To (správa od človeka ho má na odosielateľa).
 */
class DefaultReplyToTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.from' => ['address' => 'noreply@tixio.eu', 'name' => 'Tixio'],
            'mail.default_reply_to' => ['address' => 'contact@tixio.eu', 'name' => 'Tixio'],
        ]);
    }

    private function lastSent(): Email
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    /** @return array<int, string> */
    private function replyTo(Email $email): array
    {
        return array_map(fn (Address $a) => $a->getAddress(), $email->getReplyTo());
    }

    #[Test]
    public function technical_mail_goes_from_noreply_with_reply_to_contact(): void
    {
        Mail::mailer('array')->raw('Odkaz na reset hesla', fn (Message $m) => $m->to('jana@example.com')->subject('Reset'));

        $email = $this->lastSent();

        $this->assertSame('noreply@tixio.eu', $email->getFrom()[0]->getAddress());
        $this->assertSame(['contact@tixio.eu'], $this->replyTo($email));
    }

    #[Test]
    public function own_reply_to_is_kept_alone(): void
    {
        Mail::mailer('array')->raw('Správa', fn (Message $m) => $m->to('organizator@example.com')
            ->subject('Nová správa')
            ->replyTo('navstevnik@example.com', 'Návštevník'));

        $this->assertSame(['navstevnik@example.com'], $this->replyTo($this->lastSent()));
    }

    #[Test]
    public function empty_default_adds_nothing(): void
    {
        config(['mail.default_reply_to.address' => null]);

        Mail::mailer('array')->raw('Text', fn (Message $m) => $m->to('jana@example.com')->subject('Bez'));

        $this->assertSame([], $this->replyTo($this->lastSent()));
    }
}
