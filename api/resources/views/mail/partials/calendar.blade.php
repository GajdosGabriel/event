{{--
    „Pridať do kalendára". Súbor `.ics` je zároveň prílohou e-mailu — Gmail aj
    Apple Mail nad ňou vykreslia vlastné tlačidlo, tieto odkazy sú pre zvyšok
    klientov: webový kalendár so stiahnutým súborom nespraví nič.

    Bez termínu podujatia prídu všetky odkazy null a sekcia vypadne.
--}}
@php
    $calendarTargets = array_filter([
        'mail.common.calendar_ics' => $calendarUrl ?? null,
        'mail.common.calendar_google' => $googleUrl ?? null,
        'mail.common.calendar_outlook' => $outlookUrl ?? null,
    ]);
@endphp
@if (! empty($calendarUrl))

{{ __('mail.common.calendar_intro') }}

{!! implode(' · ', array_map(fn ($key, $url) => '['.__($key).']('.$url.')', array_keys($calendarTargets), $calendarTargets)) !!}

@endif
