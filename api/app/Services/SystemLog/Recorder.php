<?php

namespace App\Services\SystemLog;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Zápis do denníka udalostí (tabuľka system_logs).
 *
 *     Recorder::warning('youtube', 'source_disabled', 'Import vypnutý', subject: $canal);
 *
 * Denník je pomôcka, nie súčasť behu — keď zápis zlyhá (tabuľka chýba,
 * DB je preťažená), udalosť skončí v súborovom logu a request či job
 * pokračuje ďalej.
 */
class Recorder
{
    /** Strop JSON kontextu, aby jedna výnimka so stack trace nenafúkla tabuľku. */
    private const CONTEXT_LIMIT = 4000;

    public static function info(string $channel, string $event, string $message = '', ...$options): ?SystemLog
    {
        return static::record($channel, $event, $message, 'info', ...$options);
    }

    public static function warning(string $channel, string $event, string $message = '', ...$options): ?SystemLog
    {
        return static::record($channel, $event, $message, 'warning', ...$options);
    }

    public static function error(string $channel, string $event, string $message = '', ...$options): ?SystemLog
    {
        return static::record($channel, $event, $message, 'error', ...$options);
    }

    /**
     * @param  string  $event  bez kanála (`sent`), uloží sa ako `mail.sent`
     */
    public static function record(
        string $channel,
        string $event,
        string $message = '',
        string $level = 'info',
        ?string $status = null,
        ?string $recipient = null,
        ?int $userId = null,
        ?Model $subject = null,
        array $context = [],
        ?string $ip = null,
    ): ?SystemLog {
        try {
            return SystemLog::create([
                'level' => in_array($level, SystemLog::LEVELS, true) ? $level : 'info',
                'channel' => Str::limit($channel, 32, ''),
                'event' => Str::limit($channel . '.' . $event, 64, ''),
                'status' => $status,
                'message' => Str::limit($message, 250),
                'recipient' => $recipient !== null ? Str::limit($recipient, 191, '') : null,
                'user_id' => $userId,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'context' => static::context($context),
                'ip' => $ip,
            ]);
        } catch (Throwable $e) {
            Log::warning('SystemLog: záznam sa nepodarilo uložiť', [
                'event' => $channel . '.' . $event,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pre výpadky cudzích služieb, ktoré sa opakujú pri každom volaní:
     * pravda len pri prvom volaní s daným kľúčom v okne `$minutes`.
     *
     *     if (Recorder::onceIn(60, 'portal-down')) Recorder::warning(...);
     */
    public static function onceIn(int $minutes, string $key): bool
    {
        try {
            return Cache::add('system-log:' . $key, true, now()->addMinutes($minutes));
        } catch (Throwable) {
            return true;
        }
    }

    /** Stručný opis výnimky do kontextu — bez celého stack trace. */
    public static function exception(Throwable $e): array
    {
        return [
            'exception' => get_class($e),
            'error' => Str::limit($e->getMessage(), 1000),
            'at' => basename($e->getFile()) . ':' . $e->getLine(),
        ];
    }

    private static function context(array $context): ?array
    {
        $context = array_filter($context, fn ($value) => $value !== null && $value !== '' && $value !== []);

        if ($context === []) {
            return null;
        }

        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false || strlen($json) <= self::CONTEXT_LIMIT) {
            return $context;
        }

        // Priveľký kontext: dlhé reťazce sa skrátia, zvyšok ostane čitateľný.
        return array_map(
            fn ($value) => is_string($value) ? Str::limit($value, 500) : (is_array($value) ? Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE), 500) : $value),
            $context
        );
    }
}
