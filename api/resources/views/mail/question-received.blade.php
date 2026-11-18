@component('mail::message')
{{ $greeting }}

{{ $intro }}

@component('mail::panel')
{!! nl2br(e($body), false) !!}
@endcomponent

@if ($authorName)
{{ __('mail.question_received.from', ['name' => $authorName]) }}
@endif

{{ $hint }}

@component('mail::button', ['url' => $boardUrl])
{{ $action }}
@endcomponent
@endcomponent
