@component('mail::message')
{{ $greeting }}

{{ __('mail.canal_new_events.intro', ['canal' => $canalName]) }}

@foreach ($events as $event)
- **[{{ $event['name'] }}]({{ $event['url'] }})**{{ $event['when'] ? ' – '.$event['when'] : '' }}{{ $event['where'] ? ', '.$event['where'] : '' }}
@endforeach
@if ($more > 0)

{{ __('mail.canal_new_events.more', ['count' => $more]) }}
@endif

@component('mail::button', ['url' => $canalUrl])
{{ __('mail.canal_new_events.action') }}
@endcomponent

{{ __('mail.canal_new_events.outro', ['canal' => $canalName]) }}

@include('mail.partials.unsubscribe')
@endcomponent
