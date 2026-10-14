<?php

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventSeriesManager;
use App\Services\Publishing\EventDependencyPublisher;
use App\Support\WeeklyRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Koncepty z importu tkkbs.sk staršie ako týždeň (stav k 14. 10. 2026) —
 * všetky bez termínu, takže sa zverejniť nedali.
 *
 *  - Oznamy o prihlasovaní (animátorská škola, UTV, kurz GPS, BAŠka …) termín
 *    podujatia nemajú vôbec a uzávierky prihlášok už uplynuli -> archív.
 *  - Týždenné podujatia („každý pondelok o 18:00") import nevedel premeniť na
 *    dátum. Odteraz to robí WeeklyRecurrence; tu dostanú najbližší pondelok
 *    a sériu termínov do konca zimného semestra, a idú von aj s miestom.
 */
return new class extends Migration
{
    private const ANNOUNCEMENTS = [205, 296, 10037, 10707, 10731, 10732, 10790];

    private const WEEKLY = [11040, 11077];

    /** Posledný pondelok pred Vianocami — koniec semestra prednášok v UPC. */
    private const SERIES_UNTIL = '2026-12-21 23:59:59';

    private const DURATION_HOURS = 2;

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        DB::table('events')
            ->whereIn('id', self::ANNOUNCEMENTS)
            ->where('status', ModelStatus::Draft->value)
            ->whereNull('start_at')
            ->update(['status' => ModelStatus::Archived->value, 'updated_at' => now()]);

        foreach (self::WEEKLY as $id) {
            $this->publishWeekly($id);
        }
    }

    public function down(): void {}

    private function publishWeekly(int $id): void
    {
        $event = Event::query()->find($id);

        if ($event === null || $event->status !== ModelStatus::Draft || $event->start_at !== null) {
            return;
        }

        $first = WeeklyRecurrence::nextOccurrence(strip_tags((string) $event->body), now());
        $owner = User::query()->find($event->user_id);

        if ($first === null || $owner === null) {
            return;
        }

        DB::transaction(function () use ($event, $first, $owner): void {
            $this->publish($event, $first);

            $series = app(EventSeriesManager::class);
            $until = CarbonImmutable::parse(self::SERIES_UNTIL, 'Europe/Bratislava');

            // addWeek v miestnom čase, aby prechod na zimný čas neposunul hodinu.
            for ($at = $first->setTimezone('Europe/Bratislava')->addWeek(); $at->lessThanOrEqualTo($until); $at = $at->addWeek()) {
                $occurrence = $series->addOccurrence($owner, $event, $at->utc(), $at->addHours(self::DURATION_HOURS)->utc());
                $this->publish($occurrence, $at->utc());
            }

            (new EventDependencyPublisher)->settle($event->fresh());
        });
    }

    private function publish(Event $event, CarbonImmutable $startAt): void
    {
        $event->forceFill([
            'start_at' => $startAt,
            'end_at' => $startAt->addHours(self::DURATION_HOURS),
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ])->save();
    }
};
