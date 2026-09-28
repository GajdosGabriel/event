<?php

namespace App\Services\Profiles;

use App\Enums\CanalNotificationTopic;
use App\Services\Canals\CanalRecipients;
use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\ProfileEnrichment;
use App\Models\Venue;
use App\Notifications\ProfileCompleted;
use App\Services\SystemLog\Recorder;
use App\Support\PlaceholderNames;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProfileEnricher
{
    public function __construct(private ProfileResearch $research, private ProfileCoordinates $coordinates) {}

    public function eligible(Canal|Venue $subject): bool
    {
        return ! $subject->trashed()
            && in_array($subject->status, [ModelStatus::Published, ModelStatus::Draft], true)
            && ($subject instanceof Canal ? $subject->identity_mode === CanalIdentityMode::Organization : $subject->category !== 'fallback')
            && $subject->created_at?->lte(now()->subMinutes((int) config('profile_enrichment.delay_minutes', 120)));
    }

    public function missing(Canal|Venue $subject): array
    {
        $fields = array_values(array_filter(ProfileResearch::FIELDS,
            fn ($field) => preg_replace('/[\s\x{00a0}]+/u', '', html_entity_decode(strip_tags((string) $subject->$field))) === ''));
        $municipalityKey = $subject instanceof Canal ? 'municipality_id' : 'village_id';
        if ($subject->$municipalityKey === null || (int) $subject->$municipalityKey === Municipality::nationwideId()) {
            $fields[] = 'city';
        }

        return $fields;
    }

    public function budgetAvailable(): bool
    {
        $limit = max(0, (float) config('profile_enrichment.monthly_limit_usd', 1));

        return $limit === 0.0 || AiUsage::where('feature', ProfileResearch::FEATURE)
            ->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd') < $limit;
    }

    public function run(Canal|Venue $subject): void
    {
        Cache::lock('profile-enrichment:'.$subject->getMorphClass().':'.$subject->id, 180)->get(function () use ($subject) {
            $subject = $subject->fresh();
            if (! $subject || ! $this->eligible($subject)) {
                return;
            }
            $audit = ProfileEnrichment::firstOrCreate(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id]);
            if (PlaceholderNames::matches((string) $subject->name)) {
                $audit->update(['completed_at' => now(), 'notification_completed_at' => now()]);

                return;
            }
            if ($audit->completed_at) {
                $this->notify($subject, $audit);

                return;
            }
            if (! config('profile_enrichment.enabled', true) || $audit->retry_at?->isFuture()
                || $audit->attempts >= (int) config('profile_enrichment.max_attempts', 3)) {
                return;
            }
            $missing = $this->missing($subject);
            if ($missing !== [] && (! (string) config('openai.api_key') || ! $this->budgetAvailable())) {
                return;
            }
            $audit->update(['attempts' => $audit->attempts + 1, 'retry_at' => now()->addHours((int) config('profile_enrichment.retry_hours', 6))]);
            try {
                $found = $missing !== [] ? $this->research->research($subject, $missing) : [];
                $position = $this->coordinates->resolve($subject, $found);
                DB::transaction(function () use ($subject, $audit, $found, $position) {
                    $current = $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->first();
                    if (! $current || ! $this->eligible($current)) {
                        $audit->update(['completed_at' => now(), 'notification_completed_at' => now()]);

                        return;
                    }
                    // Identity or location may have changed while the network calls ran.
                    $identity = ['name', 'municipality_id', 'village_id', 'street', 'postcode', 'country', 'website', 'latitude', 'longitude', 'coordinates_source'];
                    if ($subject->only($identity) !== $current->only($identity)) {
                        $audit->update(['retry_at' => now()->addMinutes(120), 'last_error' => 'Profil bol počas vyhľadávania upravený.']);

                        return;
                    }
                    $changes = [];
                    $evidence = [];
                    foreach (array_intersect($this->missing($current), ProfileResearch::FIELDS) as $field) {
                        if (isset($found[$field])) {
                            $changes[$field] = $found[$field]['value'];
                            $evidence[$field] = $found[$field];
                        }
                    }
                    if (in_array('city', $this->missing($current), true) && isset($found['city'])) {
                        // Map only an unambiguous official municipality; never synthesize an ID.
                        $matches = Municipality::where('fullname', $found['city']['value'])->limit(2)->get();
                        if ($matches->count() === 1 && $matches->first()->id !== Municipality::nationwideId()) {
                            $key = $current instanceof Canal ? 'municipality_id' : 'village_id';
                            $changes[$key] = $matches->first()->id;
                            $evidence[$key] = $found['city'];
                        }
                    }
                    if ($position !== [] && $this->coordinates->needsLookup($current)) {
                        foreach (['latitude', 'longitude', 'coordinates_source'] as $field) {
                            // In a partial pair keep the coordinate already present.
                            if (in_array($field, ['latitude', 'longitude'], true)
                                && ($current->latitude === null || $current->longitude === null) && $current->$field !== null) {
                                continue;
                            }
                            $changes[$field] = $position[$field];
                            $evidence[$field] = ['value' => $position[$field], 'source_url' => $position['source_url'], 'evidence' => $position['evidence']];
                        }
                    }
                    // Preserve legacy partial pins: the model saving hook would otherwise replace both coordinates.
                    if (($current->latitude === null xor $current->longitude === null) && $position === []) {
                        $audit->update(['last_error' => 'Neúplnú polohu sa nepodarilo bezpečne doplniť.']);

                        return;
                    }
                    if ($changes !== []) {
                        $current->forceFill($changes)->save();
                    }
                    $recipient = $changes !== [] ? $current->attributeIssueRecipient() : null;
                    $audit->update([
                        'completed_at' => now(), 'last_error' => null,
                        'changes' => $changes, 'evidence' => $evidence, 'recipient_id' => $recipient?->id,
                        'notification_completed_at' => $recipient ? null : now(),
                    ]);
                    if ($changes !== []) {
                        Recorder::info('profiles', 'enriched', 'Doplnené chýbajúce údaje: '.$current->name,
                            subject: $current, status: 'ok', context: ['fields' => array_keys($changes), 'sources' => array_column($evidence, 'source_url'), 'audit_id' => $audit->id]);
                    }
                });
                if ($fresh = $subject->fresh()) {
                    $this->notify($fresh, $audit->fresh());
                }
            } catch (Throwable $e) {
                $audit->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
                Recorder::warning('profiles', 'enrichment_failed', 'Dopĺňanie profilu zlyhalo.', subject: $subject,
                    context: ['audit_id' => $audit->id, 'error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        });
    }

    private function notify(Canal|Venue $subject, ProfileEnrichment $audit): void
    {
        if (! $audit->completed_at || $audit->notification_completed_at || ! $audit->changes
            || $audit->notification_retry_at?->isFuture() || $audit->notification_attempts >= 3) {
            return;
        }
        $recipient = $subject->attributeIssueRecipient();
        if (! $recipient || (int) $recipient->id !== (int) $audit->recipient_id) {
            $audit->update(['notification_completed_at' => now()]);

            return;
        }
        $audit->update(['notification_attempts' => $audit->notification_attempts + 1, 'notification_retry_at' => now()->addHour()]);
        try {
            $notice = new ProfileCompleted($subject, $audit->changes);
            $recipients = app(CanalRecipients::class);
            $canal = $recipients->canalOf($subject);

            if ($recipients->mayNotify($canal, $recipient, CanalNotificationTopic::Reviews, $notice, $subject)) {
                $recipient->notify($notice);
            }

            $recipients->fanOut($canal, CanalNotificationTopic::Reviews, $notice, [$recipient], $subject);
            $audit->update(['notified_at' => now(), 'notification_completed_at' => now()]);
        } catch (Throwable $e) {
            Recorder::warning('profiles', 'notification_failed', 'E-mail o doplnení profilu sa nepodarilo odoslať.', subject: $subject,
                context: ['audit_id' => $audit->id]);
        }
    }
}
