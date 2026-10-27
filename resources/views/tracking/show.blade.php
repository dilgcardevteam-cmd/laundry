<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Track {{ $order->order_code }} | {{ $businessSettings->get('business_name', config('app.name', 'Laundry POS')) }}</title>@vite(['resources/css/app.css'])</head>
<body class="laundry-shell min-h-screen p-4">
    <main class="mx-auto max-w-lg space-y-4 py-6">
        <a href="{{ route('tracking.home') }}" class="text-sm font-medium text-indigo-600">← Track another order</a>
        <header class="flex items-center gap-3">
            @if($businessSettings->get('business_logo'))
                <img src="{{ asset('storage/'.$businessSettings->get('business_logo')) }}" alt="{{ $businessSettings->get('business_name', 'Business logo') }}" class="h-12 w-12 rounded-full bg-white object-contain p-1 shadow">
            @endif
            <div>
                <h1 class="font-bold text-[#002c76]">{{ $businessSettings->get('business_name', config('app.name', 'Laundry POS')) }}</h1>
                @if($businessSettings->get('business_phone'))
                    <p class="text-sm text-slate-500">{{ $businessSettings->get('business_phone') }}</p>
                @endif
            </div>
        </header>
        <div class="bubble laundry-card p-6">
            <p class="relative z-10 text-sm font-semibold uppercase tracking-wider text-sky-600">Laundry tracking</p>
            <div class="mt-2 flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $order->order_code }}</h1></div><span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">{{ str_replace('_', ' ', $order->laundry_status) }}</span></div>
            <p class="mt-5 text-sm text-slate-600">Expected completion</p><p class="font-semibold">{{ optional($order->expected_completion_at)->format('M d, Y h:i A') ?? 'To be confirmed' }}</p>
        </div>
        <div class="laundry-card p-6"><h2 class="font-semibold">Progress bubbles</h2><ol class="mt-4 space-y-4">@foreach($order->statusHistory->sortBy('changed_at') as $history)<li class="flex gap-3"><span class="mt-1 h-3 w-3 shrink-0 rounded-full bg-sky-400 ring-4 ring-sky-100"></span><div><div class="font-medium capitalize">{{ str_replace('_', ' ', $history->new_status) }}</div><div class="text-sm text-slate-500">{{ $history->changed_at->format('M d, Y h:i A') }}</div></div></li>@endforeach</ol></div>
    </main>
</body>
</html>
