<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Notifications\EventReminder;
use App\Services\Events\AttendeeDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Pripomienka účastníkom pred akciou (roadmap 3.5).
 *
 * Posiela sa raz — `reminder_sent_at` je poistka proti druhej vlne e-mailov pri
 * ďalšom behu. Zmena `reminder_hours_before` po odoslaní už nič neposiela: druhá
 * pripomienka na tú istú akciu je z pohľadu účastníka spam.
 */
class SendEventReminders extends Command
{
    /**
     * Strop predvýberu — zhoduje sa s maximom vo validácii
     * (TicketingSettingsRequest). Drží dopyt malý aj pri veľkom katalógu.
     */
    private const MAX_HOURS = 336;

    protected $signature = 'app:events-send-reminders';

    protected $description = 'Send reminder e-mails to attendees before the event starts';

    public function handle(AttendeeDirectory $directory): int
    {
        $events = Event::query()
            ->where('status', ModelStatus::Published->value)
            ->whereNotNull('reminder_hours_before')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('start_at')
            ->where('start_at', '>', now())
            ->where('start_at', '<=', now()->addHours(self::MAX_HOURS))
            ->with('venue')
            ->get()
            // Vlastné okno má každé podujatie — do dopytu by sa dalo dostať len
            // porovnaním stĺpca so stĺpcom, čo by bolo SQL navyše pre pár riadkov.
            ->filter(fn (Event $event) => $event->start_at->lte(
                now()->addHours((int) $event->reminder_hours_before)
            ));

        $sent = 0;

        foreach ($events as $event) {
            foreach ($directory->recipients($event) as $recipient) {
                Notification::route('mail', $recipient['email'])
                    ->notify(new EventReminder($event, $recipient['name']));
                $sent++;
            }

            // Aj bez jediného prihláseného — inak by sa prázdne podujatie
            // preverovalo pri každom behu až do svojho začiatku.
            $event->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info("Reminders sent: {$sent} (events: {$events->count()})");

        return self::SUCCESS;
    }
}
