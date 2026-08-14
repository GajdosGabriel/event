@component('mail::message')
# {{ $eventName }}

@component('mail::panel')
{{ $body }}
@endcomponent

@component('mail::button', ['url' => $eventUrl])
{{ __('mail.event_announcement.action') }}
@endcomponent

@include('mail.partials.calendar')

{{ __('mail.event_announcement.outro') }}
@endcomponent
