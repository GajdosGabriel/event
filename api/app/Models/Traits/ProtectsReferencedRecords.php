<?php

namespace App\Models\Traits;

/**
 * Referenčný zámok mazania.
 *
 * Zmazateľnosť nie je stav, ale vlastnosť vzťahov: záznam, na ktorý už niečo
 * ukazuje, sa zmazať nesmie, lebo by po ňom ostala visieť cudzia väzba. Doteraz
 * to riešil každý repozitár či kontrolér po svojom a Resource o tom nevedel,
 * takže UI ponúklo tlačidlo, ktoré skončilo na 422. Model si teraz prekážku
 * spočíta sám a tú istú hlášku dostane policy, API aj tlačidlo.
 */
trait ProtectsReferencedRecords
{
    /**
     * Prekážky mazania ako [prekladový kľúč => počet referencií]. Poradie
     * rozhoduje: vypíše sa prvá neprázdna.
     *
     * @return array<string, int>
     */
    abstract protected function deletionBlockerCounts(): array;

    /**
     * Hotová hláška, prečo sa záznam nedá zmazať — alebo null, keď sa smie.
     */
    public function deletionBlocker(): ?string
    {
        foreach ($this->deletionBlockerCounts() as $key => $count) {
            if ($count > 0) {
                return __($key, ['count' => $count]);
            }
        }

        return null;
    }

    public function isDeletable(): bool
    {
        return $this->deletionBlocker() === null;
    }
}
