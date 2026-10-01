<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * O čom môže člen tímu kanála dostávať notifikácie (viď CanalRecipients,
 * docs/canal-ownership.md). V DB sa ukladajú len odchýlky od predvolieb roly.
 */
enum CanalNotificationTopic: string implements HasLabel
{
    use ProvidesOptions;

    /** Prihlášky a rezervácie na akcie (EventSignupOrganizerNotice). */
    case Signups = 'signups';

    /** Správy od návštevníkov (MessageReceived). */
    case Messages = 'messages';

    /** Otázky a podnety z nástenky podujatia (QuestionReceived). */
    case Questions = 'questions';

    /** Kontroly obsahu a profilu (ContentReviewNotice, AttributeIssueNotice, ProfileCompleted). */
    case Reviews = 'reviews';

    public function label(): string
    {
        return __('canal_notifications.'.$this->value);
    }

    /** Predvoľba pre rolu, kým si ju člen (alebo vlastník) nezmení. */
    public function defaultFor(CanalRole $role): bool
    {
        return match ($role) {
            CanalRole::Owner => true,
            CanalRole::Editor => in_array($this, [self::Signups, self::Questions], true),
            CanalRole::Checkin => false,
        };
    }
}
