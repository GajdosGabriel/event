@component('mail::message')
{{ $greeting }}

@if ($cancelled)
{{ __('mail.event_changed.intro_cancelled', ['event' => $eventName]) }}
@else
{{ __('mail.event_changed.intro', ['event' => $eventName]) }}

@foreach ($changes as $change)
- {{ $change }}
@endforeach
@endif

@unless ($cancelled)
@if ($startsAt)

{{ __('mail.event_changed.starts_at', ['date' => $startsAt]) }}
@endif
@if ($venueName)

{{ __('mail.event_changed.venue', ['venue' => $venueName]) }}
@endif
@endunless

@component('mail::button', ['url' => $eventUrl])
{{ __('mail.event_changed.action') }}
@endcomponent

@unless ($cancelled)
{{-- Termín sa zmenil, takže starý záznam v kalendári je odteraz zlý. Súbor má
     rovnaké UID a vyššie SEQUENCE, čiže ho kalendár prepíše, nezaloží druhý. --}}
@include('mail.partials.calendar')
@endunless

@include('mail.partials.unsubscribe')
@endcomponent
