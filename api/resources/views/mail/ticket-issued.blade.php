@component('mail::message')
# Dobrý deň, {{ $greetingName }}!

Váš lístok na akciu **„{{ $eventName }}"** bol úspešne vytvorený.

@if ($quantity > 1)
Počet rezervovaných miest: **{{ $quantity }}**.
@endif

@if (count($seats))
@foreach ($seats as $seat)
**{{ $seat['label'] }}**@if (!empty($seat['type'])) · {{ $seat['type'] }}@endif

<img src="{{ $message->embedData($seat['png'], 'qr-'.$loop->index.'.png', 'image/png') }}" alt="QR kód" width="200" height="200" style="display:block;border:0;outline:none;margin:6px 0 6px;" />

[Otvoriť QR kód]({{ $seat['qrUrl'] }})
@endforeach

Každá vstupenka má vlastný QR kód. Jednotlivé kódy môžete preposlať aj ďalším účastníkom — pri vstupe sa každý načíta samostatne.
@endif

@if (!empty($pendingCount) && $pendingCount > 0)
Ešte **{{ $pendingCount }}** {{ $pendingCount === 1 ? 'vstupenka čaká' : ($pendingCount <= 4 ? 'vstupenky čakajú' : 'vstupeniek čaká') }} na potvrdenie účastníkmi. Ich QR kód sa vytvorí až po tom, čo potvrdia účasť — o každom potvrdení vás upozorníme e-mailom.
@endif

@component('mail::button', ['url' => $ticketUrl])
Zobraziť lístok a QR kód
@endcomponent

Lístok si prineste v telefóne alebo vytlačte a predložte ho pri vstupe na akciu.
@endcomponent
