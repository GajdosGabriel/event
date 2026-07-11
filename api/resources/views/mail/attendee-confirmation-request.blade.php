@component('mail::message')
# Dobrý deň{{ $greetingName ? ', ' . $greetingName : '' }}!

**{{ $holderName }}** {{ $isPaid ? 'vám objednal(a) vstupenku' : 'vám rezervoval(a) miesto' }} na akciu **„{{ $eventName }}"**.

Aby sme vám miesto podržali, potvrďte prosím svoju účasť.

@if (count($seats))
@foreach ($seats as $seat)
- **{{ $seat['label'] }}**@if (!empty($seat['type'])) · {{ $seat['type'] }}@endif
@endforeach
@endif

@if ($deadline)
Potvrďte prosím **do {{ $deadline }}**. Ak sa tak nestane, rezervácia sa automaticky zruší a miesto uvoľníme ďalším záujemcom.
@endif

@component('mail::button', ['url' => $confirmUrl, 'color' => 'success'])
Potvrdiť účasť
@endcomponent

@component('mail::button', ['url' => $declineUrl, 'color' => 'error'])
Zrušiť lístok
@endcomponent

Ak ste o túto rezerváciu nežiadali, jednoducho lístok zrušte alebo tento e-mail ignorujte — miesto sa po lehote uvoľní samo.

@if ($needsActivation)
---

Na túto e-mailovú adresu sme vám založili účet, aby ste mali svoje lístky vždy poruke. Plne ho aktivujete prihlásením.
@endif
@endcomponent
