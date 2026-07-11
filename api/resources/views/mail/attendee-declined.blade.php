@component('mail::message')
# Dobrý deň{{ $holderName ? ', ' . $holderName : '' }}!

@if ($expired)
**{{ $attendeeName }}** nepotvrdil(a) účasť na akcii **„{{ $eventName }}"** v stanovenej lehote, preto sme@if ($seats > 1) {{ $seats }} rezervované miesta@else jeho/jej rezervované miesto@endif uvoľnili.
@else
**{{ $attendeeName }}** ({{ $attendeeEmail }}) zrušil(a) lístok na akciu **„{{ $eventName }}"**@if ($seats > 1) ({{ $seats }} {{ $seats <= 4 ? 'miesta' : 'miest' }})@endif, takže miesto je opäť voľné.
@endif

Ak sa uvoľnilo miesto na obsadenom podujatí alebo workshope, automaticky sme oň posunuli prvého náhradníka.
@endcomponent
