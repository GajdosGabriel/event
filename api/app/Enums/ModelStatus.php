<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;
use App\Models\User;

enum ModelStatus: string implements HasLabel
{
    use ProvidesOptions;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
    case Blocked = 'blocked';

    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Published], true);
    }

    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    /**
     * Stavy, ktoré smie ukázať verejný detail.
     *
     * Archivované sem patria, hoci vo výpisoch nie sú: archivácia je automatická
     * desať minút po skončení podujatia (`app:events-archive-finished`) a odkazy
     * na minuloročné akcie musia ostať funkčné. Koncept, naplánované, blokované
     * a moderačné stavy verejné nie sú — pri `scheduled` je to celý zmysel veci.
     *
     * @return array<int, string>
     */
    public static function publiclyReadableValues(): array
    {
        return [self::Published->value, self::Archived->value];
    }

    public function label(): string
    {
        return __('statuses.' . $this->value);
    }

    /**
     * Returns the allowed statuses for a given user as [value, label] pairs.
     *
     * Zámerne nevracia PendingReview/Rejected/Scheduled — moderačný workflow
     * neexistuje a naplánované publikovanie vie len podujatie (viď
     * allowedForEvent), takže inde by to boli stavy, z ktorých sa záznam už
     * nikdy nepohne. Prípady v enume ostávajú, aby sa dali načítať historické
     * riadky; keď workflow pribudne, stačí ich sem vrátiť.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function allowedForUser(?User $user): array
    {
        return self::options(self::casesForUser($user));
    }

    /**
     * Stavy podujatia — navyše `scheduled`. Naplánované publikovanie je
     * implementované len pri podujatiach (`events.publish_at` + príkaz
     * `app:events-publish-scheduled`); kanál ani miesto z tohto stavu nemá čo
     * vyviesť, preto ho v allowedForUser() nenájdete.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function allowedForEvent(?User $user): array
    {
        $cases = self::casesForUser($user);

        array_splice($cases, 1, 0, [self::Scheduled]);

        return self::options($cases);
    }

    /** @return array<int, self> */
    private static function casesForUser(?User $user): array
    {
        return ($user && $user->hasRole('super-admin'))
            ? [self::Draft, self::Published, self::Archived, self::Blocked]
            : [self::Draft, self::Published, self::Archived];
    }
}
