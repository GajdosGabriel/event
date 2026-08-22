<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Venue $venue): bool
    {
        return $venue->activeCanals()
            ->whereIn('canals.id', $user->canalIdsWithAbility('venue.view'))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyCanalAbility('venue.create');
    }

    /**
     * Archivované miesto sa editovať smie. Archív tu znamená „mimo prevádzky",
     * nie zmrazený fakt v čase — to je prípad podujatia (EventPolicy::update()).
     * Oprava adresy zrušeného klubu nič neprepisuje, naopak: minulé podujatia na
     * ňom tým dostanú správny údaj. Zákaz by navyše spravil z archívu jednosmerku
     * — stav sa mení práve cez update.
     */
    public function update(User $user, Venue $venue): bool
    {
        return $this->ownsVenueThrough($user, $venue, 'venue.update');
    }

    public function publish(User $user, Venue $venue): bool
    {
        return $this->update($user, $venue)
            && $venue->status !== ModelStatus::Published;
    }

    public function unpublish(User $user, Venue $venue): bool
    {
        return $this->update($user, $venue)
            && $venue->status === ModelStatus::Published;
    }

    public function archive(User $user, Venue $venue): bool
    {
        return $venue->status === ModelStatus::Published
            && $this->ownsVenueThrough($user, $venue, 'venue.delete');
    }

    /**
     * Stavový zámok: mazať sa dá len to, čo nie je vonku (`published`).
     *
     * Archivované sa tu zámerne nekontroluje. Miesto s históriou drží referenčný
     * zámok bez ohľadu na stav a miesto bez histórie si vlastník aj tak odomkol
     * jedným prepnutím stavu na koncept (update archivovanému miestu povolený
     * je) — archív tu teda nechránil nič, len pridával krok navyše. Archív pri
     * mieste znamená „mimo prevádzky", nie „nedotknuteľné"; nedotknuteľnosť
     * rieši história, nie stav.
     *
     * Referenčný zámok („už to používa podujatie") sem nepatrí — to nie je
     * otázka práva, ale stavu dát, a musí odísť ako 422 s vysvetlením, nie ako
     * holé 403. Rieši ho ProtectsReferencedRecords v repozitári; Resource ho
     * pridáva k tomuto právu pre tlačidlo.
     */
    public function delete(User $user, Venue $venue): bool
    {
        return (
            $venue->status !== ModelStatus::Published
            && $this->ownsVenueThrough($user, $venue, 'venue.delete')
        )
            // Odpojenie cudzieho miesta nie je mazanie — väzba ostáva na
            // vlastníckom kanáli, takže referenčný ani stavový zámok sa naň
            // nevzťahuje. Podmienka „cudzie" tu predtým chýbala a vlastník
            // si cez túto vetvu vedel zmazať aj publikované miesto.
            || ($this->isForeignVenue($user, $venue) && $this->isLinkedToVenueCanal($user, $venue));
    }

    public function restore(User $user, Venue $venue): bool
    {
        return $this->ownsVenueThrough($user, $venue, 'venue.delete');
    }

    public function forceDelete(User $user, Venue $venue): bool
    {
        return false;
    }

    /**
     * Miesto patrí kanálu, v ktorom má používateľ dané právo. Vlastníctvo miesta
     * je na kanáli (canal_venue.is_owner), právo na členstve v tom kanáli.
     */
    private function ownsVenueThrough(User $user, Venue $venue, string $ability): bool
    {
        return $venue->ownerCanals()
            ->whereIn('canals.id', $user->canalIdsWithAbility($ability))
            ->exists();
    }

    /**
     * Miesto nepatrí žiadnemu z používateľových kanálov — rovnaká otázka, akú
     * si kladie EloquentVenueRepository::delete(), keď sa rozhoduje medzi
     * zmazaním a odpojením.
     */
    private function isForeignVenue(User $user, Venue $venue): bool
    {
        return ! $venue->ownerCanals()
            ->whereIn('canals.id', $user->ownedCanals()->pluck('canals.id'))
            ->exists();
    }

    /**
     * Miesto zdieľané cudzím kanálom sa nemaže, len odpája — to je pre pripojený
     * kanál úroveň úpravy, nie mazania.
     */
    private function isLinkedToVenueCanal(User $user, Venue $venue): bool
    {
        return $venue->activeCanals()
            ->whereIn('canals.id', $user->canalIdsWithAbility('venue.update'))
            ->exists();
    }
}
