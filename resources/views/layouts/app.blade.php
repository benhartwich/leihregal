<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('branding.farben.marke') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="manifest" href="/manifest.webmanifest">

    {{-- Markenzeichen: SVG für moderne Browser, ICO als Rückfall, PNG für iOS --}}
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- Vorschau beim Teilen des Links (z. B. in Messengern) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ config('app.name') }} – {{ config('branding.untertitel') }}">
    <meta property="og:description" content="{{ config('branding.beschreibung') }}">
    <meta property="og:image" content="{{ config('app.url') }}/og-bild.png">
    <meta property="og:locale" content="de_AT">
    <meta name="twitter:card" content="summary_large_image">

    <title>{{ isset($title) ? $title . ' – ' . config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased bg-gray-50">

    <div class="min-h-full">
        <livewire:layout.navigation />

        {{-- PWA update banner --}}
        <div x-data="{ show: false }" x-show="show"
             x-init="document.addEventListener('sw-update-available', () => show = true)"
             x-cloak
             class="bg-blue-600 text-white text-sm px-4 py-2.5 flex items-center justify-between gap-4">
            <span>Eine neue Version von {{ config('app.name') }} ist verfügbar.</span>
            <button @click="window.location.reload()" class="underline font-medium hover:no-underline">
                Jetzt aktualisieren
            </button>
        </div>

        {{-- Flash messages (populated after redirect) --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="max-w-4xl mx-auto mt-4 px-4 sm:px-6">
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="max-w-4xl mx-auto mt-4 px-4 sm:px-6">
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <main class="max-w-4xl mx-auto px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').then(reg => {
                reg.addEventListener('updatefound', () => {
                    const newSw = reg.installing;
                    newSw.addEventListener('statechange', () => {
                        if (newSw.state === 'installed' && navigator.serviceWorker.controller) {
                            // New version waiting — show update banner
                            document.dispatchEvent(new CustomEvent('sw-update-available'));
                        }
                    });
                });
            }).catch(() => {});
        }
    </script>
</body>
</html>
