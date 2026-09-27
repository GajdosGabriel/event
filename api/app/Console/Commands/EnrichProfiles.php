<?php

namespace App\Console\Commands;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\ProfileEnrichment;
use App\Models\Venue;
use App\Services\Profiles\ProfileEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EnrichProfiles extends Command
{
    protected $signature = 'app:profiles-enrich {--limit= : Maximum profilov v dávke}';

    protected $description = 'Doplní chýbajúce údaje organizátorov a miest vrátane overenej polohy';

    public function handle(ProfileEnricher $service): int
    {
        $lock = Cache::lock('profiles-enrich:batch', 180);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            $start = microtime(true);
            $limit = max(1, min(20, (int) ($this->option('limit') ?? config('profile_enrichment.batch', 2))));
            $seconds = max(1, (int) config('profile_enrichment.max_seconds', 45));
            // Retry email without making another paid research call.
            $pending = ProfileEnrichment::with('subject')->whereNotNull('completed_at')
                ->whereNull('notification_completed_at')->where('notification_attempts', '<', 3)
                ->where(fn ($q) => $q->whereNull('notification_retry_at')->orWhere('notification_retry_at', '<=', now()))
                ->oldest('id')->limit($limit)->get();
            foreach ($pending as $audit) {
                if (($audit->subject instanceof Canal || $audit->subject instanceof Venue) && $service->eligible($audit->subject)) {
                    $service->run($audit->subject);
                } else {
                    $audit->update(['notification_completed_at' => now()]);
                }
                if (microtime(true) - $start >= $seconds) {
                    return self::SUCCESS;
                }
            }
            if (! config('profile_enrichment.enabled', true)) {
                return self::SUCCESS;
            }
            $candidates = collect();
            foreach ([Canal::class, Venue::class] as $class) {
                $model = new $class;
                $query = $class::query()->whereIn('status', [ModelStatus::Published->value, ModelStatus::Draft->value])
                    ->where('created_at', '<=', now()->subMinutes((int) config('profile_enrichment.delay_minutes', 120)))
                    ->whereNotExists(function ($q) use ($model) {
                        $q->selectRaw('1')->from('profile_enrichments')
                            ->where('subject_type', $model->getMorphClass())
                            ->whereColumn('subject_id', $model->getTable().'.id')
                            ->where(fn ($q) => $q->whereNotNull('completed_at')
                                ->orWhere('attempts', '>=', (int) config('profile_enrichment.max_attempts', 3))
                                ->orWhere('retry_at', '>', now()));
                    });
                if ($model instanceof Canal) {
                    $query->where('identity_mode', CanalIdentityMode::Organization->value);
                } else {
                    $query->where(fn ($q) => $q->whereNull('category')->orWhere('category', '<>', 'fallback'));
                }
                $candidates = $candidates->concat($query->oldest('created_at')->oldest('id')->limit($limit)->get());
            }
            foreach ($candidates->sortBy('created_at')->take($limit) as $subject) {
                $service->run($subject);
                if (microtime(true) - $start >= $seconds) {
                    break;
                }
            }
            $this->info('Kontrola profilov dokončená.');

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
