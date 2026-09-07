<?php

namespace App\Services\Imports;

use App\Enums\RegistrationSource;
use App\Models\Canal;

/**
 * Zberný kanál zdroja — importovaný kanál pomenovaný po hostiteľovi
 * (`vyveska.sk`, `tkkbs.sk`, `ecav.sk`).
 *
 * Nie je to organizátor, ale odkladisko: import doň dá podujatie vždy, keď
 * z článku nevie prečítať, kto ho robí (viď ImportedCanalManager). Preto sú
 * jeho podujatia jediné, ktorým sa smie kanál dodatočne prepísať — u kanála
 * s menom organizátora by to bolo prepisovanie hotového údaja.
 */
class CollectionCanal
{
    public static function is(?Canal $canal): bool
    {
        if (! $canal instanceof Canal) {
            return false;
        }

        // Kanál založený registráciou sa nesmie stať zberným ani vtedy, keď si
        // ho niekto pomenoval doménou — podujatia sa hýbu len importovaným.
        if ($canal->registration_source !== RegistrationSource::IMPORT) {
            return false;
        }

        return OrganizerName::looksLikeHost((string) $canal->name);
    }

    /**
     * Id všetkých zberných kanálov. Meno sa posudzuje v PHP, nie v SQL —
     * podmienka „vyzerá ako doména" je regulárny výraz a ten sa naprieč
     * databázami píše zakaždým inak. Importovaných kanálov sú stovky, takže
     * jeden `pluck` je lacnejší než vlastná vetva pre každý ovládač.
     *
     * @return array<int, int>
     */
    public static function ids(): array
    {
        return Canal::query()
            ->where('registration_source', RegistrationSource::IMPORT->value)
            ->pluck('name', 'id')
            ->filter(fn ($name) => OrganizerName::looksLikeHost((string) $name))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
