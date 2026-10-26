<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    use ProvidesOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case Checkbox = 'checkbox';
    case Number = 'number';
    case Date = 'date';

    public function label(): string
    {
        return __('tickets.question_type.'.$this->value);
    }

    /** Odpoveď je výber z možností (option_id), nie voľná hodnota. */
    public function hasOptions(): bool
    {
        return $this === self::SingleChoice || $this === self::MultiChoice;
    }
}
