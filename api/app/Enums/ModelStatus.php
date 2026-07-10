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

    public function label(): string
    {
        return __('statuses.' . $this->value);
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

        return self::options($cases);
    }
}
