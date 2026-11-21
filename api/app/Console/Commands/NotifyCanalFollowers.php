<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Subscription;
use App\Notifications\CanalNewEvents;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sledovateľom kanála („Sledovať organizátora") pošle súhrn akcií, ktoré
 * kanál odvtedy zverejnil.
 *
 * Plánovaný príkaz, nie job pri zverejnení: hosting nemá stály worker (fronta
 * beží cez webcron a môže byť aj `sync`), takže na oneskorený job sa spoľahnúť
 * nedá. Príkaz navyše prirodzene zbiera dávku — import alebo séria termínov
 * zverejní veľa akcií naraz a sledovateľ z nich dostane jeden e-mail.
 *
 * Pravidlá:
 * - akcia musí byť zverejnená aspoň {@see self::SETTLE_MINUTES} minút — kto
 *   hneď po zverejnení zistí preklep a akciu stiahne, nikoho nevyrušil;
 * - len nadchádzajúce akcie (alebo bez termínu) — oznamovať, čo už bolo, nemá zmysel;
 * - každá akcia najviac raz (`events.followers_notified_at`), aj keď ju
 *   organizátor stiahne a znova zverejní;
 * - sledovateľ dostane len akcie zverejnené po tom, čo začal sledovať.
 */
class NotifyCanalFollowers extends Command
{
    public const SETTLE_MINUTES = 10;

    protected $signature = 'app:canal-followers-notify';

    protected $description = 'E-mail followers of a canal about its newly published events';

    public function handle(): int
    {
        $pending = Event::query()
            ->awaitingFollowerNotice()
            ->where('status', ModelStatus::Published->value)
            ->whereNotNull('canal_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->subMinutes(self::SETTLE_MINUTES))
            ->get(['id', 'canal_id', 'start_at', 'end_at', 'published_at']);

        $mails = 0;

        foreach ($pending->groupBy('canal_id') as $canalId => $events) {
            $mails += $this->notifyCanal((int) $canalId, $events);

            // Aj bez sledovateľov a aj minulé akcie — inak by sa pri každom
            // behu prechádzali znova. `toBase()`: bez `updated_at` a observerov,
            // je to interná poistka, nie zmena akcie.
            Event::query()->whereKey($events->modelKeys())->toBase()->update(['followers_notified_at' => now()]);
        }

        $this->info("Canal followers notified: {$mails} e-mails (events: {$pending->count()})");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function notifyCanal(int $canalId, Collection $events): int
    {
        $upcoming = $events->filter(fn (Event $event) => $event->start_at === null || $event->start_at->isFuture());

        if ($upcoming->isEmpty()) {
            return 0;
        }

        $canal = Canal::query()->find($canalId);

        if ($canal === null) {
            return 0;
        }

        $subscriptions = Subscription::query()
            ->active()
            ->where('subscribable_type', Canal::class)
            ->where('subscribable_id', $canalId)
            ->whereNotNull('confirmed_at')
            ->get();

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $ids = $upcoming
                ->filter(fn (Event $event) => $event->published_at->gte($subscription->confirmed_at))
                ->modelKeys();

            if ($ids === []) {
                continue;
            }

            $notification = new CanalNewEvents($canal, array_values($ids), $subscription);

            if ($subscription->locale) {
                $notification->locale($subscription->locale);
            }

            Notification::route('mail', $subscription->email)->notify($notification);
            $sent++;
        }

        return $sent;
    }
}
