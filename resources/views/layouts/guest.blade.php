<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[#002c76] antialiased">
        @php
            $businessName = \App\Models\BusinessSetting::where('key', 'business_name')->value('value') ?: config('app.name', 'Laundry POS');
        @endphp

        <div class="laundry-shell flex min-h-screen flex-col items-center justify-center px-4 py-8">
            <div class="mb-4 flex flex-col items-center gap-3">
                <a href="/" wire:navigate>
                    <x-application-logo class="fill-current text-[#002c76]" style="width: 100px; height: 100px;" />
                </a>
                <h1 class="max-w-full text-center text-xl font-bold text-[#002c76]">{{ $businessName }}</h1>
            </div>

            <div class="laundry-card w-full max-w-md overflow-hidden px-6 py-5">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
