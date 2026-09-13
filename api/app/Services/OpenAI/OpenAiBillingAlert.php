<?php

namespace App\Services\OpenAI;

use App\Models\User;
use App\Notifications\OpenAiBillingIssue;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Upozornenie super-adminov, keď OpenAI odmietne volanie pre kredit alebo
 * fakturáciu („You have no credits remaining…").
 *
 * Takú chybu nevyrieši ďalší pokus ani oprava kódu — AI funkcie stoja, kým
 * niekto nedoplní kredit, a v logu importu sa stratí medzi ostatnými chybami.
 *
 * Jeden import urobí stovky volaní a každé skončí rovnakou chybou, preto ide
 * e-mail najviac raz za `openai.billing_alert_cooldown_hours`.
 */
class OpenAiBillingAlert
{
    /** `error.type` alebo `error.code` z odpovede OpenAI, ktoré znamenajú peniaze. */
    private const BILLING_ERRORS = [
        'insufficient_quota',
        'credit_balance_exhausted',
        'billing_hard_limit_reached',
        'billing_not_active',
    ];

    private const CACHE_KEY = 'openai:billing-alert-sent';

    public function reportIfBillingError(Response $response): void
    {
        $error = $response->json('error');

        if (! is_array($error)) {
            return;
        }

        $type = (string) ($error['type'] ?? '');
        $code = (string) ($error['code'] ?? '');

        if (! in_array($type, self::BILLING_ERRORS, true) && ! in_array($code, self::BILLING_ERRORS, true)) {
            return;
        }

        $cooldownHours = max(1, (int) config('openai.billing_alert_cooldown_hours', 6));

        // Cache::add je atomické — súbežné workery importu nepošlú mail každý.
        if (! Cache::add(self::CACHE_KEY, true, now()->addHours($cooldownHours))) {
            return;
        }

        // Upozornenie nesmie zmeniť chybu, ktorú volajúci dostane od ChatGPT.
        try {
            $recipients = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))
                ->whereNotNull('email')
                ->get();

            if ($recipients->isEmpty()) {
                Log::critical('OpenAI billing error, but there is no super-admin to notify.', ['type' => $type, 'code' => $code]);

                return;
            }

            Notification::send($recipients, new OpenAiBillingIssue(
                status: $response->status(),
                code: $code !== '' ? $code : $type,
                message: (string) ($error['message'] ?? ''),
                cooldownHours: $cooldownHours,
            ));
        } catch (\Throwable $e) {
            Log::error('OpenAI billing alert could not be sent: '.$e->getMessage());
        }
    }
}
