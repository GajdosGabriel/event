<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Rola člena tímu v konkrétnom kanáli (pivot canal_user.role).
 *
 * Doteraz sa role priraďovali len globálne na používateľa, takže sa nedalo byť
 * správcom jedného kanála a len brigádnikom na vstupe v druhom. Zdrojom pravdy
 * je odteraz táto rola v pivote; globálna spatie rola ostáva len ako hrubá
 * poistka pre `permission:` middleware na routách (viď CanalMembership).
 *
 * `is_owner` v pivote sa drží v súlade s rolou (owner <=> is_owner), aby staršie
 * dotazy cez ownedCanals()/owners() fungovali bez zmeny.
 */
enum CanalRole: string implements HasLabel
{
    use ProvidesOptions;

    /**
     * Správca: plná správa kanála vrátane tímu. V UI „Správca", nie „Vlastník" —
     * prevzatý kanál založila platforma a správcovi nepatrí; jeho mazanie,
     * archiváciu a odobratie hlavného správcu (claimed_by) rieši len admin.
     */
    case Owner = 'owner';

    /** Robí obsah: podujatia, miesta, lístky. Nemôže spravovať tím ani kanál zmazať. */
    case Editor = 'editor';

    /** Prehliadač: vidí podujatia, miesta, lístky aj objednávateľov, nič nemení. */
    case Viewer = 'viewer';

    /** Brigádnik na vstupe — vidí podujatia a lístky, robí len check-in. */
    case Checkin = 'checkin';

    public function label(): string
    {
        return __('canal_roles.'.$this->value);
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Čo smie rola v rámci svojho kanála. Kontroluje sa v policies cez
     * User::canInCanal(); mimo kanála rola neznamená nič.
     *
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner => [
                'canal.view', 'canal.update', 'canal.delete', 'canal.team',
                'event.view', 'event.create', 'event.update', 'event.delete',
                'venue.view', 'venue.create', 'venue.update', 'venue.delete',
                'ticket.view', 'ticket.create', 'ticket.update', 'ticket.checkin',
                'file.view', 'file.create', 'file.update', 'file.delete',
            ],
            self::Editor => [
                'canal.view',
                'event.view', 'event.create', 'event.update',
                'venue.view', 'venue.create', 'venue.update',
                'ticket.view', 'ticket.create', 'ticket.update', 'ticket.checkin',
                'file.view', 'file.create', 'file.update', 'file.delete',
            ],
            self::Viewer => [
                'canal.view',
                'event.view',
                'venue.view',
                'ticket.view',
                'file.view',
            ],
            self::Checkin => [
                'canal.view',
                'event.view',
                'ticket.view', 'ticket.checkin',
            ],
        };
    }

    public function allows(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /**
     * Globálna spatie rola, ktorá zodpovedá tejto per-kanálovej role. Slúži len
     * ako hrubý filter pre `permission:` middleware — presné právo rozhoduje až
     * policy nad konkrétnym kanálom.
     */
    public function globalRole(): string
    {
        return match ($this) {
            self::Owner => 'canal-owner',
            self::Editor => 'canal-editor',
            self::Viewer => 'canal-viewer',
            self::Checkin => 'canal-checkin',
        };
    }

    /** Globálne role odvodené z členstva — nikdy sa nepriraďujú ručne. */
    public static function globalRoles(): array
    {
        return array_map(static fn (self $case) => $case->globalRole(), self::cases());
    }

    /** Poradie od najsilnejšej role — používa sa pri zlučovaní členstiev. */
    public function weight(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Editor => 3,
            self::Checkin => 2,
            self::Viewer => 1,
        };
    }
}
