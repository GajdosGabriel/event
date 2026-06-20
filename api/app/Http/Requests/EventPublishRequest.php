<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Rules\EventDatetimeRule;
use App\Rules\ValidUrlRule;
use Illuminate\Foundation\Http\FormRequest;

class EventPublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $routeEvent = $this->route('event') ?? $this->route('id');
        $eventId = $routeEvent instanceof Event ? $routeEvent->id : $routeEvent;

        if ($eventId === null) {
            return;
        }

        /** @var Event $event */
        $event = Event::query()->findOrFail($eventId);

        $this->merge([
            'name' => $event->name,
            'body' => $event->body,
            'start_at' => $event->start_at,
            'end_at' => $event->end_at,
            'registration_deadline_at' => $event->registration_deadline_at,
            'canal_id' => $event->canal_id,
            'venue_id' => $event->venue_id,
            'website' => $event->website,
            'email' => $event->email,
            'phone' => $event->phone,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:250'],
            'body' => ['nullable', 'string'],
            'start_at' => ['required', new EventDatetimeRule],
            'end_at' => ['required', new EventDatetimeRule],
            'registration_deadline_at' => ['nullable', 'date', 'before_or_equal:start_at'],
            'canal_id' => ['required', 'integer', 'exists:canals,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'website' => ['nullable', 'url', new ValidUrlRule()],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
