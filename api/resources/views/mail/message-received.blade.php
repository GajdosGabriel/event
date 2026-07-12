@component('mail::message')
# Nová správa

Dostali ste správu k {{ $label }} **„{{ $targetName }}"**.

**Od:** {{ $senderName }} ({{ $senderEmail }})

@component('mail::panel')
{{ $body }}
@endcomponent

Odpovedať môžete priamo na tento e-mail — odpoveď dorazí odosielateľovi.

@component('mail::button', ['url' => $targetUrl])
Zobraziť {{ $label }}
@endcomponent
@endcomponent
