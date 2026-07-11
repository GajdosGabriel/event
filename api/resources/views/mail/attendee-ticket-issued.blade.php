@component('mail::message')
# Dobrý deň{{ $greetingName ? ', ' . $greetingName : '' }}!

**{{ $holderName }}** {{ $isPaid ? 'vám objednal(a) vstupenku' : 'vám rezervoval(a) miesto' }} na akciu **„{{ $eventName }}"**.

@if (count($seats))
@foreach ($seats as $seat)
**{{ $seat['label'] }}**@if (!empty($seat['type'])) · {{ $seat['type'] }}@endif

<img src="{{ $message->embedData($seat['png'], 'qr-'.$loop->index.'.png', 'image/png') }}" alt="QR kód" width="200" height="200" style="display:block;border:0;outline:none;margin:6px 0 6px;" />

[Otvoriť QR kód]({{ $seat['qrUrl'] }})
@endforeach
@endif

Vstupenku si preneste v telefóne alebo vytlačte a QR kód predložte pri vstupe na akciu.

@if ($needsActivation)
---

Na túto e-mailovú adresu sme vám založili účet, aby ste mali svoje lístky vždy poruke. Účet si plne aktivujete prihlásením — potvrdíte tým svoju e-mailovú adresu a odsúhlasíte podmienky.

@component('mail::button', ['url' => $activationUrl])
Aktivovať účet
@endcomponent
@endif
@endcomponent
