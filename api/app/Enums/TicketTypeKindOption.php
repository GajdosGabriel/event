<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;
use App\Models\User;

/**
 * „Druh" typu lístka tak, ako ho vidí organizátor v UI: jedna voľba, ktorá sa
 * na strane servera rozloží na uložené polia (kind + open_to_public).
 *
 * Jediný zdroj pravdy pre tento select — hodnoty aj popisky aj to, čo smie
 * daný používateľ nastaviť. Front len vykreslí options() z resource.
 */
enum TicketTypeKindOption: string implements HasLabel
{
    use ProvidesOptions;

    case Ticket       = 'ticket';
    case Workshop     = 'workshop';
    case WorkshopOpen = 'workshop_open';

    public function label(): string
    {
        return __('tickets.type_kind_option.' . $this->value);
    }

    /**
     * Rozloží UI voľbu na uložené polia modelu.
     *
     * @return array{kind: TicketTypeKind, open_to_public: bool}
     */
    public function toAttributes(): array
    {
        return match ($this) {
            self::Ticket       => ['kind' => TicketTypeKind::Ticket,   'open_to_public' => false],
            self::Workshop     => ['kind' => TicketTypeKind::Workshop, 'open_to_public' => false],
            self::WorkshopOpen => ['kind' => TicketTypeKind::Workshop, 'open_to_public' => true],
        };
    }

    /** Zloží UI voľbu z uložených polí modelu. */
    public static function fromModel(TicketTypeKind $kind, bool $openToPublic): self
    {
        return match (true) {
            $kind === TicketTypeKind::Workshop && $openToPublic => self::WorkshopOpen,
            $kind === TicketTypeKind::Workshop                  => self::Workshop,
            default                                             => self::Ticket,
        };
    }

    /**
     * Možnosti, ktoré smie daný používateľ nastaviť (policy). Zatiaľ má
     * organizátor k dispozícii všetky; metóda je jednotný bod, kam sa prípadné
     * obmedzenie (napr. otvorený workshop len pre určité role) doplní.
     *
     * @return array<int, self>
     */
    public static function allowedForUser(?User $user): array
    {
        return self::cases();
    }
}
