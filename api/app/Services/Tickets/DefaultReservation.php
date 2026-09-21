<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\TicketType;

/**
 * Rezervácia miesta zdarma ako predvolený stav každého podujatia.
 *
 * Podujatie, pri ktorom organizátor lístky nenastavil, ponúka „Rezervovať"
 * (Event::usesDefaultReservation) — bez toho, aby malo v databáze typ lístka.
 * Na veľkú väčšinu podujatí sa cez portál nikto neprihlási, tak ich
 * nezakladáme dopredu. Typ vznikne až pri prvej rezervácii (resolve).
 *
 * Verejný formulár dostane namiesto neho virtuálny typ s id VIRTUAL_ID
 * (virtual); objednávka s týmto id sa pri uložení premapuje na skutočný typ.
 *
 * Kto lístky nastavil inak — vypol (neaktívny typ), zmazal (soft delete) alebo
 * pridal vlastné — toho sa to netýka. Keď organizátor pridá vlastný typ,
 * nepoužitý automatický zmizne (retireFor).
 */
class DefaultReservation
{
    public const NAME = 'Rezervácia miesta';

    /** Príznak v `ticket_types.meta`, podľa ktorého sa automatický typ pozná. */
    public const META_KEY = 'auto_default';

    /** Id virtuálneho typu vo verejnom formulári (skutočné id sú od 1). */
    public const VIRTUAL_ID = 0;

    /**
     * Typ lístka pre rezerváciu zdarma: podujatiu bez lístkov ho založí,
     * automatický už založený vráti. Keď lístky nastavil organizátor, null.
     */
    public function resolve(Event $event): ?TicketType
    {
        $auto = $event->ticketTypes()
            ->where('is_active', true)
            ->where('meta->'.self::META_KEY, true)
            ->first();

        if ($auto) {
            return $auto;
        }

        if ($event->ticketTypes()->withTrashed()->exists()) {
            return null;
        }

        return $event->ticketTypes()->create([
            'name' => self::NAME,
            'price_amount' => 0,
            'price_currency' => $event->price_currency ?: 'EUR',
            'is_active' => true,
            'meta' => [self::META_KEY => true],
        ]);
    }

    /** Neuložený typ pre verejný formulár podujatia bez lístkov. */
    public function virtual(Event $event): TicketType
    {
        $type = new TicketType([
            'event_id' => $event->id,
            'name' => self::NAME,
            'kind' => 'ticket',
            'price_amount' => 0,
            'price_currency' => $event->price_currency ?: 'EUR',
            'is_active' => true,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'sort_order' => 0,
        ]);
        $type->id = self::VIRTUAL_ID;

        return $type;
    }

    /** Nový vlastný typ lístka → nepoužitý automatický typ podujatia odíde. */
    public function retireFor(TicketType $created): void
    {
        if ($created->isAutoDefault()) {
            return;
        }

        TicketType::query()
            ->where('event_id', $created->event_id)
            ->whereKeyNot($created->getKey())
            ->where('meta->'.self::META_KEY, true)
            ->whereDoesntHave('admissions')
            ->get()
            ->each->forceDelete();
    }
}
