@component('mail::message')
# {{ $greetingName ? __('mail.common.greeting_named', ['name' => $greetingName]) : __('mail.common.greeting') }}

{{ __('mail.attendee_ticket_issued.' . ($isPaid ? 'intro_paid' : 'intro_free'), ['holder' => $holderName, 'event' => $eventName]) }}

@if (count($seats))
@foreach ($seats as $seat)
{{ empty($seat['type'])
    ? __('mail.common.seat', ['label' => $seat['label']])
    : __('mail.common.seat_typed', ['label' => $seat['label'], 'type' => $seat['type']]) }}

<img src="{{ $message->embedData($seat['png'], 'qr-'.$loop->index.'.png', 'image/png') }}" alt="{{ __('mail.common.qr_alt') }}" width="200" height="200" style="display:block;border:0;outline:none;margin:6px 0 6px;" />

[{{ __('mail.common.qr_open') }}]({{ $seat['qrUrl'] }})
@endforeach
@endif

{{ __('mail.attendee_ticket_issued.outro') }}

@if ($cancelUrl)
{{ __('mail.attendee_ticket_issued.cancel', ['url' => $cancelUrl]) }}
@endif

@if ($needsActivation)
---

{{ __('mail.attendee_ticket_issued.activation') }}

@component('mail::button', ['url' => $activationUrl])
{{ __('mail.attendee_ticket_issued.activation_action') }}
@endcomponent
@endif
@endcomponent
