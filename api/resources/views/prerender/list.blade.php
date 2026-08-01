@extends('prerender.layout')

@section('content')
    <h1>{{ $heading }}</h1>

    @if ($meta['description'])
        <p>{{ $meta['description'] }}</p>
    @endif

    @include('prerender._events', ['events' => $events])
@endsection
