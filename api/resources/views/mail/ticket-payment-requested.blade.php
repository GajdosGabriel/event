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

{{ __('mail.ticket_payment.note') }}

@component('mail::button', ['url' => $ticketUrl])
{{ __('mail.ticket_payment.action') }}
@endcomponent
@endcomponent