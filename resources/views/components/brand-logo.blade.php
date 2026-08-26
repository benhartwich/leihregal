@props([
    /** 'klein' für die Navigation, 'gross' für Anmeldeseite und leere Zustände */
    'groesse' => 'klein',
    /** Wortmarke ausblenden und nur die Bildmarke zeigen */
    'nurMarke' => false,
])

@php
    $masse = match ($groesse) {
        'gross' => ['mark' => 'w-11 h-11', 'text' => 'text-3xl', 'lueckeX' => 'gap-3'],
        default => ['mark' => 'w-8 h-8',   'text' => 'text-xl',  'lueckeX' => 'gap-2'],
    };

    $name    = config('app.name');
    $hell    = config('branding.farben.marke_hell');
    $dunkel  = config('branding.farben.marke_dunkel');
@endphp

{{--
    Logo der Anwendung: Bildmarke plus Wortmarke.

    Die Bildmarke steht inline im Markup statt als <img>. Damit erscheint sie
    ohne zusätzliche Anfrage und skaliert verlustfrei. Name und Verlaufsfarben
    kommen aus der Konfiguration – eine Einrichtung, die die Anwendung unter
    eigenem Namen betreibt, ändert dafür nur APP_NAME und BRAND_FARBE_*.

    Die Quelldatei liegt zusätzlich unter public/brand/leihregal-mark.svg –
    dort ist sie die Vorlage für die erzeugten Icons (siehe docs/marke.md).
--}}
<span {{ $attributes->merge(['class' => "inline-flex items-center {$masse['lueckeX']}"]) }}>
    <svg class="{{ $masse['mark'] }} shrink-0" viewBox="0 0 512 512"
         role="img" aria-label="{{ $nurMarke ? $name : '' }}"
         @if(! $nurMarke) aria-hidden="true" @endif>
        <defs>
            <linearGradient id="markenVerlauf" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="{{ $hell }}"/>
                <stop offset="1" stop-color="{{ $dunkel }}"/>
            </linearGradient>
        </defs>
        <rect width="512" height="512" rx="116" fill="url(#markenVerlauf)"/>
        <rect x="112" y="372" width="288" height="26" rx="13" fill="#FFFFFF" opacity="0.55"/>
        <rect x="132" y="168" width="58" height="196" rx="16" fill="#FFFFFF" opacity="0.92"/>
        <rect x="202" y="134" width="58" height="230" rx="16" fill="#FFFFFF"/>
        <rect x="272" y="184" width="58" height="180" rx="16" fill="#FFFFFF" opacity="0.82"/>
        <rect x="342" y="150" width="58" height="214" rx="16" fill="#FFFFFF"
              transform="rotate(13 371 364)"/>
    </svg>

    @unless($nurMarke)
        <span class="{{ $masse['text'] }} font-bold tracking-tight text-gray-900">{{ $name }}</span>
    @endunless
</span>
