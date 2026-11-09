<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Profiles\ProfileEnricher;
use App\Services\SystemLog\Recorder;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Limity AI vyhľadávania na stránke Spotreba AI: mesačný rozpočet
 * (ProfileResearch — dopĺňanie profilov aj hľadanie e-mailov kanálov)
 * a vypínače oboch funkcií. Uložené hodnoty prepisujú config/.env.
 */
class AiLimitsController extends Controller
{
    private const FIELDS = [
        'profileEnrichment' => 'profile_enrichment.enabled',
        'emailDiscoveryAi' => 'canals.emails.discovery.ai',
        'monthlyLimitUsd' => 'profile_enrichment.monthly_limit_usd',
    ];

    public function show(ProfileEnricher $enricher): JsonResponse
    {
        return response()->json(['data' => $this->payload($enricher)]);
    }

    public function update(Request $request, ProfileEnricher $enricher): JsonResponse
    {
        $validated = $request->validate([
            'profileEnrichment' => ['required', 'boolean'],
            'emailDiscoveryAi' => ['required', 'boolean'],
            // Zastavenie je vypínač; nula (= bez limitu) sa cez admin nastaviť nedá.
            'monthlyLimitUsd' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $before = $this->values();
        $values = [];
        foreach (self::FIELDS as $field => $key) {
            $values[$key] = $validated[$field];
        }
        AppSettings::set($values, $request->user()?->id);

        Recorder::info('admin', 'ai_limits_updated', 'Zmena limitov AI vyhľadávania',
            status: 'ok', userId: $request->user()?->id, context: ['before' => $before, 'after' => $this->values()],
            ip: $request->ip());

        return response()->json(['data' => $this->payload($enricher)]);
    }

    /** @return array<string, mixed> */
    private function payload(ProfileEnricher $enricher): array
    {
        return $this->values() + [
            'apiKey' => (string) config('openai.api_key') !== '',
            'budget' => $enricher->budget(),
        ];
    }

    /** @return array{profileEnrichment: bool, emailDiscoveryAi: bool, monthlyLimitUsd: float} */
    private function values(): array
    {
        return [
            'profileEnrichment' => (bool) AppSettings::get(self::FIELDS['profileEnrichment'], true),
            'emailDiscoveryAi' => (bool) AppSettings::get(self::FIELDS['emailDiscoveryAi'], true),
            'monthlyLimitUsd' => (float) AppSettings::get(self::FIELDS['monthlyLimitUsd'], 1),
        ];
    }
}
