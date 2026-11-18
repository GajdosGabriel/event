@component('mail::message')
{{ __('mail.common.greeting') }}

{{ __('mail.message_replied.intro', ['name' => $senderName, 'label' => $labelTo, 'target' => $targetName]) }}

@component('mail::panel')
{!! nl2br(e($body), false) !!}
@endcomponent

{{ __('mail.message_replied.reply_hint') }}

@component('mail::button', ['url' => $inboxUrl])
{{ __('mail.message_replied.action') }}
@endcomponent
@endcomponent
