@extends('prerender.layout')

@section('content')
    <article>
        <h1>{{ $event->name }}</h1>

        {{-- Adresa skončeného podujatia ostáva funkčná navždy, ale musí to
             o sebe povedať hneď v prvom odseku — návštevník z vyhľadávača inak
             číta pozvánku na akciu, ktorá už bola. Rovnaké oznámenie ukazuje
             SPA (EventPublicShowPage), aby crawler videl tú istú stránku. --}}
        @if ($hasEnded)
            <p><strong>{{ __('seo.page.ended') }}</strong></p>
        @endif

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

        {{-- Zodpovedané otázky publika. Pre crawlera je to jediná cesta, ako sa
             k nim dostane — v SPA ich dopĺňa JS až v prehliadači. Sú aj obsahom
             `FAQPage` v JSON-LD (viď JsonLd::faqPage). --}}
        @if ($faq->isNotEmpty())
            <section>
                <h2>{{ __('seo.page.faq') }}</h2>
                <dl>
                    @foreach ($faq as $question)
                        <dt>{{ $question->body }}</dt>
                        <dd>{{ $question->answer_body }}</dd>
                    @endforeach
                </dl>
            </section>
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

        {{-- Zo skončeného podujatia musí viesť cesta ďalej — pre človeka, ktorý
             sem prišiel z vyhľadávača neskoro, aj pre crawlera, ktorému by inak
             stránka bola slepou uličkou. --}}
        @if ($hasEnded && $upcomingElsewhere->isNotEmpty())
            <section>
                <h2>{{ __('seo.page.upcoming') }}</h2>
                @include('prerender._events', ['events' => $upcomingElsewhere])
            </section>
        @endif
    </article>
@endsection
