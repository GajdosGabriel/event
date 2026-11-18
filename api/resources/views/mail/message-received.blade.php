@component('mail::message')
{{ __('mail.common.greeting') }}

{{ __('mail.message_received.intro', ['label' => $labelTo, 'name' => $targetName]) }}

{{ __('mail.message_received.from', ['name' => $senderName, 'email' => $senderEmail]) }}

@component('mail::panel')
{!! nl2br(e($body), false) !!}
@endcomponent

{{ __('mail.message_received.reply_hint') }}

@component('mail::button', ['url' => $targetUrl])
{{ __('mail.message_received.action', ['label' => $label]) }}
@endcomponent
@endcomponent
