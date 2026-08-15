@extends('prerender.layout')

@section('content')
    <article>
        <h1>{{ $event->name }}</h1>

        <dl>
            @if ($event->start_at)
                <dt>{{ __('seo.page.when') }}</dt>
                <dd>
                    <time datetime="{{ $event->start_at->format('Y-m-d\TH:i:s') }}">
                        {{ $event->start_at->translatedFormat('j. F Y, H:i') }}
                    </time>
                    @if ($event->end_at)
                        –
                        <time datetime="{{ $event->end_at->format('Y-m-d\TH:i:s') }}">
                            {{ $event->end_at->translatedFormat('j. F Y, H:i') }}
                        </time>
                    @endif
                </dd>
            @endif

            @if ($event->venue)
                <dt>{{ __('seo.page.where') }}</dt>
                <dd>
                    <a href="{{ \App\Support\PublicUrl::venue($event->venue) }}">{{ $event->venue->name }}</a>
                    @if ($event->venue->street), {{ $event->venue->street }}@endif
                    @if ($event->venue->municipality), {{ $event->venue->municipality->shortname }}@endif
                </dd>
            @endif

            @if ($event->canal)
                <dt>{{ __('seo.page.organizer') }}</dt>
                <dd><a href="{{ \App\Support\PublicUrl::canal($event->canal) }}">{{ $event->canal->name }}</a></dd>
            @endif
        </dl>

        @if ($event->has_primary_image)
            <img src="{{ $event->primary_image['large'] }}" alt="{{ $event->name }}">
        @endif

        @if ($bodyHtml)
            {{-- Prešlo cez HtmlBodyCleaner v controlleri — `body` sa dnes ukladá
                 bez sanitizácie (ROADMAP 0.1), takže surové by sem nikdy ísť
                 nesmelo. --}}
            {!! $bodyHtml !!}
        @endif

        @if ($event->tags->isNotEmpty())
            <nav>
                @foreach ($event->tags as $tag)
                    <a href="{{ \App\Support\PublicUrl::tag($tag) }}">{{ $tag->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($event->municipality)
            <p>
                <a href="{{ \App\Support\PublicUrl::municipality($event->municipality) }}">
                    {{ __('seo.page.related', ['name' => $event->municipality->shortname]) }}
                </a>
            </p>
        @endif
    </article>
@endsection
