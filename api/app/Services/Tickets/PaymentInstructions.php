<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Services\Account\OrganizationSync;

/**
 * Pokyny na úhradu platenej objednávky prevodom.
 *
 * Účet príjemcu je bankové spojenie organizácie kanála z Accountu (fakturačné
 * údaje). Vlastný účet na kanáli ani na akcii zámerne nie je — jedno miesto,
 * kde organizátor účet spravuje. Kanál bez organizácie, organizácia bez
 * Accountu alebo bez IBAN-u znamená „pokyny nemáme" a kupujúci dostane len
 * všeobecnú výzvu uhradiť podľa pokynov organizátora.
 *
 * Account číta AccountClient s cache a so záložnou poslednou známou hodnotou,
 * takže výpadok Accountu výzvu na úhradu neprekazí.
 */
class PaymentInstructions
{
    public function __construct(private readonly OrganizationSync $accountSync) {}

    /**
     * @return array{recipient: string, iban: string, swift: ?string, bank_name: ?string, variable_symbol: string, amount: int, currency: string, note: string}|null
     */
    public function for(Ticket $ticket): ?array
    {
        if (! $ticket->awaitsPayment()) {
            return null;
        }

        // Explicitné načítanie, nie lazy loading — resource beží aj tam, kde
        // kanál s organizáciou nikto vopred nenačítal.
        $event = $ticket->loadMissing('event.canal.organization')->event;
        $bank = $event ? $this->bank($event) : null;

        if ($bank === null) {
            return null;
        }

        return [
            ...$bank,
            // Číslo objednávky: číselné a krátke, takže sa zmestí do VS (max. 10 číslic).
            'variable_symbol' => (string) $ticket->id,
            'amount' => (int) $ticket->price_amount,
            'currency' => $ticket->price_currency ?? 'EUR',
            'note' => trim($ticket->holder_name.', '.$event->name, ', '),
        ];
    }

    /** Má akcia kam prijímať platby? Pre upozornenie v nastaveniach vstupeniek. */
    public function accountReady(Event $event): bool
    {
        return $this->bank($event->loadMissing('canal.organization')) !== null;
    }

    /**
     * @return array{recipient: string, iban: string, swift: ?string, bank_name: ?string}|null
     */
    private function bank(Event $event): ?array
    {
        $organization = $event->canal?->organization;

        if (! $organization instanceof Organization) {
            return null;
        }

        $account = $this->accountSync->pull($organization);
        $iban = preg_replace('/\s+/', '', strtoupper((string) ($account['bank']['iban'] ?? '')));

        if ($iban === '') {
            return null;
        }

        return [
            'recipient' => (string) (($account['legal_name'] ?? null) ?: ($account['name'] ?? null) ?: $organization->title),
            'iban' => trim(chunk_split($iban, 4, ' ')),
            'swift' => ($account['bank']['swift'] ?? null) ?: null,
            'bank_name' => ($account['bank']['name'] ?? null) ?: null,
        ];
    }
}
