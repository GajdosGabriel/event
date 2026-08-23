{{--
    Kostra serverom vykreslenej stránky pre crawlerov.

    Telo NIE je len atrapa pre `<head>`: Google indexuje obsah, nie meta tagy,
    takže tu musí byť skutočný nadpis, dátum, miesto a odkazy ďalej do portálu.
    Bez nich by z toho bola prázdna stránka s peknými OG tagmi.

    Žiadne CSS ani JS — táto odpoveď nikdy nekončí v prehliadači používateľa.
--}}
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $meta['site_title'] }}</title>
    <link rel="canonical" href="{{ $meta['canonical'] }}">
    @if ($meta['description'])
        <meta name="description" content="{{ $meta['description'] }}">
    @endif

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="sk_SK">
    <meta property="og:type" content="{{ $meta['type'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:url" content="{{ $meta['canonical'] }}">
    @if ($meta['description'])
        <meta property="og:description" content="{{ $meta['description'] }}">
    @endif
    @if ($meta['image'])
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta property="og:image:alt" content="{{ $meta['title'] }}">
    @endif

    <meta name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $meta['title'] }}">
    @if ($meta['description'])
        <meta name="twitter:description" content="{{ $meta['description'] }}">
    @endif
    @if ($meta['image'])
        <meta name="twitter:image" content="{{ $meta['image'] }}">
    @endif

    @foreach ($structuredData as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach
</head>
<body>
@yield('content')

<footer>
    <nav>
        <a href="{{ \App\Support\PublicUrl::events() }}">{{ __('seo.page.all_events') }}</a>
        <a href="{{ \App\Support\PublicUrl::thisWeekend() }}">{{ __('seo.page.weekend') }}</a>
        {{-- Archív je v pätičke každej stránky zámerne: je to jediný vstup do
             skončených podujatí a crawler ho tak nájde odkiaľkoľvek. --}}
        <a href="{{ \App\Support\PublicUrl::archive() }}">{{ __('seo.page.archive') }}</a>
    </nav>
</footer>
</body>
</html>
