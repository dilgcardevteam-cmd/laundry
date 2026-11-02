<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Sales report</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 p-6">
        <form method="POST" action="{{ route('expenses.store') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <input name="category" required placeholder="Expense category" class="rounded-lg border-gray-300">
            <input name="description" required placeholder="Description" class="rounded-lg border-gray-300">
            <input name="amount" required type="number" step="0.01" min="0.01" placeholder="Amount" class="rounded-lg border-gray-300">
            <input name="expense_date" required type="date" value="{{ now()->format('Y-m-d') }}" class="rounded-lg border-gray-300">
            <button class="rounded-lg bg-red-700 px-4 py-2 font-semibold text-white">Record expense</button>
        </form>

        <form method="GET" action="{{ route('reports') }}" class="flex flex-wrap items-end gap-3">
            <label class="grid gap-1 text-sm font-medium text-slate-600">
                From
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border-gray-300">
            </label>
            <label class="grid gap-1 text-sm font-medium text-slate-600">
                To
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border-gray-300">
            </label>
            <label class="grid gap-1 text-sm font-medium text-slate-600">
                Payment status
                <select name="payment_status" class="rounded-lg border-gray-300">
                    <option value="" @selected($paymentStatus === null)>All statuses</option>
                    <option value="paid" @selected($paymentStatus === 'paid')>Paid</option>
                    <option value="unpaid" @selected($paymentStatus === 'unpaid')>Unpaid</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-medium text-slate-600">
                Service
                <select name="service_id" class="rounded-lg border-gray-300">
                    <option value="">All services</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($serviceId === $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm font-medium text-slate-600">
                Add-on
                <select name="add_on_id" class="rounded-lg border-gray-300">
                    <option value="">All add-ons</option>
                    @foreach($addOns as $addOn)
                        <option value="{{ $addOn->id }}" @selected($addOnId === $addOn->id)>{{ $addOn->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-lg bg-[#002c76] px-5 py-2.5 font-semibold text-white">Filter</button>
            <a href="{{ route('reports.export', request()->query()) }}" class="rounded-lg bg-green-700 px-5 py-2.5 text-center font-semibold text-white">Export report CSV</a>
        </form>

        <section aria-label="Report analysis">
            <h3 class="mb-3 text-lg font-semibold text-[#002c76]">Report analysis</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Orders</p><svg class="h-5 w-5 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">{{ $summary['orders'] }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Paid orders</p><svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">{{ $summary['paid_orders'] }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Unpaid orders</p><svg class="h-5 w-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">{{ $summary['unpaid_orders'] }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Order value</p><svg class="h-5 w-5 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16v13H4zM8 7V5a4 4 0 0 1 8 0v2M8 12h8"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">₱{{ number_format($summary['order_value'], 2) }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Collected</p><svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m5-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">₱{{ number_format($summary['collected'], 2) }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Outstanding</p><svg class="h-5 w-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 2.8 20h18.4L12 3Z"/><path stroke-linecap="round" d="M12 9v4m0 3h.01"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">₱{{ number_format($summary['outstanding'], 2) }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Expenses</p><svg class="h-5 w-5 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16v13H4zM8 7V5h8v2M9 12h6"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">₱{{ number_format($summary['expenses'], 2) }}</p></div>
                <div class="rounded-2xl bg-white p-5 shadow"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">Net collected after expenses</p><svg class="h-5 w-5 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18V6m0 12h16M7 14l4-4 3 3 5-6"/></svg></div><p class="mt-1 text-2xl font-bold text-[#002c76]">₱{{ number_format($summary['net_collected'], 2) }}</p></div>
            </div>
        </section>

        <section aria-label="Service report">
            <h3 class="mb-3 text-lg font-semibold text-[#002c76]">Per-service report</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($serviceBreakdown as $serviceSummary)
                    <article class="rounded-2xl bg-white p-5 shadow">
                        @php($serviceIcon = str_contains(strtolower($serviceSummary['name']), 'shoe') ? 'shoes' : 'laundry')
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="font-semibold text-[#002c76]">{{ $serviceSummary['name'] }}</h4>
                            @if($serviceIcon === 'shoes')
                                <svg data-report-icon="shoes" class="h-5 w-5 shrink-0 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5c2.4-.2 4.2-1.1 5.4-3l1.2-2.1 2.1 1.5c1.4 1 3.2 1.6 5 1.7l2.4.2a2.4 2.4 0 0 1 2.2 2.4V19H3v-2.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m8.4 13.5 2.1 1.2m1.7-.4 1.5 1m-8.2 1.2h.01"/></svg>
                            @else
                                <svg data-report-icon="laundry" class="h-5 w-5 shrink-0 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M8 5.5h.01M11 5.5h.01"/><circle cx="12" cy="14" r="4"/><path stroke-linecap="round" d="M12 10a4 4 0 0 1 4 4"/></svg>
                            @endif
                        </div>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-slate-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>Orders</dt><dd class="font-semibold">{{ $serviceSummary['orders'] }}</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-slate-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h8M8 17h4"/></svg>{{ ucfirst($serviceSummary['unit']) }}</dt><dd class="font-semibold">{{ number_format($serviceSummary['quantity'], $serviceSummary['unit'] === 'kg' ? 3 : 0) }}</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-slate-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m5-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6"/></svg>Sales value</dt><dd class="font-semibold text-[#002c76]">₱{{ number_format($serviceSummary['amount'], 2) }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <p class="rounded-2xl bg-white p-5 text-sm text-slate-500 shadow">No service sales found for these filters.</p>
                @endforelse
            </div>
        </section>

        <section aria-label="Add-on report">
            <h3 class="mb-3 text-lg font-semibold text-[#002c76]">Per add-on report</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($addOnBreakdown as $addOnSummary)
                    <article class="rounded-2xl bg-white p-5 shadow">
                        @php($addOnLabel = strtolower($addOnSummary['name']))
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="font-semibold text-[#002c76]">{{ $addOnSummary['name'] }}</h4>
                            @if(str_contains($addOnLabel, 'dry'))
                                <svg data-report-icon="extra-dry" class="h-5 w-5 shrink-0 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 17a8 8 0 0 1 16 0M12 9V5m-3 6-2-2m8 2 2-2M3 20h18"/><path stroke-linecap="round" d="M12 2v1m-7 3 1 1m12-1-1 1"/></svg>
                            @elseif(str_contains($addOnLabel, 'conditioner') || str_contains($addOnLabel, 'fabcon'))
                                <svg data-report-icon="fabric-conditioner" class="h-5 w-5 shrink-0 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v3l2 2v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V8l2-2V3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 6h6M7 10h10"/><path stroke-linecap="round" stroke-linejoin="round" d="m10 15 1.3 1.3L14.5 13m4.5-8h2m-1-1v2"/></svg>
                            @else
                                <svg data-report-icon="detergent" class="h-5 w-5 shrink-0 text-[#002c76]/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v3l2 2v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V8l2-2V3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 6h6M7 10h10M10 14h4M10 17h4"/></svg>
                            @endif
                        </div>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-slate-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>Orders</dt><dd class="font-semibold">{{ $addOnSummary['orders'] }}</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-slate-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h8M8 17h4"/></svg>Quantity</dt><dd class="font-semibold">{{ number_format($addOnSummary['quantity']) }}</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-slate-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m5-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6"/></svg>Sales value</dt><dd class="font-semibold text-[#002c76]">₱{{ number_format($addOnSummary['amount'], 2) }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <p class="rounded-2xl bg-white p-5 text-sm text-slate-500 shadow">No add-on sales found for these filters.</p>
                @endforelse
            </div>
        </section>

        <div class="overflow-x-auto rounded-2xl bg-white shadow">
            <table class="w-full min-w-[900px] text-left">
                <thead class="text-sm text-[#002c76]">
                    <tr class="border-b">
                        <th class="p-3 text-center">Order</th>
                        <th class="p-3 text-center">Date</th>
                        <th class="p-3 text-center">Value</th>
                        <th class="p-3 text-center">Collected</th>
                        <th class="p-3 text-center">Balance</th>
                        <th class="p-3 text-center">Payment status</th>
                        <th class="p-3 text-center">Staff assisted / booked by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-b last:border-0">
                            <td class="p-3 text-center font-medium text-[#002c76]">{{ $order->order_code }}</td>
                            <td class="p-3 text-center">{{ $order->received_at->format('Y-m-d') }}</td>
                            <td class="p-3 text-center">₱{{ number_format($order->total_amount, 2) }}</td>
                            <td class="p-3 text-center">₱{{ number_format($order->total_paid, 2) }}</td>
                            <td class="p-3 text-center">₱{{ number_format($order->outstanding_balance, 2) }}</td>
                            <td class="p-3 text-center capitalize">{{ $order->payment_status }}</td>
                            <td class="p-3 text-center">{{ $order->cashier?->name ?? 'Unassigned' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-slate-500">No orders found for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
