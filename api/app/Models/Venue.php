<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Models\Municipality;
use App\Models\Traits\{HasCommonFilters, HasFile};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Venue extends Model
{
    /**
     * Venue je fyzicke miesto, kde sa event kona alebo kde ma canal sidlo.
     */
    use HasFactory, SoftDeletes, HasFile, HasCommonFilters;

    protected $guarded = [];
    protected $appends = ['primary_image', 'thumb_image', 'files', 'canal_id', 'canal_ids'];

    protected array $pendingCanalIds = [];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class, 'village_id');
    }

    public function canal()
    {
        return $this->canals();
    }

    public function canals()
    {
        return $this->belongsToMany(Canal::class)
            ->withPivot(['is_owner', 'status'])
            ->withTimestamps();
    }

    public function activeCanals()
    {
        return $this->canals()->wherePivot('status', ModelStatus::Published->value);
    }

    public function ownerCanals()
    {
        return $this->canals()->wherePivot('is_owner', true);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function assignCanal(Canal|int $canal, bool $isOwner = false, ModelStatus|string|bool|null $status = null): void
    {
        $canalId = $canal instanceof Canal ? $canal->id : $canal;
        $status = match (true) {
            $status instanceof ModelStatus => $status->value,
            $status === false => ModelStatus::Draft->value,
            $status === true || $status === null => ModelStatus::Published->value,
            default => $status,
        };

        if ($isOwner) {
            $this->canals()
                ->newPivotStatement()
                ->where('venue_id', $this->id)
                ->where('is_owner', true)
                ->update(['is_owner' => false, 'updated_at' => now()]);
        }

        $this->canals()->syncWithoutDetaching([
            (int) $canalId => [
                'is_owner' => $isOwner,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function syncCanalAssignments(array $canalIds, bool $markFirstAsOwner = false, ?int $ownerCanalId = null): void
    {
        $canalIds = collect($canalIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $this->canals()->sync(
            $canalIds
                ->mapWithKeys(fn (int $canalId, int $index) => [
                    $canalId => [
                        'is_owner' => $ownerCanalId !== null
                            ? $canalId === $ownerCanalId
                            : $markFirstAsOwner && $index === 0,
                        'status' => ModelStatus::Published->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ])
                ->all()
        );
    }

    public function setCanalIdAttribute($value): void
    {
        if ($value !== null && $value !== '') {
            $this->pendingCanalIds = [(int) $value];
        }
    }

    public function getCanalIdAttribute(): ?int
    {
        if ($this->relationLoaded('canal')) {
            return $this->getRelation('canal')->first()?->id;
        }

        if ($this->relationLoaded('canals')) {
            return $this->getRelation('canals')->first()?->id;
        }

        if (! $this->exists) {
            return $this->pendingCanalIds[0] ?? null;
        }

        return $this->canal()->value('canals.id');
    }

    public function getCanalIdsAttribute(): array
    {
        if ($this->relationLoaded('canal')) {
            return $this->getRelation('canal')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->relationLoaded('canals')) {
            return $this->getRelation('canals')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $this->exists) {
            return $this->pendingCanalIds;
        }

        return $this->canal()->pluck('canals.id')->map(fn ($id) => (int) $id)->all();
    }

    public function pendingCanalIds(): array
    {
        return $this->pendingCanalIds;
    }

    protected function defaultThumbImageUrl(): string
    {
        $modelFallback = 'storage/images/venue.jpg';
        $sharedFallback = 'storage/images/default.png';

        return file_exists(public_path($modelFallback))
            ? url($modelFallback)
            : url($sharedFallback);
    }
}
