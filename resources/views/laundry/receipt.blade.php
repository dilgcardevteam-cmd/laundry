@php
    $token = $token ?? session('tracking_token');
    $trackingUrl = $token ? route('tracking.show', $token) : route('tracking.home', ['order_code' => $order->order_code]);
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
        <h2 class="font-semibold text-xl text-[#002c76]">Order {{ $order->order_code }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl p-6">
        <div class="receipt rounded bg-white p-6 shadow dark:bg-gray-800">
            <div class="text-center">
                @if($businessSettings->get('business_logo'))
                    <img src="{{ asset('storage/'.$businessSettings->get('business_logo')) }}" alt="Business logo" class="mx-auto mb-3 h-16 max-w-40 object-contain">
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
                <p class="text-sm">{{ str_replace('_', ' ', $order->laundry_status) }}</p>
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
                <div class="flex justify-between">
                    <span>{{ $item->service_name }} x {{ $quantity }}{{ $unit ? ' '.$unit : '' }}</span>
                    <span>₱{{ number_format($item->line_total, 2) }}</span>
                </div>
            @endforeach

            <div class="mt-4 space-y-1 text-right">
                <p>Subtotal: ₱{{ number_format($order->subtotal, 2) }}</p>
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
                <p class="mt-2 text-xs text-sky-700 dark:text-sky-400">Tracking code: <span class="font-bold tracking-wider">{{ $order->order_code }}</span></p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <button onclick="window.print()" class="rounded bg-indigo-600 px-4 py-2 text-white">Print receipt</button>

            <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="flex gap-2">
                @csrf
                <input name="amount" type="number" step="0.01" min="0.01" max="{{ $order->outstanding_balance }}" placeholder="Payment" class="w-28 rounded border-gray-300">
                <select name="method" class="rounded border-gray-300">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="other">Other</option>
                </select>
                <button class="rounded bg-green-700 px-4 py-2 text-white">Add payment</button>
            </form>

            @if(auth()->user()?->hasRole('administrator', 'manager', 'laundry_staff'))
                <form method="POST" action="{{ route('orders.status', $order) }}" class="flex gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="laundry_status" class="rounded border-gray-300">
                        @foreach($availableStatuses as $status)
                            <option value="{{ $status }}" @selected($order->laundry_status === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                    @error('laundry_status')
                        <span class="text-sm font-medium text-red-700">{{ $message }}</span>
                    @enderror
                    <button class="rounded bg-gray-800 px-4 py-2 text-white">Update</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>

<style>
@media print {
    nav, header, button, form { display:none !important }
    body { background:#fff }
    .receipt { box-shadow:none; width:80mm; padding:4mm }
}
</style>

@if(request()->boolean('print'))
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
