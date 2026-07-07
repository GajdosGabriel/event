<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Traits\HasCommonFilters;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Objednávka / registrácia — jeden nákup jedného kupujúceho.
 * Jednotlivé vstupenky (miesta) sú v {@see Admission}.
 */
class Ticket extends Model
{
    use HasFactory, SoftDeletes, HasCommonFilters;

    protected $guarded = [];
    protected $appends = ['checked_in_count', 'admissions_total'];

    protected $casts = [
        'status' => TicketStatus::class,
        'payment_status' => TicketPaymentStatus::class,
        'quantity' => 'integer',
        'meta' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->commonPrimarySearchColumns = ['holder_name', 'holder_email'];
        $this->commonSecondarySearchColumns = [];
    }

    protected static function booted()
    {
        static::creating(function (self $ticket) {
            if (empty($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    /** Počet vstupeniek objednávky, ktoré už prešli vchodom. */
    public function getCheckedInCountAttribute(): int
    {
        if ($this->relationLoaded('admissions')) {
            return $this->admissions->whereNotNull('checked_in_at')->count();
        }

        return (int) $this->admissions()->whereNotNull('checked_in_at')->count();
    }

    /** Počet platných vstupeniek objednávky. */
    public function getAdmissionsTotalAttribute(): int
    {
        if ($this->relationLoaded('admissions')) {
            return $this->admissions
                ->where('status', AdmissionStatus::Valid)
                ->count();
        }

        return (int) $this->admissions()
            ->where('status', AdmissionStatus::Valid->value)
            ->count();
    }
}
