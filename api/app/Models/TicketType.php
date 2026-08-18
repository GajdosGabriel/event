<?php

namespace App\Models;

use App\Contracts\HasQuestionBoard;
use App\Enums\AdmissionStatus;
use App\Enums\TicketTypeKind;
use App\Models\Traits\InteractsAsQuestionBoard;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketType extends Model implements HasQuestionBoard
{
    use HasFactory, InteractsAsQuestionBoard, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'kind' => TicketTypeKind::class,
        'price_amount' => 'integer',
        'capacity' => 'integer',
        'max_per_order' => 'integer',
        'min_per_order' => 'integer',
        'requires_attendee_name' => 'boolean',
        'open_to_public' => 'boolean',
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

    /**
     * Nástenka otázok na workshope dedí viditeľnosť aj práva od podujatia.
     * Bežný typ lístka („Štandard", „VIP") nástenku nedostane — pýtať sa dá na
     * program, nie na cenovú hladinu. Rozhoduje o tom volajúci; model tu len
     * povie, kam workshop patrí.
     */
    public function questionBoardEvent(): ?Event
    {
        return $this->relationLoaded('event')
            ? $this->getRelation('event')
            : $this->event()->first();
    }

    public function questionBoardTitle(): string
    {
        return (string) $this->name;
    }

    /** Workshop otvorený aj pre neregistrovaných — nevyžaduje hlavnú vstupenku. */
    public function isOpenWorkshop(): bool
    {
        return $this->isWorkshop() && (bool) $this->open_to_public;
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
