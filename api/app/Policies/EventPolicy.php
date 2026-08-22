<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Policies\Traits\DeniesArchivedUpdate;

class EventPolicy
{
    use DeniesArchivedUpdate;
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        return $user->canInCanal((int) $event->canal_id, 'event.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyCanalAbility('event.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        return $this->isNotArchived($event)
            && $user->canInCanal((int) $event->canal_id, 'event.update');
    }

    /**
     * Naplánované podujatie sa dá publikovať aj ručne — je to „vydať teraz,
     * nečakať na termín", nie obídenie nejakej kontroly.
     */
    public function publish(User $user, Event $event): bool
    {
        return $this->update($user, $event)
            && in_array($event->status, [ModelStatus::Draft, ModelStatus::Scheduled], true);
    }

    /**
     * Opačný smer k publish() — publikované (alebo naplánované) podujatie späť
     * do konceptu. Archivované sem nepatrí, to je koncová stanica (pozri archive()).
     */
    public function unpublish(User $user, Event $event): bool
    {
        return $this->update($user, $event)
            && in_array($event->status, [ModelStatus::Published, ModelStatus::Scheduled], true);
    }

    /**
     * Archivované podujatie späť do konceptu.
     *
     * Archivácia beží automaticky desať minút po `end_at`
     * (app:events-archive-finished), takže preklep v roku zamkne podujatie skôr,
     * než si ho niekto všimne — a update, delete aj unpublish sú archivovanému
     * zakázané. Bez tejto cesty by organizátorovi ostalo len duplikovať
     * a vypĺňať odznova.
     *
     * Vracia sa do konceptu, nie späť medzi publikované: z verejného výpisu tým
     * zmizne, nič sa spätne neprepisuje a von ide normálnou cestou cez publish()
     * aj s jej kontrolami.
     *
     * Zámkom je história, nie čas. Podujatie s vydanými lístkami sa neodomyká —
     * koncept verejnosť nevidí (404) a držiteľom lístkov by zmizol detail akcie,
     * na ktorú prišli. Podujatie bez lístkov nedrží nič, čo by sa dalo pokaziť.
     */
    public function unarchive(User $user, Event $event): bool
    {
        return $event->status === ModelStatus::Archived
            && $user->canInCanal((int) $event->canal_id, 'event.update')
            && ! $event->tickets()->exists();
    }

    /**
     * Determine whether the user can create a new draft event based on this one.
     * Intentionally does NOT check isNotArchived() — duplicating an archived event
     * is the whole point (it's the "edit" replacement once an event is locked).
     */
    public function duplicate(User $user, Event $event): bool
    {
        return $user->canInCanal((int) $event->canal_id, 'event.create');
    }

    /**
     * Determine whether the user can archive the model (published -> archived).
     */
    public function archive(User $user, Event $event): bool
    {
        return $event->status === ModelStatus::Published
            && $user->canInCanal((int) $event->canal_id, 'event.delete');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->isNotArchived($event)
            && $event->status !== ModelStatus::Published
            && $user->canInCanal((int) $event->canal_id, 'event.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $user->canInCanal((int) $event->canal_id, 'event.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return false;
    }
}
