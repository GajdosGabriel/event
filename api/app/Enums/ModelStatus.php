<?php

namespace App\Enums;

use App\Models\User;

enum ModelStatus: string
{
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

    public function label(): string
    {
        return match($this) {
            self::Draft        => 'Koncept',
            self::PendingReview => 'Čaká na schválenie',
            self::Rejected     => 'Zamietnutý',
            self::Scheduled    => 'Naplánovaný',
            self::Published    => 'Publikovaný',
            self::Archived     => 'Archivovaný',
            self::Blocked      => 'Blokovaný',
        };
    }

    /**
     * Returns the allowed statuses for a given user as [value, label] pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function allowedForUser(?User $user): array
    {
        $cases = ($user && $user->hasRole('super-admin'))
            ? self::cases()
            : [self::Draft, self::Published, self::Archived];

        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            $cases
        );
    }
}
