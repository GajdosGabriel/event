<?php

namespace App\Services\Imports;

use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\PromptProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Píše popis kanálu/miesta, ktoré vzniklo importom.
 *
 * Bez neho by importované kanály mali všetky rovnaký systémový text a miesta
 * prázdny popis. AI sa pýtame len na to, čo vie z názvu spoľahlivo doplniť;
 * keď nevie (alebo je vypnutá), kanál dostane neutrálnu vetu a miesto null.
 */
class ImportedProfileDescriber
{
    private const MAX_LENGTH = 1000;

    public function __construct(
        private readonly ChatGPT $chatGPT = new ChatGPT(),
    ) {}

    public function forCanal(string $name, ?string $sourceOrigin = null): string
    {
        $host = $this->host($sourceOrigin);
        $isSourceBucket = $host !== null && Str::slug($name) === Str::slug($host);

        // Zberný kanál zdroja nie je reálny organizátor — nie je čo popisovať.
        $description = $isSourceBucket
            ? null
            : $this->describe(PromptProfile::KIND_CANAL, $name, $host !== null ? "Podujatia pochádzajú zo zdroja {$host}." : null);

        if ($description !== null) {
            return $description;
        }

        if ($isSourceBucket) {
            return "Zberný kanál pre podujatia zo zdroja {$host}, ktoré sa nepodarilo priradiť konkrétnemu organizátorovi.";
        }

        return $host !== null
            ? "{$name} — organizátor podujatí. Podujatia sú preberané zo zdroja {$host}."
            : "{$name} — organizátor podujatí.";
    }

    public function forVenue(string $name, ?string $city = null): ?string
    {
        return $this->describe(
            PromptProfile::KIND_VENUE,
            $name,
            $city !== null && $city !== '' ? "Obec alebo mesto: {$city}." : null,
        );
    }

    private function describe(string $kind, string $name, ?string $context): ?string
    {
        $name = trim($name);

        if ($name === '' || ! (bool) config('services.imports.describe_with_ai', false)) {
            return null;
        }

        try {
            $description = $this->chatGPT->extractProfileDescription($kind, $name, $context);
        } catch (\Throwable $e) {
            Log::warning('Popis importovaného profilu sa nepodarilo vygenerovať.', [
                'kind' => $kind,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($description === null) {
            return null;
        }

        return Str::limit($description, self::MAX_LENGTH, '');
    }

    private function host(?string $sourceOrigin): ?string
    {
        if (! is_string($sourceOrigin) || trim($sourceOrigin) === '') {
            return null;
        }

        $host = preg_replace('/^https?:\/\//i', '', trim($sourceOrigin)) ?? $sourceOrigin;
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $host = trim(explode('/', $host)[0]);

        return $host !== '' ? $host : null;
    }
}
