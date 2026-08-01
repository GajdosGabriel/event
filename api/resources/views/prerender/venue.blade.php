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

    <h2>Nadchádzajúce podujatia</h2>
    @include('prerender._events', ['events' => $events])
@endsection
