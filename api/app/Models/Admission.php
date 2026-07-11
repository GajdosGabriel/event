<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
use App\Enums\TicketTypeKind;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Admission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ticket_admissions';

    protected $guarded = [];
    protected $hidden = ['qr_token'];
    protected $appends = ['is_checked_in'];

    protected $casts = [
        'status' => AdmissionStatus::class,
        'confirmation_status' => AttendeeConfirmationStatus::class,
        'checked_in_at' => 'datetime',
        'confirmation_deadline_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (self $admission) {
            if (empty($admission->uuid)) {
                $admission->uuid = (string) Str::uuid();
            }

            if (empty($admission->qr_token)) {
                $admission->qr_token = Str::random(64);
            }
        });
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function getIsCheckedInAttribute(): bool
    {
        return $this->checked_in_at !== null;
    }

    /** Vstupenka ešte čaká na potvrdenie účastníkom — miesto je držané, ale neaktívne. */
    public function isPendingConfirmation(): bool
    {
        return $this->confirmation_status === AttendeeConfirmationStatus::Pending;
    }

    /**
     * Platné hlavné vstupenky (nie workshopy) daného podujatia —
     * len tie sa rátajú do kapacity eventu a nároku na workshopy.
     */
    public function scopeMainSeats($query, int $eventId)
    {
        return $query
            ->where('event_id', $eventId)
            ->where('status', AdmissionStatus::Valid->value)
            ->where(function ($q) {
                $q->whereNull('ticket_type_id')
                    ->orWhereHas('ticketType', fn ($t) => $t->where('kind', TicketTypeKind::Ticket->value));
            });
    }
}
