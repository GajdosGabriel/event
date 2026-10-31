<?php

namespace App\Services\Account;

use App\Models\CanalEmail;
use App\Models\CanalOutreach;
use App\Models\EmailSuppression;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;

/**
 * Udalosti Emailingu z Accountu (`mailing.*`).
 *
 *  - `mailing.unsubscribed` — človek sa odhlásil odkazom v kampani; Event si
 *    adresu zapíše do EmailSuppression, aby ju neoslovil ani vlastným mailom,
 *  - `mailing.delivery.sent|failed|bounced` — stav ponuky prevzatia kanála
 *    (párovanie podľa `dedupe_key`); nedoručiteľná adresa sa vyradí
 *    z kanála aj z ďalších oslovení (CanalEmails::recordBounce).
 *
 * Doručenia iných kampaní (registrácie) sa ticho preskočia.
 */
class AccountMailingWebhooks
{
    private const DELIVERY_STATUS = [
        'mailing.delivery.sent' => CanalOutreach::SENT,
        'mailing.delivery.failed' => CanalOutreach::FAILED,
        'mailing.delivery.bounced' => CanalOutreach::BOUNCED,
    ];

    public function __construct(private readonly CanalEmails $emails) {}

    /** @param  array<string, mixed>  $data */
    public function handle(string $event, array $data): void
    {
        if ($event === 'mailing.unsubscribed') {
            $this->unsubscribed(CanalEmails::normalize($data['contact']['email'] ?? null));

            return;
        }

        if (isset(self::DELIVERY_STATUS[$event])) {
            $this->delivery($event, (array) ($data['delivery'] ?? []));
        }
    }

    private function unsubscribed(?string $email): void
    {
        if ($email === null) {
            return;
        }

        if (EmailSuppression::add($email, 'unsubscribe', 'account')->wasRecentlyCreated) {
            Recorder::info('mail', 'unsubscribed', 'Odhlásenie z e-mailingu v Accounte',
                status: 'ok',
                recipient: $email,
                context: ['source' => 'account'],
            );
        }
    }

    /** @param  array<string, mixed>  $delivery */
    private function delivery(string $event, array $delivery): void
    {
        if (($delivery['ref']['type'] ?? null) !== 'canal' || empty($delivery['dedupe_key'])) {
            return;
        }

        $outreach = CanalOutreach::query()->where('dedupe_key', $delivery['dedupe_key'])->first();
        $status = self::DELIVERY_STATUS[$event];

        $outreach?->forceFill(['status' => $status])->save();

        if ($status === CanalOutreach::BOUNCED) {
            $this->emails->recordBounce($delivery['email'] ?? $outreach?->email, CanalEmail::BOUNCE_HARD, $delivery['error'] ?? null);
        }

        if ($outreach) {
            $result = match ($status) {
                CanalOutreach::SENT => 'e-mail odišiel',
                CanalOutreach::FAILED => 'e-mail sa nepodarilo odoslať',
                CanalOutreach::BOUNCED => 'e-mail sa vrátil ako nedoručiteľný',
            };

            Recorder::info('canals', 'outreach_'.$status, "Kanál {$outreach->canal?->name}: ponuka prevzatia — {$result} (oslovenie #{$outreach->id})",
                status: $status,
                recipient: $outreach->email,
                subject: $outreach->canal,
                context: ['outreach_id' => $outreach->id, 'delivery' => $delivery['id'] ?? null, 'error' => $delivery['error'] ?? null],
            );
        }
    }
}
