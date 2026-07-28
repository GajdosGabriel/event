@component('mail::message')
# {{ $holderName ? __('mail.attendee_confirmed.heading_named', ['name' => $holderName]) : __('mail.attendee_confirmed.heading') }}

{{ trans_choice('mail.attendee_confirmed.intro', $seats, ['attendee' => $attendeeName, 'event' => $eventName]) }}

{{ __('mail.attendee_confirmed.ticket_sent', ['email' => $attendeeEmail]) }}

@component('mail::button', ['url' => $ticketUrl])
{{ __('mail.attendee_confirmed.action') }}
@endcomponent
@endcomponent
