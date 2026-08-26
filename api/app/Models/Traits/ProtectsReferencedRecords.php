<?php

namespace App\Models\Traits;

/**
 * Referenčný zámok mazania a odpublikovania.
 *
 * Zmazateľnosť nie je stav, ale vlastnosť vzťahov: záznam, na ktorý už niečo
 * ukazuje, sa zmazať nesmie, lebo by po ňom ostala visieť cudzia väzba. Doteraz
 * to riešil každý repozitár či kontrolér po svojom a Resource o tom nevedel,
 * takže UI ponúklo tlačidlo, ktoré skončilo na 422. Model si teraz prekážku
 * spočíta sám a tú istú hlášku dostane policy, API aj tlačidlo.
 *
 * To isté platí pre odpublikovanie: kanál a miesto, na ktoré sa už odvoláva
 * podujatie, sa nesmú stiahnuť z výpisu — odkaz z podujatia by viedol na
 * neexistujúcu stránku. Zámok mazania a zámok odpublikovania sú dva rôzne
 * zoznamy vzťahov, preto sa počítajú zvlášť.
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
     * Prekážky odpublikovania — rovnaký zápis, iný zoznam vzťahov.
     *
     * @return array<string, int>
     */
    abstract protected function unpublishBlockerCounts(): array;

    /**
     * Hotová hláška, prečo sa záznam nedá zmazať — alebo null, keď sa smie.
     */
    public function deletionBlocker(): ?string
    {
        return $this->firstBlocker($this->deletionBlockerCounts());
    }

    /**
     * Hotová hláška, prečo sa záznam nedá odpublikovať — alebo null.
     */
    public function unpublishBlocker(): ?string
    {
        return $this->firstBlocker($this->unpublishBlockerCounts());
    }

    public function isDeletable(): bool
    {
        return $this->deletionBlocker() === null;
    }

    public function isUnpublishable(): bool
    {
        return $this->unpublishBlocker() === null;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function firstBlocker(array $counts): ?string
    {
        foreach ($counts as $key => $count) {
            if ($count > 0) {
                return __($key, ['count' => $count]);
            }
        }

        return null;
    }
}
