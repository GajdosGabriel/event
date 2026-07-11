@component('mail::message')
# Dobrá správa{{ $holderName ? ', ' . $holderName : '' }}!

**{{ $attendeeName }}** potvrdil(a) účasť na akcii **„{{ $eventName }}"**@if ($seats > 1) ({{ $seats }} {{ $seats <= 4 ? 'miesta' : 'miest' }})@endif.

Jeho/jej vstupenku s QR kódom sme práve poslali na **{{ $attendeeEmail }}**.

@component('mail::button', ['url' => $ticketUrl])
Zobraziť objednávku
@endcomponent
@endcomponent
