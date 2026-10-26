<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/** Na koho sa otázka dotazníka vzťahuje — raz za objednávku, alebo za každé miesto. */
enum QuestionScope: string implements HasLabel
{
    use ProvidesOptions;

    case Order = 'order';
    case Attendee = 'attendee';

    public function label(): string
    {
        return __('tickets.question_scope.'.$this->value);
    }
}
