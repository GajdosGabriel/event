@component('mail::message')
{{ $greeting }}

{{ __('mail.event_reminder.intro', ['event' => $eventName]) }}

@if ($startsAt)
{{ __('mail.event_reminder.starts_at', ['date' => $startsAt]) }}
@endif
@if ($venueName)

{{ __('mail.event_reminder.venue', ['venue' => trim($venueName . ' ' . $venueAddress)]) }}
@endif

@component('mail::button', ['url' => $eventUrl])
{{ __('mail.event_reminder.action') }}
@endcomponent

{{ __('mail.event_reminder.outro') }}
@endcomponent
