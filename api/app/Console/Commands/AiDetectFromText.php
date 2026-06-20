<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\OpenAI\Detector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AiDetectFromText extends Command
{
    protected $signature = 'app:ai-detect-from-text
        {event_id : ID eventu, ktorého body text sa analyzuje}
        {--save : Ulozi AI vysledok a priradi canal_id + venue_id do eventu}';

    protected $description = 'Spustí AI detekciu textu pre jeden event a vypíše výsledok (na testovanie kvality)';

    public function handle(Detector $detector): int
    {
        $eventId = (int) $this->argument('event_id');
        $shouldSave = (bool) $this->option('save');

        /** @var Event|null $event */
        $event = Event::withTrashed()->find($eventId);

        if (! $event instanceof Event) {
            $this->error("Event s ID {$eventId} neexistuje.");

            return self::FAILURE;
        }

        $text = $event->body ?? $event->body_ai ?? null;

        if (! is_string($text) || trim($text) === '') {
            $this->warn("Event ID {$eventId} nemá žiadny body text (body ani body_ai sú prázdne).");

            return self::FAILURE;
        }

        $this->info("Spúšťam AI detekciu pre event ID {$eventId} (name: {$event->name}) ...");
        $this->newLine();

        $result = $detector->detectFromText($text);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (! ($result['success'] ?? false)) {
            $this->newLine();
            $this->error('Detekcia zlyhala: ' . ($result['error'] ?? 'Neznáma chyba'));

            return self::FAILURE;
        }

        if ($shouldSave) {
            $this->newLine();
            $this->info('Ukladam AI vysledok do databazy...');

            $this->saveDetectionResult($event, $result);

            $this->info('Ulozene: event bol aktualizovany (body_ai, meta, canal_id, venue_id, user_id).');
        }

        $this->newLine();
        $this->info('Detekcia dokončená.');

        return self::SUCCESS;
    }

    private function saveDetectionResult(Event $event, array $result): void
    {
        DB::transaction(function () use ($event, $result): void {
            $systemOwner = $this->resolveSystemOwnerUser();

            $eventPayload = is_array($result['event_payload'] ?? null) ? $result['event_payload'] : [];
            $organizerCanal = is_array($result['organizer_canal'] ?? null) ? $result['organizer_canal'] : [];
            $venueDetect = is_array($result['venue_detect'] ?? null) ? $result['venue_detect'] : [];
            $existingCanal = $event->canal_id ? Canal::query()->find((int) $event->canal_id) : null;
            $existingVenue = $event->venue_id ? Venue::query()->find((int) $event->venue_id) : null;

            $organizerName = $this->pickString(
                $organizerCanal['name'] ?? null,
                $eventPayload['organizer']['name'] ?? null,
                $eventPayload['organization'] ?? null,
            );

            $canal = $this->resolveOrCreateCanal($organizerName) ?? $existingCanal;

            if (! $canal instanceof Canal) {
                throw new \RuntimeException('Event nema povinny canal_id a AI nedokazalo urcit organizatora.');
            }

            $this->ensureSystemOwnership($systemOwner, $canal);

            $venue = $this->resolveOrCreateVenue($canal, $eventPayload, $venueDetect) ?? $existingVenue;
            [$startAt, $endAt] = $this->resolveEventTimes($eventPayload, $event);

            $meta = is_array($event->meta) ? $event->meta : [];
            $meta['ai_detect_from_text'] = [
                'processed_at' => now()->toIso8601String(),
                'organizer_name' => $organizerName,
                'saved' => true,
                'result' => $result,
            ];

            $event->update([
                'body_ai' => $this->pickString($result['corrected_text'] ?? null),
                'name' => $this->pickString($eventPayload['title'] ?? null, $event->name) ?? $event->name,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'email' => $this->pickString($eventPayload['email'] ?? null, $event->email),
                'phone' => $this->pickString($eventPayload['phone'] ?? null, $event->phone),
                'canal_id' => $canal->id,
                'venue_id' => $venue?->id ?? $event->venue_id,
                'user_id' => $systemOwner->id,
                'meta' => $meta,
            ]);
        });
    }

    private function resolveSystemOwnerUser(): User
    {
        $user = User::query()->find(1);

        if (! $user instanceof User) {
            throw new \RuntimeException('System owner user_id=1 neexistuje.');
        }

        return $user;
    }

    private function ensureSystemOwnership(User $systemOwner, Canal $canal): void
    {
        $systemOwner->canals()->syncWithoutDetaching([
            $canal->id => [
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $systemOwner->canals()->updateExistingPivot($canal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'updated_at' => now(),
        ]);

        if ((int) ($systemOwner->canal_id ?? 0) !== (int) $canal->id) {
            $systemOwner->forceFill(['canal_id' => $canal->id])->save();
        }
    }

    private function resolveEventTimes(array $eventPayload, Event $event): array
    {
        $payloadStartString = $this->pickString($eventPayload['start_at'] ?? null);
        $payloadEndString = $this->pickString($eventPayload['end_at'] ?? null);

        $startString = $this->pickString(
            $payloadStartString,
            $event->start_at?->format('Y-m-d H:i:s')
        );

        $endString = $this->pickString(
            $payloadEndString,
            $event->end_at?->format('Y-m-d H:i:s')
        );

        $startAt = $this->parseDateTime($startString);
        $endAt = $this->parseDateTime($endString);
        $hasExplicitStartTime = (bool) ($eventPayload['_start_time_explicit'] ?? false);

        if ($hasExplicitStartTime && $startAt instanceof Carbon && $payloadEndString === null) {
            return [
                $startAt->format('Y-m-d H:i:s'),
                null,
            ];
        }

        if ($startAt instanceof Carbon && (! $endAt instanceof Carbon || $endAt->lessThanOrEqualTo($startAt))) {
            if ($hasExplicitStartTime) {
                return [
                    $startAt->format('Y-m-d H:i:s'),
                    null,
                ];
            }

            $allDayStart = $startAt->copy()->startOfDay();
            $allDayEnd = $startAt->copy()->endOfDay();

            return [
                $allDayStart->format('Y-m-d H:i:s'),
                $allDayEnd->format('Y-m-d H:i:s'),
            ];
        }

        return [
            $startAt?->format('Y-m-d H:i:s') ?? $startString,
            $endAt?->format('Y-m-d H:i:s') ?? $endString,
        ];
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveOrCreateCanal(?string $organizerName): ?Canal
    {
        if (! is_string($organizerName) || trim($organizerName) === '') {
            return null;
        }

        $name = trim($organizerName);

        $canal = Canal::query()
            ->where('name', $name)
            ->orWhere('name', 'like', '%' . $name . '%')
            ->orderByDesc('created_at')
            ->first();

        if ($canal instanceof Canal) {
            return $canal;
        }

        return Canal::query()->create([
            'name' => $name,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    private function resolveOrCreateVenue(?Canal $canal, array $eventPayload, array $venueDetect): ?Venue
    {
        if (! $canal instanceof Canal) {
            return null;
        }

        $existingVenue = $venueDetect['existing_venue'] ?? null;
        if (is_array($existingVenue) && isset($existingVenue['id'])) {
            $found = Venue::query()->find((int) $existingVenue['id']);
            if ($found instanceof Venue) {
                $this->attachVenueCanal($found, $canal);

                return $found;
            }
        }

        $venueStorePayload = is_array($venueDetect['venue_store_payload'] ?? null)
            ? $venueDetect['venue_store_payload']
            : [];

        $name = $this->pickString(
            $venueStorePayload['name'] ?? null,
            $eventPayload['venue']['name'] ?? null,
            $eventPayload['building'] ?? null,
        );

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $city = $this->pickString(
            $venueDetect['venue_payload']['city'] ?? null,
            $eventPayload['venue']['city'] ?? null,
            $eventPayload['city'] ?? null,
        );

        $venue = Venue::query()
            ->where('name', $name)
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $canal->id))
            ->orderByDesc('created_at')
            ->first();

        if ($venue instanceof Venue) {
            return $venue;
        }

        $venueFromOtherCanal = Venue::query()
            ->where('name', $name)
            ->orderByDesc('created_at')
            ->first();

        if ($venueFromOtherCanal instanceof Venue) {
            $this->attachVenueCanal($venueFromOtherCanal, $canal);

            return $venueFromOtherCanal;
        }

        $venue = Venue::query()->create([
            'village_id' => (int) ($venueStorePayload['village_id'] ?? 4209),
            'name' => $name,
            'street' => $this->pickString($venueStorePayload['street'] ?? null, $eventPayload['street_and_number'] ?? null),
            'postcode' => $this->pickString($venueStorePayload['postcode'] ?? null),
            'body' => $this->pickString($venueStorePayload['body'] ?? null),
            'website' => $this->pickString($venueStorePayload['website'] ?? null),
            'country' => $this->pickString($venueStorePayload['country'] ?? null, 'Slovakia'),
            'latitude' => $venueStorePayload['latitude'] ?? null,
            'longitude' => $venueStorePayload['longitude'] ?? null,
            'status' => ModelStatus::Draft->value,
            'category' => $city,
        ]);

        $this->attachVenueCanal($venue, $canal);

        return $venue;
    }

    private function attachVenueCanal(Venue $venue, Canal $canal): void
    {
        $venue->assignCanal($canal, isOwner: true);
    }

    private function pickString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
