<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Resources\Traits\HasAttributeCheckState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
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
        // Nefunkčné hodnoty (dnes web) — viď App\Services\Attributes.
        $data['attribute_issues'] = $this->attributeCheckState($request);

        // Len pre organizátora a admina — model ho drží v $hidden (HasViews).
        if ($user?->can('view', $this->resource)) {
            $data['views_count'] = (int) $this->views_count;
        }

        $isPublished = $this->status === ModelStatus::Published;
        $canUpdate = $user?->can('update', $this->resource) ?? false;

        // Prečo sa miesto nedá zmazať, hoci právo na to je — používa ho
        // podujatie. Počíta sa len tomu, kto s miestom vôbec smie robiť, nech
        // to na výpisoch nie je dotaz navyše na každý cudzí riadok.
        $blocker = $canUpdate ? $this->resource->deletionBlocker() : null;

        $data['delete_blocked_reason'] = $blocker;

        $data['permissions'] = [
            'view' => $user?->can('view', $this->resource) ?? false,
            'update' => $canUpdate,
            'publish' => $user?->can('publish', $this->resource) ?? false,
            'unpublish' => $user?->can('unpublish', $this->resource) ?? false,
            // Stavový zámok rieši policy, referenčný model — tlačidlo potrebuje
            // oba naraz, inak by kliknutie skončilo na 422.
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

        if ($this->relationLoaded('canals')) {
            $data['canals_list'] = $this->canals->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'is_owner' => (bool) $c->pivot->is_owner,
                'status' => $c->pivot->status,
            ])->values()->all();
        }

        // „Poslať správu" potrebuje príznak len na detaile (show) — na výpisoch
        // (index) by isContactableBy() robilo dotaz na každý riadok, preto len tu.
        if ($request->route()?->getActionMethod() === 'show') {
            $data['contactable'] = $this->resource->isContactableBy($user);
        }

        return $data;
    }
}
