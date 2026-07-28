@component('mail::message')
# {{ __('mail.message_received.heading') }}

{{ __('mail.message_received.intro', ['label' => $label, 'name' => $targetName]) }}

{{ __('mail.message_received.from', ['name' => $senderName, 'email' => $senderEmail]) }}

@component('mail::panel')
{{ $body }}
@endcomponent

{{ __('mail.message_received.reply_hint') }}

@component('mail::button', ['url' => $targetUrl])
{{ __('mail.message_received.action', ['label' => $label]) }}
@endcomponent
@endcomponent
