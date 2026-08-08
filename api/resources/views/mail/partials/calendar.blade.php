{{--
    „Pridať do kalendára". Súbor `.ics` je zároveň prílohou e-mailu — Gmail aj
    Apple Mail nad ňou vykreslia vlastné tlačidlo, tieto odkazy sú pre zvyšok
    klientov. Bez termínu podujatia príde $calendarUrl null a sekcia vypadne.
--}}
@if (! empty($calendarUrl))

{{ __('mail.common.calendar_intro') }}

[{{ __('mail.common.calendar_ics') }}]({{ $calendarUrl }})@if (! empty($googleUrl)) · [{{ __('mail.common.calendar_google') }}]({{ $googleUrl }})@endif

@endif
