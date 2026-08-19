{{--
    Odhlásenie odberu. Patrí do každého e-mailu, ktorý vznikol z „Pripomeň mi" —
    adresu sme dostali bez overenia účtu, takže jediná obrana človeka, ktorému ju
    zadal niekto iný, je odkaz, čo mu práve prišiel do schránky.

    Token v odkaze je autorizácia (rovnako ako RSVP) a odhlásenie je idempotentné,
    takže druhý klik nič nerozbije.

    Očakáva premennú `$unsubscribeUrl`; bez nej sekcia vypadne, takže e-maily pre
    účastníkov s lístkom môžu ten istý include pokojne obsahovať.
--}}
@isset($unsubscribeUrl)
@component('mail::subcopy')
{{ __('mail.common.unsubscribe_intro') }} [{{ __('mail.common.unsubscribe_action') }}]({{ $unsubscribeUrl }})
@endcomponent
@endisset
