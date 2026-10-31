<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Event;
use App\Support\PublicUrl;

/** Správa návštevníka kanálu bez správcu alebo k jeho podujatiu (CanalRelay). */
class MessageRelayed extends RelayedToCanal
{
    public function __construct(
        public readonly Canal|Event $target,
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
        return 'canal.message_relay';
    }

    protected function textKey(): string
    {
        return 'mail.message_relayed';
    }

    protected function targetParams(): array
    {
        return ['target' => (string) $this->target->name];
    }

    protected function targetUrl(): string
    {
        return $this->target instanceof Event
            ? PublicUrl::event($this->target)
            : PublicUrl::canal($this->target);
    }
}
