@component('mail::message')
{{ $greeting }}

{{ __('mail.canal_follow.intro', ['canal' => $canalName]) }}

@component('mail::button', ['url' => $canalUrl])
{{ __('mail.canal_follow.action') }}
@endcomponent

{{ __('mail.canal_follow.outro') }}

@include('mail.partials.unsubscribe')
@endcomponent
