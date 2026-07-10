<?php

namespace App\Enums\Concerns;

/**
 * Poskytne enumu jednotný zoznam možností pre <select> vo fronte:
 * pole objektov { value, label }, kde label ide cez lang. Vďaka tomu
 * front nedrží žiadne popisky natvrdo a preklad je len otázka lang súboru.
 *
 * Očakáva, že enum je string-backed a implementuje App\Enums\Contracts\HasLabel.
 *
 * @mixin \App\Enums\Contracts\HasLabel
 */
trait ProvidesOptions
{
    /**
     * @param  array<int, static>|null  $cases  Podmnožina prípadov (napr. povolená policy). Null = všetky.
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(?array $cases = null): array
    {
        return array_map(
            static fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            $cases ?? static::cases(),
        );
    }
}
