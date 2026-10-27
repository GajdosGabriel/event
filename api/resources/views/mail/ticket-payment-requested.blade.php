@component('mail::message')
# {{ __('mail.common.greeting_named', ['name' => $greetingName]) }}

{{ __('mail.ticket_payment.intro', ['event' => $eventName]) }}

**{{ __('mail.ticket_payment.recap') }}**

@foreach ($seats as $seat)
{{ empty($seat['type'])
    ? __('mail.common.seat', ['label' => $seat['label']])
    : __('mail.common.seat_typed', ['label' => $seat['label'], 'type' => $seat['type']]) }}

@include('mail.partials.answers', ['answers' => $seat['answers'] ?? []])
@endforeach
@if (!empty($answers))
@include('mail.partials.answers', ['answers' => $answers])
@endif

{{ __('mail.ticket_payment.amount', ['amount' => $amount]) }}

@if (!empty($payment))
**{{ __('mail.ticket_payment.bank_title') }}**

{{ __('mail.ticket_payment.bank_recipient') }}: **{{ $payment['recipient'] }}**<br>
{{ __('mail.ticket_payment.bank_iban') }}: **{{ $payment['iban'] }}**<br>
@if (!empty($payment['swift']))
{{ __('mail.ticket_payment.bank_swift') }}: {{ $payment['swift'] }}<br>
@endif
{{ __('mail.ticket_payment.bank_vs') }}: **{{ $payment['variable_symbol'] }}**<br>
{{ __('mail.ticket_payment.bank_amount') }}: **{{ $amount }}**<br>
{{ __('mail.ticket_payment.bank_note') }}: {{ $payment['note'] }}

{{ __('mail.ticket_payment.bank_after') }}
@else
{{ __('mail.ticket_payment.note') }}
@endif

@component('mail::button', ['url' => $ticketUrl])
{{ __('mail.ticket_payment.action') }}
@endcomponent
@endcomponent