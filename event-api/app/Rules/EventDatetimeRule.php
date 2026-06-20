<?php

namespace App\Rules;

use App\Enums\ModelStatus;
use App\Models\Event;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class EventDatetimeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $datetime = Carbon::parse($value);

            // end_at musi byt vzdy striktne po start_at.
            // Toto plati pre draft aj publikovany event.
            if ($attribute === 'end_at') {
                $startAtRaw = request()->input('start_at');

                if ($startAtRaw !== null) {
                    try {
                        $startAt = Carbon::parse($startAtRaw);

                        if (!$datetime->gt($startAt)) {
                            $fail('Pole :attribute musí byť neskoršie ako začiatok udalosti.');
                            return;
                        }
                    } catch (\Exception) {
                        // Invalid start_at is validated by its own rule.
                    }
                }
            }

            // Datum v minulosti zakazujeme iba pre nepublikovane eventy.
            // Po publikovani je povolene upravit event aj na historicky termin.
            if (!$this->isPublishedEvent() && !$datetime->gt(now())) {
                $fail('Pole :attribute musí byť dátum v budúcnosti.');
            }
        } catch (\Exception) {
            $fail('Pole :attribute musí byť platný dátum.');
        }
    }

    private function isPublishedEvent(): bool
    {
        $request = request();
        $status = $request->input('status');
        $publishedAt = $request->input('published_at');

        // Pri create/update berieme publikovanie priamo zo vstupu.
        if ($status === ModelStatus::Published->value || !empty($publishedAt)) {
            return true;
        }

        $routeEvent = $request->route('event') ?? $request->route('id');
        $id = $routeEvent instanceof Event ? $routeEvent->id : $routeEvent;

        if ($id === null) {
            return false;
        }

        /** @var Event|null $event */
        $event = Event::query()->find($id);

        if ($event === null) {
            return false;
        }

        // Pri update bez status/published_at vo vstupe dohladame povodny stav eventu.
        return $event->status === ModelStatus::Published || $event->published_at !== null;
    }
}
