@component('mail::message')
{{ $greeting }}

{{ __('mail.subscription_confirmed.intro', ['event' => $eventName]) }}

@if ($startsAt)
{{ __('mail.subscription_confirmed.starts_at', ['date' => $startsAt]) }}
@endif
@if ($venueName)

{{ __('mail.subscription_confirmed.venue', ['venue' => trim($venueName . ' ' . $venueAddress)]) }}
@endif

@component('mail::button', ['url' => $eventUrl])
{{ __('mail.subscription_confirmed.action') }}
@endcomponent

@include('mail.partials.calendar')

{{ __('mail.subscription_confirmed.outro') }}

@include('mail.partials.unsubscribe')
@endcomponent
