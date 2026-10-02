<?php

namespace App\Services\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ktoré objednávky patria účtu — jediné miesto, ktoré na to odpovedá.
 *
 * Dvojička [AttendeeDirectory](../Events/AttendeeDirectory.php), len z opačnej
 * strany: tá hovorí organizátorovi, kto mu príde, táto návštevníkovi, kam ide.
 *
 * Väzba nie je len `user_id`. Objednať sa dá **bez účtu** — vtedy má lístok iba
 * `holder_email` ([EloquentTicketRepository]) — a človek si účet často založí až
 * potom (napríklad z pozvánky do tímu). Keby sme sa pozerali len na `user_id`,
 * jeho staré lístky by z „Mojich lístkov" zmizli, hoci mu prišli na tú istú
 * adresu. Rovnaká dvojica podmienok drží aj kontrolu limitu na osobu
 * (`existingMainSeats`), takže tu nevzniká nové pravidlo, len sa pomenúva.
 *
 * E-mail sa porovnáva bez ohľadu na veľkosť písmen: `holder_email` prichádza
 * tak, ako ho človek napísal do formulára.
 */
class TicketOwnership
{
    /**
     * Všetky objednávky účtu, bez ohľadu na stav a termín.
     *
     * Stĺpce sú kvalifikované (`tickets.…`) zámerne: volajúci si k dotazu môže
     * pripojiť `events` kvôli zoradeniu podľa termínu a `status` aj `user_id`
     * existujú v oboch tabuľkách — nekvalifikované by skončili na „ambiguous
     * column“, prípadne, čo je horšie, na tichej zhode s druhou tabuľkou.
     */
    public function query(User $user): Builder
    {
        $email = mb_strtolower(trim((string) $user->email));

        return Ticket::query()->where(function (Builder $q) use ($user, $email) {
            $q->where('tickets.user_id', $user->id);

            if ($email !== '') {
                $q->orWhereRaw('LOWER(tickets.holder_email) = ?', [$email])
                    // Vstupenka v cudzej objednávke: objednal ju niekto iný a
                    // účastníka uviedol e-mailom. Zvonček ho posiela na „Moje
                    // lístky", takže ju tam musí nájsť (viď MyTicketResource).
                    ->orWhereHas('admissions', fn (Builder $a) => $this->attendee($a, $email));
            }
        });
    }

    /** Je účet objednávateľom, alebo len účastníkom na cudzej objednávke? */
    public function isHolder(Ticket $ticket, User $user): bool
    {
        return (int) $ticket->user_id === (int) $user->id
            || $this->email($user) === mb_strtolower(trim((string) $ticket->holder_email));
    }

    public function email(User $user): string
    {
        return mb_strtolower(trim((string) $user->email));
    }

    private function attendee(Builder $admissions, string $email, bool $validOnly = false): Builder
    {
        $admissions->whereRaw('LOWER(attendee_email) = ?', [$email]);

        return $validOnly
            ? $admissions->where('status', AdmissionStatus::Valid->value)
            : $admissions;
    }

    /**
     * Čo ešte len bude: platná objednávka na podujatie, ktoré sa neskončilo.
     *
     * Zrušené objednávky sem nepatria — človek ich zrušil práve preto, aby ich
     * nemal pred sebou. Nezmiznú však úplne, `past()` ich ukáže ako históriu.
     *
     * Podujatie bez termínu (`start_at` NULL) sa počíta medzi nadchádzajúce:
     * dovtedy, kým organizátor termín nedoplní, je to stále živý záznam a
     * v histórii by ho nikto nehľadal.
     */
    public function upcoming(User $user): Builder
    {
        $email = $this->email($user);

        return $this->query($user)
            ->where('tickets.status', '!=', TicketStatus::Cancelled->value)
            // Účastník cudzej objednávky ju má pred sebou len dovtedy, kým jeho
            // vlastná vstupenka platí — odmietnutá patrí do histórie.
            ->where(function (Builder $q) use ($user, $email) {
                $q->where('tickets.user_id', $user->id);

                if ($email !== '') {
                    $q->orWhereRaw('LOWER(tickets.holder_email) = ?', [$email])
                        ->orWhereHas('admissions', fn (Builder $a) => $this->attendee($a, $email, true));
                }
            })
            ->whereHas('event', fn (Builder $q) => $q
                ->where(function (Builder $qq) {
                    // Bez prefixu: vnútro `whereHas` je samostatný poddotaz
                    // nad `events`, takže tu sa niet s čím pomýliť.
                    $qq->whereNull('start_at')
                        ->orWhere('start_at', '>=', now()->startOfDay());
                }));
    }

    /**
     * Zvyšok: uplynulé podujatia a zrušené objednávky. `start_at` sa porovnáva
     * od začiatku dňa, aby lístok na dnešný večer nespadol do histórie hneď
     * ráno — presne v deň podujatia ho človek potrebuje najviac.
     */
    public function past(User $user): Builder
    {
        return $this->query($user)
            ->where(fn (Builder $q) => $q
                ->where('tickets.status', TicketStatus::Cancelled->value)
                ->orWhereHas('event', fn (Builder $qq) => $qq->where('start_at', '<', now()->startOfDay()))
                // Cudzia objednávka, v ktorej účastníkova vstupenka už neplatí.
                ->orWhere(fn (Builder $qq) => $qq
                    ->whereHas('event')
                    ->whereNotIn('tickets.id', $this->upcoming($user)->select('tickets.id'))));
    }
}
