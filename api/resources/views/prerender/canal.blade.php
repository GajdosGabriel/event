@extends('prerender.layout')

@section('content')
    <h1>{{ $canal->name }}</h1>

    @if ($canal->has_primary_image)
        <img src="{{ $canal->primary_image['large'] }}" alt="{{ $canal->name }}">
    @endif

    @if ($bodyHtml)
        {!! $bodyHtml !!}
    @endif

    @if ($canal->website)
        <p><a href="{{ $canal->website }}" rel="nofollow">{{ $canal->website }}</a></p>
    @endif

    <h2>{{ __('seo.page.upcoming') }}</h2>
    @include('prerender._events', ['events' => $events])
@endsection
