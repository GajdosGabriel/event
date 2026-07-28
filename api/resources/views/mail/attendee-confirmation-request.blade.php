@component('mail::message')
# {{ $greetingName ? __('mail.common.greeting_named', ['name' => $greetingName]) : __('mail.common.greeting') }}

{{ __('mail.attendee_confirmation_request.' . ($isPaid ? 'intro_paid' : 'intro_free'), ['holder' => $holderName, 'event' => $eventName]) }}

{{ __('mail.attendee_confirmation_request.ask') }}

@if (count($seats))
@foreach ($seats as $seat)
- {{ empty($seat['type'])
    ? __('mail.common.seat', ['label' => $seat['label']])
    : __('mail.common.seat_typed', ['label' => $seat['label'], 'type' => $seat['type']]) }}
@endforeach
@endif

@if ($deadline)
{{ __('mail.attendee_confirmation_request.deadline', ['deadline' => $deadline]) }}
@endif

@component('mail::button', ['url' => $confirmUrl, 'color' => 'success'])
{{ __('mail.attendee_confirmation_request.confirm') }}
@endcomponent

@component('mail::button', ['url' => $declineUrl, 'color' => 'error'])
{{ __('mail.attendee_confirmation_request.decline') }}
@endcomponent

{{ __('mail.attendee_confirmation_request.ignore') }}

@if ($needsActivation)
---

{{ __('mail.attendee_confirmation_request.activation') }}
@endif
@endcomponent
