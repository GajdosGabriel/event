{{--
    „Pridať do kalendára". Súbor `.ics` býva zároveň prílohou e-mailu — Gmail aj
    Apple Mail nad ňou vykreslia vlastné tlačidlo, tieto odkazy sú pre zvyšok
    klientov: kto má kalendár v prehliadači, so stiahnutým súborom nespraví nič.

    Premenné dodáva App\Services\Calendar\EventCalendarLinks::viewData(). Bez
    termínu podujatia prídu všetky null a sekcia vypadne, preto stačí
    `@include('mail.partials.calendar')` bez parametrov.
--}}
@php
    $calendarTargets = array_filter([
        'mail.common.calendar_google' => $googleUrl ?? null,
        'mail.common.calendar_outlook' => $outlookUrl ?? null,
        'mail.common.calendar_ics' => $calendarUrl ?? null,
    ]);
@endphp
@if ($calendarTargets)
@component('mail::panel')
**{{ __('mail.common.calendar_title') }}**

{{ __('mail.common.calendar_intro') }}

{!! implode(' · ', array_map(fn ($key, $url) => '['.__($key).']('.$url.')', array_keys($calendarTargets), $calendarTargets)) !!}
@endcomponent
@endif
