<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Track {{ $order->order_code }} | {{ $businessSettings->get('business_name', config('app.name', 'Laundry POS')) }}</title>@vite(['resources/css/app.css'])</head>
<body class="laundry-shell min-h-screen p-4">
    @php
        $serviceStatusLabels = [
            'received' => 'On Queue',
            'washing' => 'Washing',
            'drying' => 'Drying',
            'ironing' => 'Drying',
            'folding' => 'Folding',
            'ready_for_pickup' => 'Ready for Pick up',
            'released' => 'Ready for Pick up',
        ];
        $serviceStatusLabel = $serviceStatusLabels[$order->laundry_status] ?? ucfirst(str_replace('_', ' ', $order->laundry_status));
    @endphp
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
            <div class="mt-2 flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $order->order_code }}</h1></div><span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">{{ $serviceStatusLabel }}</span></div>
            <p class="mt-5 text-sm text-slate-600">Expected completion</p><p class="font-semibold">{{ optional($order->expected_completion_at)->format('M d, Y h:i A') ?? 'To be confirmed' }}</p>
        </div>
        <div class="laundry-card p-6">
            <h2 class="font-semibold">Laundry details</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach($order->items as $item)
                    @php
                        $quantity = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.');
                        $unit = match ($item->pricing_method) {
                            'per_piece' => 'piece',
                            'per_kg' => 'kg',
                            'per_load' => 'load',
                            'fixed' => '',
                            default => $item->pricing_method,
                        };
                    @endphp
                    <div class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div>
                            <p class="font-medium">{{ $item->service_name }}</p>
                            <p class="text-sm text-slate-500">{{ $quantity }}{{ $unit ? ' '.$unit : '' }}</p>
                        </div>
                        <p class="shrink-0 font-medium">₱{{ number_format($item->line_total, 2) }}</p>
                    </div>
                @endforeach
                @foreach($order->addOns as $addOn)
                    <div class="flex items-start justify-between gap-4 py-3 last:pb-0">
                        <div>
                            <p class="font-medium">{{ $addOn->name }}</p>
                            <p class="text-sm text-slate-500">{{ $addOn->quantity }} × ₱{{ number_format($addOn->price, 2) }} · Optional add-on</p>
                        </div>
                        <p class="shrink-0 font-medium">₱{{ number_format($addOn->line_total, 2) }}</p>
                    </div>
                @endforeach
            </div>
            <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                @if($order->addOns->isNotEmpty())
                    <div class="flex justify-between gap-4"><dt>Add-ons</dt><dd>₱{{ number_format($order->addOns->sum(fn ($addOn) => (float) $addOn->line_total), 2) }}</dd></div>
                @endif
                <div class="flex justify-between gap-4"><dt>Total amount</dt><dd class="font-semibold">₱{{ number_format($order->total_amount, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Paid</dt><dd>₱{{ number_format($order->total_paid, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Balance due</dt><dd>₱{{ number_format($order->outstanding_balance, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Payment status</dt><dd class="font-semibold">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</dd></div>
            </dl>
        </div>
        <div class="laundry-card p-6"><h2 class="font-semibold">Progress bubbles</h2><ol class="mt-4 space-y-4">@foreach($order->statusHistory->sortBy('changed_at') as $history)<li class="flex gap-3"><span class="mt-1 h-3 w-3 shrink-0 rounded-full bg-sky-400 ring-4 ring-sky-100"></span><div><div class="font-medium capitalize">{{ $serviceStatusLabels[$history->new_status] ?? ucfirst(str_replace('_', ' ', $history->new_status)) }}</div><div class="text-sm text-slate-500">{{ $history->changed_at->format('M d, Y h:i A') }}</div></div></li>@endforeach</ol></div>
    </main>
</body>
</html>
