@component('mail::message')
# Dobrý deň{{ $holderName ? ', ' . $holderName : '' }}!

@if ($expired)
@php
    // Blade nekompiluje direktívu nalepenú na písmeno (`sme@if`), preto text
    // skladáme dopredu a do vety ho vložíme cez výraz.
    $freedSeats = match (true) {
        $seats <= 1 => 'jeho/jej rezervované miesto',
        $seats <= 4 => $seats . ' rezervované miesta',
        default     => $seats . ' rezervovaných miest',
    };
@endphp
**{{ $attendeeName }}** nepotvrdil(a) účasť na akcii **„{{ $eventName }}"** v stanovenej lehote, preto sme {{ $freedSeats }} uvoľnili.
@else
**{{ $attendeeName }}** ({{ $attendeeEmail }}) zrušil(a) lístok na akciu **„{{ $eventName }}"**@if ($seats > 1) ({{ $seats }} {{ $seats <= 4 ? 'miesta' : 'miest' }})@endif, takže miesto je opäť voľné.
@endif

Ak sa uvoľnilo miesto na obsadenom podujatí alebo workshope, automaticky sme oň posunuli prvého náhradníka.
@endcomponent
