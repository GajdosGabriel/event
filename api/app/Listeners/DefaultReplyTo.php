<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Mail z noreply@ dostane Reply-To na kontakt (config mail.default_reply_to).
 *
 * Len keď si ho mail nenastavil sám – správa od človeka (MessageReceived,
 * QuestionRelayed, EventAnnouncement…) má odpoveď smerovať k nemu.
 */
class DefaultReplyTo
{
    public function handle(MessageSending $event): void
    {
        $address = config('mail.default_reply_to.address');

        if (! filled($address) || $event->message->getReplyTo() !== []) {
            return;
        }

        $event->message->replyTo(new Address($address, (string) config('mail.default_reply_to.name')));
    }
}
