<?php

namespace App\Enums;

/** O čom používateľ píše podpore — podľa toho sa vlákno v schránke triedi. */
enum SupportCategory: string
{
    case Question = 'question';
    case Problem = 'problem';
    case Canal = 'canal';
    case Idea = 'idea';
    case Other = 'other';

    public function label(): string
    {
        return __('support.category.'.$this->value);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $category) => ['value' => $category->value, 'label' => $category->label()],
            self::cases()
        );
    }
}
