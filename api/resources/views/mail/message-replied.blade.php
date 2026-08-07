@component('mail::message')
# {{ __('mail.message_replied.heading') }}

{{ __('mail.message_replied.intro', ['name' => $senderName, 'label' => $label, 'target' => $targetName]) }}

@component('mail::panel')
{{ $body }}
@endcomponent

{{ __('mail.message_replied.reply_hint') }}

@component('mail::button', ['url' => $inboxUrl])
{{ __('mail.message_replied.action') }}
@endcomponent
@endcomponent
