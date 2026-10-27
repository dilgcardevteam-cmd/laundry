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

        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                @if($businessSettings->get('business_logo'))
                    <img src="{{ asset('storage/'.$businessSettings->get('business_logo')) }}" alt="{{ $businessSettings->get('business_name', 'Business logo') }}" class="mx-auto mb-5 h-20 w-20 rounded-full border-8 border-white bg-white object-contain p-1 shadow-xl shadow-sky-200">
                @else
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full border-8 border-white bg-sky-400 text-3xl shadow-xl shadow-sky-200">✦</div>
                @endif
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-600">{{ $businessSettings->get('business_name', 'Laundry care') }}</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-800">Where is your laundry?</h1>
                <p class="mt-3 text-slate-500">Follow every fresh, clean step from drop-off to pickup.</p>
                @if($businessSettings->get('business_address') || $businessSettings->get('business_phone'))
                    <p class="mt-3 text-sm text-slate-500">
                        @if($businessSettings->get('business_address'))
                            <span>{{ $businessSettings->get('business_address') }}</span>
                        @endif
                        @if($businessSettings->get('business_address') && $businessSettings->get('business_phone'))
                            <span class="mx-1" aria-hidden="true">·</span>
                        @endif
                        @if($businessSettings->get('business_phone'))
                            <span>{{ $businessSettings->get('business_phone') }}</span>
                        @endif
                    </p>
                @endif
            </div>

            <form method="POST" action="{{ route('tracking.lookup') }}" class="bubble laundry-card p-7">
                @csrf
                <h2 class="relative z-10 text-lg font-semibold text-slate-800">Enter your order details</h2>
                <p class="relative z-10 mt-1 text-sm text-slate-500">Enter the unique tracking code printed on your receipt.</p>
                @if($errors->any())
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif
                <label class="relative z-10 mt-6 block text-sm font-medium text-slate-600" for="order_code">Order code</label>
                <input id="order_code" name="order_code" value="{{ old('order_code', request('order_code')) }}" required autocomplete="off" placeholder="LND-20261026-0001" class="relative z-10 mt-2 w-full rounded-xl border-sky-100 bg-sky-50/60 px-4 py-3 text-slate-700 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400">
                <button type="submit" class="relative z-10 mt-6 w-full rounded-xl bg-sky-500 px-4 py-3 font-semibold text-white shadow-lg shadow-sky-200 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300">Track my laundry</button>
                <p class="relative z-10 mt-4 text-center text-xs text-slate-400">You can also scan the bubble QR code on your receipt.</p>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-slate-500 transition hover:text-sky-600">Staff login</a>
            </div>
        </section>
    </main>
</body>
</html>
