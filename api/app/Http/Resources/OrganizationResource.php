<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use App\Http\Resources\Traits\HasAllowedStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    use HasAllowedStatuses;

    /**
     * Fakturačné údaje z Accountu. Nepatria do modelu ani do tabuľky —
     * pripájajú sa až v detaile, kde sa Account naozaj volá.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $account = null;

    /** @param  array<string, mixed>|null  $account */
    public function withAccount(?array $account): static
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'village_id' => $this->village_id,
            'title' => $this->title,
            // Súkromná osoba namiesto firmy – riadi, či sa vôbec pýta IČO.
            'person' => (bool) $this->person,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'status' => $this->status,
            'published' => (bool) $this->published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'allowed_statuses' => $this->allowedStatuses($request),
            // Väzba na centrálnu evidenciu firiem. `account` je vyplnené len
            // v detaile; v zozname by to znamenalo volanie na riadok.
            'account_uuid' => $this->account_uuid,
            'account_synced_at' => $this->account_synced_at,
            'account' => $this->account,
            'permissions' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $this->status !== ModelStatus::Published && ($user?->can('delete', $this->resource) ?? false),
                'archive' => $this->status === ModelStatus::Published && ($user?->can('archive', $this->resource) ?? false),
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],
        ];
    }
}
