<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Notifications\EventReminder;
use App\Services\Events\AttendeeDirectory;
use App\Services\Events\SubscriberDirectory;
use App\Support\PublicUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Pripomienka pred akciou. Dve publiká, dve rôzne pravidlá:
 *
 * **Účastníci s lístkom** (roadmap 3.5) — len tam, kde si organizátor
 * `reminder_hours_before` naozaj nastavil. Posiela sa raz, `reminder_sent_at`
 * je poistka proti druhej vlne pri ďalšom behu; zmena nastavenia po odoslaní už
 * nič neposiela, lebo druhá pripomienka na tú istú akciu je z pohľadu účastníka
 * spam.
 *
 * **Odberatelia** („Pripomeň mi") — tí si pripomienku vypýtali sami, takže
 * nesmie závisieť od toho, či organizátor niečo nastavil. Drvivá väčšina
 * podujatí v katalógu je importovaná a `reminder_hours_before` nemá vyplnené
 * vôbec; bez vlastného predvoleného okna by týmto ľuďom nikdy nič neprišlo.
 * Poistka je `subscriptions.notified_at` na úrovni riadku — odber môže vzniknúť
 * aj potom, čo podujatie okno prekročilo, a taký človek pripomienku dostať nemá.
 */
class SendEventReminders extends Command
{
    /**
     * Strop predvýberu — zhoduje sa s maximom vo validácii
     * (TicketingSettingsRequest). Drží dopyt malý aj pri veľkom katalógu.
     */
    private const MAX_HOURS = 336;

    /**
     * Predvolené okno pre odberateľov, keď organizátor žiadne nenastavil.
     * Deň vopred je kompromis: ešte sa dá preplánovať večer a už je jasné,
     * či sa naozaj chystám.
     */
    private const SUBSCRIBER_DEFAULT_HOURS = 24;

    protected $signature = 'app:events-send-reminders';

    protected $description = 'Send reminder e-mails to attendees and subscribers before the event starts';

    public function handle(AttendeeDirectory $directory, SubscriberDirectory $subscribers): int
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

        $sentToSubscribers = $this->remindSubscribers($subscribers);

        $this->info("Reminders sent: {$sent} attendees, {$sentToSubscribers} subscribers (events: {$events->count()})");

        return self::SUCCESS;
    }

    /**
     * Odberatelia sa vyberajú vlastným prechodom, nie spolu s účastníkmi:
     * ich podujatia sa s tými vyššie prekrývajú len náhodou (tie potrebujú
     * vyplnené `reminder_hours_before` a nedotknuté `reminder_sent_at`, tieto
     * nič z toho) a spoločný dopyt by musel obe podmienky voliť za behu.
     */
    private function remindSubscribers(SubscriberDirectory $subscribers): int
    {
        $events = Event::query()
            ->where('status', ModelStatus::Published->value)
            ->whereNotNull('start_at')
            ->where('start_at', '>', now())
            ->where('start_at', '<=', now()->addHours(self::MAX_HOURS))
            // Bez tejto podmienky by sa pri každom behu prechádzal celý katalóg
            // budúcich podujatí, hoci odber má zlomok z nich.
            ->whereHas('subscriptions', fn ($query) => $query->active()->whereNull('notified_at'))
            ->with('venue')
            ->get()
            ->filter(fn (Event $event) => $event->start_at->lte(
                now()->addHours($this->subscriberLeadHours($event))
            ));

        $sent = 0;

        foreach ($events as $event) {
            foreach ($subscribers->pendingReminderForEvent($event) as $subscription) {
                Notification::route('mail', $subscription->email)
                    ->notify(new EventReminder(
                        $event,
                        '',
                        PublicUrl::unsubscribe((string) $subscription->token),
                    ));

                $subscription->forceFill(['notified_at' => now()])->save();
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Organizátorovo okno má prednosť — je to ten istý úmysel („kedy má zmysel
     * ozvať sa") a rovnaké pravidlo používa aj pripomienka vypálená do `.ics`
     * (IcsGenerator::alarmLeadHours), takže sa človeku neozveme dvakrát v úplne
     * iný čas.
     */
    private function subscriberLeadHours(Event $event): int
    {
        $configured = $event->reminder_hours_before;

        return is_numeric($configured) && (int) $configured > 0
            ? (int) $configured
            : self::SUBSCRIBER_DEFAULT_HOURS;
    }
}
