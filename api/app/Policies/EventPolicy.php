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

    public function publish(User $user, Event $event): bool
    {
        return $this->update($user, $event)
            && $event->status === ModelStatus::Draft;
    }

    /**
     * Opačný smer k publish() — publikované podujatie späť do konceptu.
     * Archivované sem nepatrí, to je koncová stanica (pozri archive()).
     */
    public function unpublish(User $user, Event $event): bool
    {
        return $this->update($user, $event)
            && $event->status === ModelStatus::Published;
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
