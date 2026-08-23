@extends('prerender.layout')

@section('content')
    <h1>{{ $venue->name }}</h1>

    <address>
        @if ($venue->street){{ $venue->street }}@endif
        @if ($venue->postcode), {{ $venue->postcode }}@endif
        @if ($venue->municipality), {{ $venue->municipality->shortname }}@endif
    </address>

    @if ($venue->has_primary_image)
        <img src="{{ $venue->primary_image['large'] }}" alt="{{ $venue->name }}">
    @endif

    @if ($bodyHtml)
        {!! $bodyHtml !!}
    @endif

    <h2>{{ __('seo.page.upcoming') }}</h2>
    @include('prerender._events', ['events' => $events])

    {{-- Uplynulé podujatia. Pre človeka je to referencia miesta („čo sa tu už
         konalo"), pre crawlera jediná cesta z portálu na ich detaily — bez nej
         sú to osirené stránky, ktoré Google časom vyhodí z indexu. --}}
    @if ($pastEvents->isNotEmpty())
        <h2>{{ __('seo.page.past') }}</h2>
        @include('prerender._events', ['events' => $pastEvents])
        <p><a href="{{ \App\Support\PublicUrl::archive() }}">{{ __('seo.page.archive') }}</a></p>
    @endif
@endsection
