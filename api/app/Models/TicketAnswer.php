<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Odpoveď na otázku dotazníka. Pri odpovedi za objednávku je admission_id
 * null; výber viacerých možností = jeden riadok na možnosť.
 */
class TicketAnswer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_amount' => 'integer',
    ];

    public function question()
    {
        return $this->belongsTo(EventQuestion::class, 'question_id')->withTrashed();
    }

    public function option()
    {
        return $this->belongsTo(EventQuestionOption::class, 'option_id')->withTrashed();
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }
}
