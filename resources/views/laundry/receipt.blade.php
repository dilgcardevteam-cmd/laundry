@php
    $token = $token ?? session('tracking_token');
    if (! $token && preg_match('/^LND-(?:[A-Z0-9]{4}-){3}[A-Z0-9]{4}$/', $order->order_code) === 1) {
        $orderToken = str_replace('-', '', substr($order->order_code, 4));
        if (hash_equals($order->tracking_token_hash, hash('sha256', $orderToken))) {
            $token = $orderToken;
        }
    }
    $trackingCode = preg_match('/^LND-(?:[A-Z0-9]{4}-){3}[A-Z0-9]{4}$/', $order->order_code) === 1
        ? $order->order_code
        : $token;
    $trackingUrl = $token ? route('tracking.show', $token) : route('tracking.home');
    $businessSettings = \App\Models\BusinessSetting::whereIn('key', ['business_name', 'business_logo', 'business_phone', 'business_address'])->pluck('value', 'key');
    $cashReceived = $order->payments->sum(fn ($payment) => (float) ($payment->cash_tendered ?? 0));
    $cashPaid = $order->payments->where('method', 'cash')->sum(fn ($payment) => (float) $payment->amount);
    $change = max(0, $cashReceived - $cashPaid);
    $qrCodeSvg = null;
    try {
        $qrOptions = new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::L,
        ]);
        $qrCodeSvg = (new \chillerlan\QRCode\QRCode($qrOptions))->render($trackingUrl);
    } catch (\Throwable) {
        // Silently fallback if QR generation encounters an issue
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-[#002c76]">Order {{ $order->order_code }}</h2>
            <p class="mt-1 flex items-center gap-2 text-sm">
                <span>Payment status:</span>
                <span class="order-payment-status order-payment-status--{{ str_replace('_', '-', $order->payment_status) }}">
                    {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                </span>
            </p>
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-6xl gap-6 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
        <div class="receipt rounded bg-white p-6 shadow dark:bg-gray-800">
            <div class="text-center">
                @if($businessSettings->get('business_logo'))
                    <img src="{{ asset('storage/'.$businessSettings->get('business_logo')) }}" alt="Business logo" width="100" height="100" class="mx-auto mb-3 object-contain" style="width: 100px; height: 100px;">
                @endif
                <h1 class="text-xl font-bold">{{ $businessSettings->get('business_name', 'Laundry POS') }}</h1>
                @if($businessSettings->get('business_address'))
                    <p class="mt-1 text-sm">{{ $businessSettings->get('business_address') }}</p>
                @endif
                @if($businessSettings->get('business_phone'))
                    <p class="mt-1 text-sm">{{ $businessSettings->get('business_phone') }}</p>
                @endif
            </div>

            <div class="mt-4 text-left">
                <p class="text-sm"><span class="font-semibold">Customer name:</span> {{ $order->customer?->name ?? 'Walk-in customer' }}</p>
                @if($order->customer?->contact_number)
                    <p class="mt-1 text-sm"><span class="font-semibold">Mobile number:</span> {{ $order->customer->contact_number }}</p>
                @endif
                <p class="mt-1 text-sm">{{ $order->received_at?->format('M d, Y h:i A') }}</p>
                <p class="text-sm">{{ $availableStatuses[$order->laundry_status] ?? ($order->laundry_status === 'ironing' ? 'Drying' : ($order->laundry_status === 'released' ? 'Ready for Pick up' : ucfirst(str_replace('_', ' ', $order->laundry_status)))) }}</p>
            </div>

            <hr class="my-4">

            @foreach($order->items as $item)
                @php
                    $quantity = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.');
                    $unit = match ($item->pricing_method) {
                        'per_piece' => 'PC',
                        'per_kg' => 'kg',
                        'per_load' => 'load',
                        'fixed' => '',
                        default => $item->pricing_method,
                    };
                @endphp
                <div class="mb-2">
                    <div class="flex justify-between gap-3">
                        <span>{{ $item->service_name }}</span>
                        <span class="shrink-0">₱{{ number_format($item->line_total, 2) }}</span>
                    </div>
                    <p class="pl-1 text-xs text-slate-500">x {{ $quantity }}{{ $unit ? ' '.$unit : '' }}</p>
                </div>
            @endforeach
            @foreach($order->addOns as $addOn)
                <div class="mb-2">
                    <div class="flex justify-between gap-3">
                        <span>{{ $addOn->name }}</span>
                        <span class="shrink-0">₱{{ number_format($addOn->line_total, 2) }}</span>
                    </div>
                    <p class="pl-1 text-xs text-slate-500">x {{ $addOn->quantity }} @ ₱{{ number_format($addOn->price, 2) }}</p>
                </div>
            @endforeach

            <div class="mt-4 space-y-1 text-right">
                <p>Subtotal: ₱{{ number_format($order->subtotal, 2) }}</p>
                @if($order->addOns->isNotEmpty())
                    <p>Add-ons: ₱{{ number_format($order->addOns->sum(fn ($addOn) => (float) $addOn->line_total), 2) }}</p>
                @endif
                <p class="font-bold">Total: ₱{{ number_format($order->total_amount, 2) }}</p>
                <p>Paid: ₱{{ number_format($order->total_paid, 2) }}</p>
                <p>Balance: ₱{{ number_format($order->outstanding_balance, 2) }}</p>
                @if($cashReceived > 0)
                    <p>Cash received: ₱{{ number_format($cashReceived, 2) }}</p>
                    <p>Change: ₱{{ number_format($change, 2) }}</p>
                @endif
            </div>

            <div class="mt-6 border-t pt-4 text-center">
                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Scan to track your laundry</p>
                @if($qrCodeSvg)
                    <div class="mx-auto flex justify-center">
                        <img src="{{ $qrCodeSvg }}" alt="Tracking QR Code" class="h-32 w-32">
                    </div>
                @endif
                @if($token)
                    <p class="mt-2 text-xs text-sky-700 dark:text-sky-400">Tracking code: <span class="font-bold tracking-wider">{{ $trackingCode }}</span></p>
                @endif
            </div>
        </div>

        <div class="customer-copy hidden text-center">
            <h1 class="text-3xl font-bold">{{ $order->customer?->name ?? 'Walk-in customer' }}</h1>
            <p class="mt-3 text-sm">Received: {{ $order->received_at?->format('M d, Y h:i A') ?? 'To be confirmed' }}</p>
            <p class="mt-1 text-sm">Payment: <span class="font-bold">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</span></p>
            @if($qrCodeSvg)
                <div class="mx-auto mt-4 flex justify-center">
                    <img src="{{ $qrCodeSvg }}" alt="Tracking QR Code" class="h-40 w-40">
                </div>
            @endif
            @if($trackingCode)
                <p class="mt-3 text-lg font-bold tracking-wider">{{ $trackingCode }}</p>
            @else
                <p class="mt-3 text-sm">Please ask staff for your tracking code.</p>
            @endif
        </div>

        <aside class="receipt-actions space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-[#002c76]">Order actions</h2>
            <button type="button" onclick="window.print()" class="receipt-action-button w-full">Print receipt</button>
            <button type="button" onclick="printLaundryTag()" class="receipt-action-button receipt-action-button--secondary w-full">Print laundry tag</button>

            @if($order->payment_status !== 'paid')
                <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="space-y-3 border-t border-slate-100 pt-4">
                    @csrf
                    <h3 class="font-semibold text-slate-800">Record a payment</h3>
                    <label class="block text-sm font-medium text-slate-600" for="payment-amount">Amount</label>
                    <input id="payment-amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $order->outstanding_balance }}" placeholder="0.00" class="w-full rounded-xl border-slate-200">
                    <label class="block text-sm font-medium text-slate-600" for="payment-method">Payment method</label>
                    <select id="payment-method" name="method" class="w-full rounded-xl border-slate-200">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="other">Other</option>
                    </select>
                    <button class="receipt-action-button receipt-action-button--success w-full">Add payment</button>
                </form>
            @endif

            @if(auth()->user()?->hasRole('administrator', 'manager', 'laundry_staff'))
                <form method="POST" action="{{ route('orders.status', $order) }}" class="space-y-3 border-t border-slate-100 pt-4">
                    @csrf
                    @method('PATCH')
                    <h3 class="font-semibold text-slate-800">Update laundry status</h3>
                    <label class="block text-sm font-medium text-slate-600" for="laundry-status">Status</label>
                    <select id="laundry-status" name="laundry_status" class="w-full rounded-xl border-slate-200">
                        @foreach($availableStatuses as $status => $label)
                            <option value="{{ $status }}" @selected($order->laundry_status === $status || ($order->laundry_status === 'ironing' && $status === 'drying') || ($order->laundry_status === 'released' && $status === 'ready_for_pickup') || ($order->laundry_status === 'cancelled' && $status === 'received'))>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('laundry_status')
                        <span class="text-sm font-medium text-red-700">{{ $message }}</span>
                    @enderror
                    <button class="receipt-action-button w-full">Update status</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>

<style>
@media print {
    nav, header, button, form { display:none !important }
    .receipt-actions { display:none !important }
    body { background:#fff }
    .receipt { box-shadow:none; width:80mm; padding:4mm }
    .customer-copy { display:none !important }
    body.printing-laundry-tag .receipt { display:none !important }
    body.printing-laundry-tag .customer-copy {
        display:block !important;
        width:80mm;
        margin:0 auto;
        padding:6mm 4mm;
    }
}
</style>

<script>
    @if(session('clear_pos_draft'))
        localStorage.removeItem('laundry-pos-order-draft');
        sessionStorage.removeItem('laundry-pos-order-draft');
    @endif

    function printLaundryTag() {
        document.body.classList.add('printing-laundry-tag');
        window.print();
    }

    window.addEventListener('afterprint', () => {
        document.body.classList.remove('printing-laundry-tag');
    });

    @if(request()->boolean('print'))
        window.addEventListener('load', () => window.print());
    @endif
</script>
