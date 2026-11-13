@extends('prerender.layout')

{{-- Stránka „Pre organizátorov" pre crawlerov — skrátený obsah SPA
     (ui/src/content/organizers), aby ju vyhľadávač našiel na dopyty typu
     „zverejniť akciu zadarmo". --}}
@section('content')
    <h1>{{ $heading }}</h1>

    <p>{{ $meta['description'] }}</p>

    <ul>
        @foreach ($features as $feature)
            <li>{{ $feature }}</li>
        @endforeach
    </ul>

    <p><a href="{{ \App\Support\PublicUrl::absolute('/nahrat-plagat') }}">{{ $cta }}</a></p>
@endsection
