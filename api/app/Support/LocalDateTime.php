<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Čas „na nástenných hodinách" organizátora → UTC.
 *
 * Databáza drží UTC. Čas zadaný bez zóny (formulár, AI z plagátu) sa berie ako
 * Europe/Bratislava; čas, ktorý zónu nesie (`Z`, `+02:00`), sa len prevedie.
 * Jediné miesto tohto prepočtu pre wizard (EventStoreRequest) aj plagátový
 * tok (PosterDraftMaterializer), nech obe cesty ukladajú rovnako.
 */
final class LocalDateTime
{
    public const TIMEZONE = 'Europe/Bratislava';

    public static function hasZone(string $value): bool
    {
        return preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($value)) === 1;
    }

    /**
     * @throws \Carbon\Exceptions\InvalidFormatException pri nepoužiteľnom vstupe
     */
    public static function toUtc(string $value): Carbon
    {
        return Carbon::parse(trim($value), self::hasZone($value) ? null : self::TIMEZONE)->utc();
    }

    public static function toUtcOrNull(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return self::toUtc($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
