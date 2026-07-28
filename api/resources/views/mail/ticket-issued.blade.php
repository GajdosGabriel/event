@component('mail::message')
# {{ __('mail.common.greeting_named', ['name' => $greetingName]) }}

{{ __('mail.ticket_issued.intro', ['event' => $eventName]) }}

@if ($quantity > 1)
{{ __('mail.ticket_issued.quantity', ['count' => $quantity]) }}
@endif

@if (count($seats))
@foreach ($seats as $seat)
{{ empty($seat['type'])
    ? __('mail.common.seat', ['label' => $seat['label']])
    : __('mail.common.seat_typed', ['label' => $seat['label'], 'type' => $seat['type']]) }}

<img src="{{ $message->embedData($seat['png'], 'qr-'.$loop->index.'.png', 'image/png') }}" alt="{{ __('mail.common.qr_alt') }}" width="200" height="200" style="display:block;border:0;outline:none;margin:6px 0 6px;" />

[{{ __('mail.common.qr_open') }}]({{ $seat['qrUrl'] }})
@endforeach

{{ __('mail.ticket_issued.qr_note') }}
@endif

@if (!empty($pendingCount) && $pendingCount > 0)
{{ trans_choice('mail.ticket_issued.pending', $pendingCount) }} {{ __('mail.ticket_issued.pending_note') }}
@endif

@component('mail::button', ['url' => $ticketUrl])
{{ __('mail.ticket_issued.action') }}
@endcomponent

{{ __('mail.ticket_issued.outro') }}
@endcomponent
