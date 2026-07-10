<?php

namespace App\Enums\Contracts;

/**
 * Enum, ktorý vie o sebe poskytnúť ľudský popisok (cez lang).
 * V spojení s trait-om ProvidesOptions dá jednotný zoznam možností pre <select>.
 */
interface HasLabel
{
    public function label(): string;
}
