<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Event;
use App\Support\PublicUrl;

/** Otázka návštevníka na podujatie kanála bez správcu (CanalRelay). */
class QuestionRelayed extends RelayedToCanal
{
    public function __construct(
        public readonly Event $event,
        Canal $canal,
        string $audience,
        string $body,
        string $askerName,
        string $askerEmail,
        string $claimUrl,
        string $unsubscribeUrl,
        string $organizerEmail,
    ) {
        parent::__construct($canal, $audience, $body, $askerName, $askerEmail, $claimUrl, $unsubscribeUrl, $organizerEmail);
    }

    public function accountKind(): string
    {
        return 'canal.question_relay';
    }

    protected function textKey(): string
    {
        return 'mail.question_relayed';
    }

    protected function targetParams(): array
    {
        return ['event' => $this->event->name ?: __('mail.common.event_fallback')];
    }

    protected function targetUrl(): string
    {
        return PublicUrl::event($this->event);
    }
}
