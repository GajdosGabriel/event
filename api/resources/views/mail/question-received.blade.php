@component('mail::message')
{{ $greeting }}

{{ $intro }}

@component('mail::panel')
{{ $body }}
@endcomponent

@if ($authorName)
{{ __('mail.question_received.from', ['name' => $authorName]) }}
@endif

{{ $hint }}

@component('mail::button', ['url' => $boardUrl])
{{ $action }}
@endcomponent
@endcomponent
