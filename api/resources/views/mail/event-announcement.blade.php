@component('mail::message')
# {{ $eventName }}

@include('mail.partials.when')

@component('mail::panel')
{!! nl2br(e($body), false) !!}
@endcomponent

@component('mail::button', ['url' => $eventUrl])
{{ __('mail.event_announcement.action') }}
@endcomponent

@include('mail.partials.calendar')

{{ __('mail.event_announcement.outro') }}
@endcomponent
