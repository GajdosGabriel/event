@component('mail::message')
{{ $greeting }}

{{ $intro }}

@component('mail::panel')
{{ $body }}
@endcomponent

@if ($authorName)
{{ __('mail.private_question_received.from', ['name' => $authorName]) }}
@endif

{{ $hint }}

@component('mail::button', ['url' => $boardUrl])
{{ $action }}
@endcomponent

{{ $outro }}
@endcomponent
