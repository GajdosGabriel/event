@component('mail::message')
# {{ $holderName ? __('mail.common.greeting_named', ['name' => $holderName]) : __('mail.common.greeting') }}

@if ($expired)
{{ trans_choice('mail.attendee_declined.expired', $seats, ['attendee' => $attendeeName, 'event' => $eventName]) }}
@else
{{ trans_choice('mail.attendee_declined.declined', $seats, ['attendee' => $attendeeName, 'email' => $attendeeEmail, 'event' => $eventName]) }}
@endif

{{ __('mail.attendee_declined.waitlist_note') }}
@endcomponent
