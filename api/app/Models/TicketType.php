<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\TicketTypeKind;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'kind' => TicketTypeKind::class,
        'price_amount' => 'integer',
        'capacity' => 'integer',
        'max_per_order' => 'integer',
        'min_per_order' => 'integer',
        'requires_attendee_name' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $appends = ['sold_count', 'remaining_capacity', 'on_sale'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function isWorkshop(): bool
    {
        return $this->kind === TicketTypeKind::Workshop;
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    /** Počet vydaných (platných) vstupeniek tohto typu. */
    public function getSoldCountAttribute(): int
    {
        return (int) $this->admissions()
            ->where('status', AdmissionStatus::Valid->value)
            ->count();
    }

    public function getRemainingCapacityAttribute(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->sold_count);
    }

    /** Je typ práve v predaji (aktívny + v predajnom okne)? */
    public function getOnSaleAttribute(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->sale_starts_at !== null && $this->sale_starts_at->isFuture()) {
            return false;
        }

        if ($this->sale_ends_at !== null && $this->sale_ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
