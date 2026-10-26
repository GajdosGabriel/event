<?php

namespace App\Models;

use App\Enums\QuestionScope;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Otázka dotazníka k registrácii (strava, ubytovanie, alergie…).
 * Pri výberových typoch má možnosti {@see EventQuestionOption}, ktoré môžu
 * niesť cenu aj kapacitu.
 */
class EventQuestion extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'scope' => QuestionScope::class,
        'type' => QuestionType::class,
        'is_required' => 'boolean',
        'ticket_type_ids' => 'array',
        'editable_by_attendee' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function options()
    {
        return $this->hasMany(EventQuestionOption::class, 'question_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function answers()
    {
        return $this->hasMany(TicketAnswer::class, 'question_id');
    }

    public function isOrderScope(): bool
    {
        return $this->scope === QuestionScope::Order;
    }

    /**
     * Platí otázka pre miesto daného typu? Bez výberu typov sa pýta len pri
     * hlavných vstupenkách — workshop je doplnok k nim, nie ďalší účastník.
     */
    public function appliesToType(TicketType $type): bool
    {
        $ids = $this->ticket_type_ids;

        if ($ids === null || $ids === []) {
            return ! $type->isWorkshop();
        }

        return in_array((int) $type->id, array_map('intval', $ids), true);
    }
}
