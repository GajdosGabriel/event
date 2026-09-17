<?php

namespace App\Services\OpenAI;

use App\Models\AiUsage;
use App\Models\Canal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Evidencia spotreby OpenAI. Zapisuje ChatGPT::chatComplete — jediné miesto,
 * kadiaľ idú všetky volania — takže nová funkcia sa započíta bez úprav.
 *
 * Kanál a predmet (podujatie, miesto…) volanie samo nepozná. Služby, ktoré ho
 * poznajú, ho nastavia cez within(); inak ostane prázdny a prehľad volanie
 * priradí len k operácii a používateľovi.
 */
class AiUsageRecorder
{
    /** Metóda ChatGPT → operácia v prehľade. Súkromné pomocné metódy patria k verejnej. */
    public const FEATURES = [
        'extractData' => 'event_detection',
        'extractDataFromPoster' => 'poster_detection',
        'extractCopywriter' => 'copywriter',
        'copywriteChunk' => 'copywriter',
        'copywriteLongText' => 'copywriter',
        'formatAsHtml' => 'html_format',
        'extractTextEdit' => 'text_edit',
        'extractTags' => 'tags',
        'extractVenueDetails' => 'venue_detection',
        'extractCanalName' => 'canal_name',
        'extractProfileDescription' => 'profile_description',
        'extractContentReview' => 'content_review',
    ];

    private ?Model $subject = null;

    /**
     * Spustí $callback s predmetom, ku ktorému sa volania v ňom priradia.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function within(?Model $subject, callable $callback): mixed
    {
        $previous = $this->subject;
        $this->subject = $subject;

        try {
            return $callback();
        } finally {
            $this->subject = $previous;
        }
    }

    /**
     * Nastaví predmet do konca požiadavky / príkazu — pre dlhé metódy, ktoré
     * by sa do within() obaľovali ťažko. Recorder je `scoped`, ďalšia úloha
     * frontu začína bez predmetu.
     */
    public function setSubject(?Model $subject): void
    {
        $this->subject = $subject;
    }

    /**
     * Zapíše volanie. Evidencia nesmie zhodiť samotnú AI operáciu — chyba
     * zápisu sa len zaloguje.
     *
     * @param  array<string, mixed>|null  $usage  pole `usage` z odpovede OpenAI
     */
    public function record(string $feature, string $model, ?array $usage, bool $success = true): void
    {
        try {
            $prompt = (int) ($usage['prompt_tokens'] ?? 0);
            $completion = (int) ($usage['completion_tokens'] ?? 0);

            AiUsage::query()->create([
                'feature' => $feature,
                'source' => $this->source(),
                'model' => $model,
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'cost_usd' => AiUsage::price($model, $prompt, $completion),
                'success' => $success,
                'user_id' => auth()->id(),
                'canal_id' => $this->canalId(),
                'subject_type' => $this->subject ? class_basename($this->subject) : null,
                'subject_id' => $this->subject?->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Spotrebu OpenAI sa nepodarilo zapísať.', ['error' => $e->getMessage()]);
        }
    }

    /** Operácia podľa metódy ChatGPT, ktorá volanie spustila. */
    public static function featureFromTrace(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
            if (($frame['class'] ?? null) === ChatGPT::class && isset(self::FEATURES[$frame['function']])) {
                return self::FEATURES[$frame['function']];
            }
        }

        return 'other';
    }

    private function canalId(): ?int
    {
        if ($this->subject === null) {
            return null;
        }

        if ($this->subject instanceof Canal) {
            return (int) $this->subject->getKey();
        }

        $canalId = $this->subject->getAttribute('canal_id');

        return $canalId !== null ? (int) $canalId : null;
    }

    private function source(): ?string
    {
        if (app()->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];

            return isset($argv[1]) ? mb_substr('artisan '.$argv[1], 0, 120) : 'console';
        }

        $route = request()->route();

        return $route?->getName() ?? mb_substr(request()->path(), 0, 120);
    }
}
