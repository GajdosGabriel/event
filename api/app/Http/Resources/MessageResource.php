<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Správa v dashboardovom inboxe.
 *
 * E-mail druhej strany sa do odpovede NEDOSTANE — ani odosielateľa, ani
 * príjemcu. Kontakt drží e-mailová notifikácia (reply-to) a odpovedať sa dá
 * priamo cez `POST /dashboard/messages/{id}/reply`; vystaviť adresu v UI by
 * z inboxu spravilo zoznam e-mailov na zber.
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $target = $this->whenLoaded('messageable');

        return [
            'id' => $this->id,
            'parent_message_id' => $this->parent_message_id,
            'body' => $this->body,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            // Smer z pohľadu prihláseného: prijatá otázka vs. vlastná odpoveď.
            'outgoing' => (int) $this->sender_user_id === (int) $user?->id,
            'sender_name' => $this->displayNameOf($this->sender),
            'recipient_name' => $this->displayNameOf($this->recipient),
            'target' => $this->relationLoaded('messageable') && $target
                ? [
                    'type' => $this->targetType(),
                    'id' => $this->messageable_id,
                    'name' => $target->name,
                ]
                : null,
            'replies' => self::collection($this->whenLoaded('replies')),
            'permissions' => [
                'reply' => $user?->can('reply', $this->resource) ?? false,
                'mark_read' => $user?->can('markRead', $this->resource) ?? false,
            ],
        ];
    }

    /** Zmazaný účet (FK sa pri delete nuluje) nemá meno, ale správa ostáva. */
    private function displayNameOf(?User $user): string
    {
        return $user?->displayName() ?? 'Zmazaný účet';
    }
}
