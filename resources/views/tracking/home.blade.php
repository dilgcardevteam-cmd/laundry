<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track your laundry | {{ $businessSettings->get('business_name', config('app.name', 'Laundry POS')) }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="laundry-shell min-h-screen text-slate-700">
    <main class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
        <div class="absolute left-8 top-10 -z-10 h-20 w-20 rounded-full border-8 border-white/80 bg-sky-100/70 shadow-lg"></div>
        <div class="absolute right-8 top-24 -z-10 h-12 w-12 rounded-full bg-pink-200/70 shadow-lg"></div>
        <div class="absolute bottom-10 left-1/4 -z-10 h-28 w-28 rounded-full border-8 border-white/80 bg-cyan-100/70 shadow-lg"></div>

        <section class="w-full max-w-5xl">
            <div class="mb-8 text-center">
                @if($businessSettings->get('business_logo'))
                    <img src="{{ asset('storage/'.$businessSettings->get('business_logo')) }}" alt="{{ $businessSettings->get('business_name', 'Business logo') }}" class="mx-auto mb-5 h-56 w-56 rounded-full border-8 border-white bg-white object-contain p-1 shadow-xl shadow-sky-200">
                @else
                    <div class="mx-auto mb-5 flex h-56 w-56 items-center justify-center rounded-full border-8 border-white bg-sky-400 text-3xl shadow-xl shadow-sky-200">✦</div>
                @endif
                <p class="business-name font-bold uppercase leading-tight text-sky-600">{{ $businessSettings->get('business_name', 'Laundry care') }}</p>
                @if($businessSettings->get('business_address') || $businessSettings->get('business_phone'))
                    <div class="mt-3 space-y-1 text-sm text-slate-500">
                        @if($businessSettings->get('business_address'))
                            <p>{{ $businessSettings->get('business_address') }}</p>
                        @endif
                        @if($businessSettings->get('business_phone'))
                            <p>{{ $businessSettings->get('business_phone') }}</p>
                        @endif
                    </div>
                @endif
                <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-800">Check the status of your laundry</h1>
            </div>

            <form method="POST" action="{{ route('tracking.lookup') }}" class="bubble laundry-card p-7">
                @csrf
                <h2 class="relative z-10 text-lg font-semibold text-slate-800">Enter your tracking code</h2>
                <p class="relative z-10 mt-1 text-sm text-slate-500">Enter the random tracking code printed on your receipt.</p>
                @if($errors->any())
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif
                <label class="relative z-10 mt-6 block text-sm font-medium text-slate-600" for="tracking_code">Tracking code</label>
                <input id="tracking_code" name="tracking_code" value="{{ old('tracking_code') }}" required autocomplete="off" autocapitalize="characters" placeholder="AB12CD34EF56GH78" class="relative z-10 mt-2 w-full rounded-xl border-sky-100 bg-sky-50/60 px-4 py-3 text-slate-700 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400">
                <button type="submit" class="tracking-submit relative z-10 mt-6 w-full rounded-xl px-4 py-3 font-semibold shadow-lg transition focus:outline-none focus:ring-2 focus:ring-offset-2">Track my laundry</button>
                <p class="relative z-10 mt-4 text-center text-xs text-slate-400">You can also scan the bubble QR code on your receipt.</p>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-slate-500 transition hover:text-sky-600">Staff login</a>
            </div>
        </section>
    </main>
</body>
</html>
