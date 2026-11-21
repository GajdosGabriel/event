@component('mail::message')
{{ $greeting }}

@if ($pending)
{{ __('mail.canal_follow.intro_pending', ['canal' => $canalName]) }}

@component('mail::button', ['url' => $actionUrl])
{{ __('mail.canal_follow.action_pending') }}
@endcomponent

{{ __('mail.canal_follow.outro_pending') }}
@else
{{ __('mail.canal_follow.intro_confirmed', ['canal' => $canalName]) }}

@component('mail::button', ['url' => $actionUrl])
{{ __('mail.canal_follow.action_confirmed') }}
@endcomponent
@endif

@include('mail.partials.unsubscribe')
@endcomponent
