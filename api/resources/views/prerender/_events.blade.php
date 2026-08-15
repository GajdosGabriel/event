{{-- Zoznam podujatí ako odkazy na kanonické URL — toto je cesta, ktorou sa
     crawler dostane z landing stránky na detaily. --}}
@if ($events->isEmpty())
    <p>{{ __('seo.page.empty') }}</p>
@else
    <ul>
        @foreach ($events as $event)
            <li>
                <a href="{{ \App\Support\PublicUrl::event($event) }}">{{ $event->name }}</a>
                @if ($event->start_at)
                    <time datetime="{{ $event->start_at->format('Y-m-d\TH:i:s') }}">
                        {{ $event->start_at->translatedFormat('j. F Y, H:i') }}
                    </time>
                @endif
                @if ($event->venue)
                    <span>{{ $event->venue->name }}@if ($event->venue->municipality), {{ $event->venue->municipality->shortname }}@endif</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
