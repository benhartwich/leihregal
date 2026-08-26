<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('branding.farben.marke') }}">
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

    <title>Anmelden – {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen flex flex-col items-center justify-center p-4">

    {{-- Logo / Brand --}}
    <div class="mb-8 text-center">
        <x-brand-logo groesse="gross" class="justify-center" />
        <p class="text-sm text-gray-500 mt-2">{{ config('branding.untertitel') }}</p>
    </div>

    <div class="w-full max-w-sm bg-white rounded-2xl shadow-xs border border-gray-200 p-6">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
