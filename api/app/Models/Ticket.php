<?php

namespace App\Models;

use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Traits\HasCommonFilters;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, SoftDeletes, HasCommonFilters;

    protected $guarded = [];
    protected $hidden = ['qr_token'];
    protected $appends = ['is_checked_in'];

    protected $casts = [
        'status' => TicketStatus::class,
        'payment_status' => TicketPaymentStatus::class,
        'checked_in_at' => 'datetime',
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

            if (empty($ticket->qr_token)) {
                $ticket->qr_token = Str::random(64);
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

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function getIsCheckedInAttribute(): bool
    {
        return $this->checked_in_at !== null;
    }
}
