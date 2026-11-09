<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nastavenia meniteľné z adminu bez zásahu do .env na serveri.
 *
 * Kľúč je názov configu, ktorý hodnota prepisuje — kým ju admin neuloží,
 * platí config (a cez neho .env). Meniť sa dajú len kľúče z EDITABLE.
 * Hodnoty sa držia v cache; zápis ju zneplatní pre všetky procesy.
 */
final class AppSettings
{
    private const CACHE_KEY = 'app-settings';

    /** Kľúč → typ hodnoty. */
    public const EDITABLE = [
        'profile_enrichment.enabled' => 'bool',
        'profile_enrichment.monthly_limit_usd' => 'float',
        'canals.emails.discovery.ai' => 'bool',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::stored();

        return array_key_exists($key, $values) ? $values[$key] : config($key, $default);
    }

    /** @param  array<string, mixed>  $values */
    public static function set(array $values, ?int $userId = null): void
    {
        $rows = [];
        foreach ($values as $key => $value) {
            $type = self::EDITABLE[$key] ?? throw new \InvalidArgumentException("Nastavenie {$key} sa nedá meniť.");
            $rows[] = [
                'key' => $key,
                'value' => json_encode($type === 'bool' ? (bool) $value : (float) $value),
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('app_settings')->upsert($rows, ['key'], ['value', 'updated_by', 'updated_at']);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            // Pred migráciou (nasadenie, čerstvá inštalácia) platí config.
            if (! Schema::hasTable('app_settings')) {
                return [];
            }

            return DB::table('app_settings')->whereIn('key', array_keys(self::EDITABLE))
                ->pluck('value', 'key')->map(fn ($value) => json_decode((string) $value, true))->all();
        });
    }
}
