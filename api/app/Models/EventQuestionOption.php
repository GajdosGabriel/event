<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventQuestionOption extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'price_amount' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function question()
    {
        return $this->belongsTo(EventQuestion::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(TicketAnswer::class, 'option_id');
    }

    /**
     * Odpovede, ktoré obsadzujú kapacitu možnosti: nezrušená objednávka
     * a pri odpovedi za miesto platné miesto.
     */
    public function takenAnswers()
    {
        return $this->answers()
            ->whereHas('ticket', fn ($q) => $q->where('status', '!=', TicketStatus::Cancelled->value))
            ->where(fn ($q) => $q
                ->whereNull('admission_id')
                ->orWhereHas('admission', fn ($a) => $a->where('status', AdmissionStatus::Valid->value)));
    }

    public function takenCount(): int
    {
        return (int) $this->takenAnswers()->count();
    }

    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->takenCount());
    }
}
