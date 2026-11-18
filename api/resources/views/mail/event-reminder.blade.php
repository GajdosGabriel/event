@component('mail::message')
{{ $greeting }}

{{ $intro }}

@if ($startsAt)
{{ __('mail.event_reminder.starts_at', ['date' => $startsAt]) }}
@endif
@if ($venueName)

{{ __('mail.event_reminder.venue', ['venue' => implode(', ', array_filter([$venueName, $venueAddress]))]) }}
@endif

@component('mail::button', ['url' => $eventUrl])
{{ __('mail.event_reminder.action') }}
@endcomponent

@include('mail.partials.calendar')

{{-- Účastníkovi pripomíname vstupenku, odberateľovi nie — nijakú nemá.
     Text vyberá notifikácia, šablóna o publikách nemusí vedieť. --}}
{{ $outro ?? __('mail.event_reminder.outro') }}

@include('mail.partials.unsubscribe')
@endcomponent
