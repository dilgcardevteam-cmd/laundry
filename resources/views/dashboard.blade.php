<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#002c76]">Overview</p>
            <h2 class="mt-1 text-xl font-bold text-[#002c76] leading-tight">
            {{ __('Dashboard') }}
            </h2>
        </div>
    </x-slot>

    <div class="tablet-safe mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        <div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                @foreach([['Today sales', '₱'.number_format($todaySales, 2)], ['Orders today', $todayOrders], ['Processing', $processingOrders], ['Ready for pickup', $readyOrders], ['Outstanding', '₱'.number_format($outstanding, 2)], ['Overdue', $overdueOrders], ['Unclaimed', $unclaimedOrders]] as [$label, $value])
                    <div class="laundry-card p-5">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#002c76]/70">{{ $label }}</p>
                            <span class="h-2.5 w-2.5 rounded-full bg-[#002c76]" aria-hidden="true"></span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-[#002c76]">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 flex flex-wrap gap-3"><a href="{{ route('pos') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#002c76] px-4 py-2 font-semibold text-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>New order</a><a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-[#002c76] bg-white px-4 py-2 font-semibold text-[#002c76]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg>View orders</a></div>
            <div class="mt-6 laundry-card p-6">
                <h3 class="font-bold text-[#002c76]">Collected sales — last 7 days</h3>
                <div class="mt-5 grid h-48 grid-cols-7 items-end gap-3">
                    @php($maxSale = max(1, max($salesTrend->all())))
                    @foreach($salesTrend as $label => $sale)
                        <div class="flex h-full flex-col items-center justify-end gap-2"><div class="w-full rounded-t bg-[#002c76]" style="height: {{ max(4, ($sale / $maxSale) * 100) }}%"></div><span class="text-xs text-[#002c76]/70">{{ $label }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
