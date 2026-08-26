<?php

namespace App\Http\Resources;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Resources\Traits\HasAttributeCheckState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CanalResource extends JsonResource
{
    use HasAllowedStatuses, HasAttributeCheckState;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $data = parent::toArray($request);

        $data['status_label'] = $this->statusLabel();
        $data['allowed_statuses'] = $this->allowedStatuses($request);
        $data['identity_mode_label'] = $this->identityModeLabel();
        // Nefunkčné hodnoty (dnes web) — viď App\Services\Attributes.
        $data['attribute_issues'] = $this->attributeCheckState($request);

        // Len pre organizátora a admina — model ho drží v $hidden (HasViews).
        if ($user?->can('view', $this->resource)) {
            $data['views_count'] = (int) $this->views_count;
        }

        $isPublished = $this->status === ModelStatus::Published;
        $canUpdate = $user?->can('update', $this->resource) ?? false;

        // Viď VenueResource — dôvod, prečo mazanie neprejde, ide do tlačidla.
        $blocker = $canUpdate ? $this->resource->deletionBlocker() : null;

        $data['delete_blocked_reason'] = $blocker;
        // To isté pre stiahnutie z výpisu: policy vráti len „nesmieš",
        // tlačidlo potrebuje vedieť prečo. Počíta sa len publikovanému kanálu,
        // inde otázka nedáva zmysel.
        $data['unpublish_blocked_reason'] = $canUpdate && $isPublished
            ? $this->resource->unpublishBlocker()
            : null;

        $data['permissions'] = [
            'view' => $user?->can('view', $this->resource) ?? false,
            'update' => $canUpdate,
            'publish' => $user?->can('publish', $this->resource) ?? false,
            'unpublish' => $user?->can('unpublish', $this->resource) ?? false,
            'delete' => $blocker === null && ($user?->can('delete', $this->resource) ?? false),
            'archive' => $isPublished && ($user?->can('archive', $this->resource) ?? false),
            'restore' => $user?->can('restore', $this->resource) ?? false,
        ];

        if ($this->relationLoaded('municipality') && $this->municipality) {
            $data['municipality'] = [
                'id' => $this->municipality->id,
                'name' => $this->municipality->fullname,
            ];
        }

        // Fakturačná identita kanála. Osobný kanál žiadnu nemá, preto sa kľúč
        // pridáva len keď vzťah naozaj sedí.
        if ($this->relationLoaded('organization') && $this->organization) {
            $data['organization'] = [
                'id' => $this->organization->id,
                'name' => $this->organization->title,
            ];
        }

        if ($this->relationLoaded('venues')) {
            $data['venues_list'] = $this->venues->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'is_owner' => (bool) $v->pivot->is_owner,
            ])->values()->all();
        }

        if ($this->relationLoaded('users')) {
            $data['members_list'] = $this->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->display_name ?? $u->name ?? ('User #' . $u->id),
                'is_owner' => (bool) $u->pivot->is_owner,
            ])->values()->all();
        }

        // „Poslať správu" potrebuje príznak len na detaile (show) — na výpisoch
        // (index) by isContactableBy() robilo dotaz na každý riadok, preto len tu.
        if ($request->route()?->getActionMethod() === 'show') {
            $data['contactable'] = $this->resource->isContactableBy($user);
        }

        return $data;
    }

    private function identityModeLabel(): ?string
    {
        $mode = $this->identity_mode instanceof CanalIdentityMode
            ? $this->identity_mode
            : CanalIdentityMode::tryFrom((string) $this->identity_mode);

        return $mode?->label();
    }
}
