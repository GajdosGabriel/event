<?php

namespace App\Services\Account;

use App\Enums\CanalIdentityMode;
use App\Enums\MailingTrigger;
use App\Enums\TicketStatus;
use App\Jobs\CancelMailing;
use App\Jobs\ReportMailingEvent;
use App\Jobs\SyncMailingContact;
use App\Models\Canal;
use App\Models\CanalOutreach;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DashboardUrl;
use App\Support\EventDateRange;
use App\Support\PublicUrl;
use Carbon\CarbonInterface;

/**
 * Emailing (kampane a automatizácie) beží v Accounte – Event len hlási
 * udalosti s premennými do textu kampane a udržiava adresár kontaktov.
 *
 * Vecou je registrácia (`ticket`, uuid) alebo akcia (`event`, id), takže
 * zrušenie vie v Accounte zrušiť jej čakajúce maily. Kľúč udalosti je
 * `ticket:{uuid}` / `event:{id}` – opakované hlásenie nič nezdvojí.
 *
 * Bez ACCOUNT_MAILING (alebo tokenu) sa nič nehlási.
 */
class AccountMailing
{
    public function __construct(private readonly AccountClient $account) {}

    public function enabled(): bool
    {
        return $this->account->mailingEnabled();
    }

    public function event(MailingTrigger $trigger, Ticket $ticket, ?CarbonInterface $at = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $email = self::email($ticket->holder_email);

        if ($email === null) {
            return;
        }

        $event = $ticket->loadMissing('event.venue')->event;
        $name = trim((string) $ticket->holder_name) ?: null;

        ReportMailingEvent::dispatch(
            $trigger->value,
            $email,
            [
                'type' => 'ticket',
                'id' => $ticket->uuid,
                'label' => 'Registrácia – '.($event?->name ?? '#'.$ticket->id),
                'url' => $event ? DashboardUrl::base().'/events/'.$event->id.'/attendees' : null,
            ],
            [
                'meno' => $name,
                'podujatie' => $event?->name,
                'termin' => $event ? EventDateRange::label($event->start_at, $event->end_at) : null,
                'miesto' => $event?->venue?->name,
                'pocet_miest' => (int) $ticket->quantity,
                'odkaz_podujatia' => $event ? PublicUrl::event($event) : null,
                'odkaz_vstupenky' => rtrim((string) config('app.frontend_url'), '/').'/tickets/'.$ticket->uuid,
            ],
            'ticket:'.$ticket->uuid,
            $at ?? now(),
            $name,
        )->afterCommit();
    }

    /**
     * Akcia sa zverejnila – technický mail správcom kanála (kampaň druhu
     * oznámenie o službe). Len vlastný obsah spravovaného kanála: import
     * organizátor nezadal a nespravovaný kanál majú na starosti ponuky
     * prevzatia (MailingTrigger::Canal*).
     */
    public function eventPublished(Event $event): void
    {
        if (! $this->enabled() || ! empty($event->orginal_source)) {
            return;
        }

        $event->loadMissing(['canal', 'venue']);
        $canal = $event->getRelation('canal');

        if (! $canal || ! $canal->isManaged()) {
            return;
        }

        $at = $event->published_at ?? now();

        foreach ($canal->owners()->whereNotNull('email')->get() as $owner) {
            if (! ($email = self::email($owner->email))) {
                continue;
            }

            $name = self::personName($owner);

            ReportMailingEvent::dispatch(
                MailingTrigger::EventPublished->value,
                $email,
                [
                    'type' => 'event',
                    'id' => (string) $event->id,
                    'label' => 'Akcia – '.$event->name,
                    'url' => DashboardUrl::base().'/events/'.$event->id,
                ],
                [
                    'meno' => $name,
                    'kanal' => $canal->name,
                    'podujatie' => $event->name,
                    'termin' => EventDateRange::label($event->start_at, $event->end_at),
                    'miesto' => $event->venue?->name,
                    'odkaz_podujatia' => PublicUrl::event($event),
                    'odkaz_spravy' => DashboardUrl::base().'/events/'.$event->id,
                    'odkaz_upravy' => DashboardUrl::base().'/events/'.$event->id.'/edit',
                ],
                'event:'.$event->id,
                $at,
                $name,
            )->afterCommit();
        }
    }

    /**
     * Registrácia sa zrušila alebo zmazala: čakajúce maily k nej sa neodošlú
     * (okrem kampaní práve na zrušenie).
     */
    public function cancel(Ticket $ticket): void
    {
        if (! $this->enabled()) {
            return;
        }

        CancelMailing::dispatch('ticket', $ticket->uuid, [MailingTrigger::TicketCancelled->value])->afterCommit();
    }

    /** Akcia sa stiahla zo zverejnenia alebo zmazala: čakajúce maily k nej sa neodošlú. */
    public function cancelEvent(Event $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        CancelMailing::dispatch('event', (string) $event->id)->afterCommit();
    }

    /**
     * Kanál prevzal organizátor: čakajúce ponuky prevzatia (MailingTrigger::Canal*)
     * sa neodošlú.
     */
    public function cancelCanal(Canal $canal): void
    {
        if (! $this->enabled()) {
            return;
        }

        CancelMailing::dispatch('canal', (string) $canal->id)->afterCommit();
    }

    /**
     * Adresu treba v adresári Accountu prepočítať (účet, členstvo v kanáli,
     * registrácia, zmazanie). Stav poskladá až job – pozri contact().
     */
    public function syncContact(?string $email): void
    {
        if (! $this->enabled() || ! ($email = self::email($email))) {
            return;
        }

        SyncMailingContact::dispatch($email)->afterCommit();
    }

    /**
     * Kontakt pre adresár v Accounte, alebo null, ak adresa v Evente nemá
     * účet, registráciu ani oslovenie kanála (kontakt sa zmaže).
     *
     * Súhlas s ponukami Event zatiaľ nezbiera – má ho len verejný kontakt
     * importovaného kanála (rovnako ako pri oslovení, CanalOutreachSender).
     * Ostatní dostanú len oznámenia o službe.
     *
     * @return array{email: string, name: ?string, tags: array<int, string>, attributes: array<string, string>, permission_note?: string, permission_at?: string}|null
     */
    public function contact(string $email): ?array
    {
        $email = (string) self::email($email);
        $user = User::query()->where('email', $email)->first();
        $ticket = Ticket::query()->where('holder_email', $email)
            ->where('status', '!=', TicketStatus::Cancelled->value)
            ->latest('id')
            ->first();
        // Prevzatý kanál už má správcu.
        $outreach = CanalOutreach::query()->where('email', $email)
            ->whereHas('canal', fn ($canal) => $canal->whereNull('claimed_at'))
            ->with('canal')
            ->oldest('id')
            ->first();

        if (! $user && ! $ticket && ! $outreach) {
            return null;
        }

        $organizerCanal = $user ? $this->organizerCanal($user) : null;
        $person = ($user ? self::personName($user) : null) ?? (trim((string) $ticket?->holder_name) ?: null);
        $canalName = $organizerCanal?->name ?? $outreach?->canal?->name;

        $contact = [
            'email' => $email,
            'name' => $person ?? $canalName,
            'tags' => array_values(array_filter([
                $user ? 'user' : null,
                $user?->email_verified_at ? 'verified' : null,
                $organizerCanal ? 'organizer' : null,
                $outreach ? 'canal_contact' : null,
                $ticket ? 'attendee' : null,
            ])),
            'attributes' => array_filter([
                'meno' => $person,
                'kanal' => $canalName,
            ]),
        ];

        if ($outreach) {
            $contact['permission_note'] = 'Verejný kontakt organizátora podujatí (import)';
            $contact['permission_at'] = ($outreach->canal?->created_at ?? $outreach->created_at ?? now())->format(DATE_ATOM);
        }

        return $contact;
    }

    /** Štítky pre výber adresátov v Accounte. */
    public function tags(): array
    {
        return [
            ['key' => 'user', 'label' => 'Má účet', 'description' => 'Registrovaný používateľ (aj neoverený e-mail).'],
            ['key' => 'verified', 'label' => 'Overený e-mail', 'description' => 'Používateľ si potvrdil e-mailovú adresu.'],
            ['key' => 'organizer', 'label' => 'Organizátor', 'description' => 'Člen tímu kanála, ktorý má aspoň jednu akciu.'],
            ['key' => 'canal_contact', 'label' => 'Kontakt nespravovaného kanála', 'description' => 'Verejný kontakt importovaného kanála, ktorý dostal ponuku prevzatia.'],
            ['key' => 'attendee', 'label' => 'Účastník', 'description' => 'Má platnú registráciu alebo vstupenku na akciu.'],
        ];
    }

    /** Udalosti pre výber spúšťača v Accounte. */
    public function triggers(): array
    {
        return array_map(fn (MailingTrigger $trigger) => [
            'key' => $trigger->value,
            'label' => $trigger->label(),
            'short_label' => $trigger->shortLabel(),
            'variables' => $trigger->variables(),
        ], MailingTrigger::cases());
    }

    /** Kanál, v ktorom je používateľ v tíme a ktorý má aspoň jednu akciu. */
    private function organizerCanal(User $user): ?Canal
    {
        $ids = $user->dashboardCanalIds(withTrashed: false);

        if ($ids->isEmpty()) {
            return null;
        }

        return Canal::query()->whereIn('id', $ids)
            ->whereHas('events')
            ->orderByRaw('id = ? desc', [(int) $user->canal_id])
            ->first();
    }

    /**
     * Meno človeka: osobný kanál alebo ešte nespracovaný profil. Na rozdiel
     * od User::displayName() bez náhradného tvaru z e-mailu – ten do
     * oslovenia v maili nepatrí.
     */
    private static function personName(User $user): ?string
    {
        $personal = $user->canals()
            ->where('canals.identity_mode', CanalIdentityMode::Personal->value)
            ->value('canals.name');

        return trim((string) ($personal ?? $user->pendingProfile()->value('display_name') ?? '')) ?: null;
    }

    private static function email(?string $email): ?string
    {
        return mb_strtolower(trim((string) $email)) ?: null;
    }
}
