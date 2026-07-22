<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Typ identity kanála.
 *
 * Personal dostane kanál, ktorý vznikne automaticky pri registrácii používateľa
 * (viď PersonalCanalProvisioner). Kanál založený ručne alebo importom si typ
 * volí sám — import vždy Organization, ručné zakladanie cez formulár.
 */
enum CanalIdentityMode: string implements HasLabel
{
    use ProvidesOptions;

    case Personal = 'personal';
    case Organization = 'organization';
    case Pseudonymous = 'pseudonymous';

    public function label(): string
    {
        return __('canal_identity_modes.' . $this->value);
    }
}
