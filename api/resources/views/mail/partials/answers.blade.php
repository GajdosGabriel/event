{{-- Odpovede dotazníka (strava, ubytovanie…) — zoznam „Otázka: odpoveď (+príplatok)". --}}
@if (!empty($answers))
@foreach ($answers as $answer)
- {{ $answer['label'] }}: **{{ $answer['display'] }}**{{ !empty($answer['price_amount']) ? ' (+'.number_format($answer['price_amount'] / 100, 2, ',', ' ').' €)' : '' }}
@endforeach

@endif
