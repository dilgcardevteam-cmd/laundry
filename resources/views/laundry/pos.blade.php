@php
    $customers = $customers ?? \App\Models\Customer::orderBy('name')->get(['id', 'code', 'name', 'contact_number']);
    $serviceColors = [
        ['accent' => '#0284c7', 'tint' => '#f0f9ff'],
        ['accent' => '#7c3aed', 'tint' => '#f5f3ff'],
        ['accent' => '#059669', 'tint' => '#ecfdf5'],
        ['accent' => '#d97706', 'tint' => '#fffbeb'],
        ['accent' => '#e11d48', 'tint' => '#fff1f2'],
        ['accent' => '#4f46e5', 'tint' => '#eef2ff'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#002c76]">Point of sale</p>
                <h2 class="mt-1 text-xl font-bold text-slate-800">Create laundry order</h2>
            </div>
            <span class="hidden rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-[#002c76] sm:inline-flex">New transaction</span>
        </div>
    </x-slot>

    <div class="tablet-safe mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-green-700">{{ session('success') }}</div>
        @endif

        <form id="order-form" method="POST" action="{{ route('orders.store') }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            @csrf
            <section class="bubble laundry-card p-5 sm:p-7">
                <div class="relative z-10 mt-7">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#002c76]">Customer info</p>
                            <h3 class="mt-1 text-lg font-bold text-slate-800">Customer details</h3>
                        </div>
                        <span class="text-xs text-slate-500">Required for every order</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-slate-700">Customer name</label>
                            <input id="customer_name" name="customer_name" type="text" inputmode="text" value="{{ old('customer_name') }}" list="customers-list" autocomplete="name" placeholder="e.g. Maria Santos" required class="mt-1 w-full rounded-xl border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-[#002c76] focus:ring-[#002c76]">
                            <datalist id="customers-list">
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->name }}">{{ $cust->code }} @if($cust->contact_number) · {{ $cust->contact_number }} @endif</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label for="customer_contact" class="block text-sm font-medium text-slate-700">Contact number (optional)</label>
                            <input id="customer_contact" name="customer_contact" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('customer_contact') }}" placeholder="e.g. 0917 123 4567" class="mt-1 w-full rounded-xl border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-[#002c76] focus:ring-[#002c76]">
                        </div>
                    </div>
                </div>

                <div class="pos-accordion relative z-10 mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div>
                        <button type="button" class="pos-section-toggle pos-section-toggle--services group flex w-full items-center justify-between gap-4 p-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#002c76]" aria-expanded="true" aria-controls="service-list" data-pos-section="services">
                            <span class="min-w-0">
                                <span class="block text-[11px] font-bold uppercase tracking-[0.14em] text-[#002c76]">Choose services</span>
                                <span role="heading" aria-level="3" class="mt-1 block text-base font-bold text-slate-800 sm:text-lg">Laundry items</span>
                                <span class="mt-1 block text-xs text-slate-500">Select a service to enter its quantity</span>
                            </span>
                            <span class="pos-section-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-full">
                                <svg class="pos-section-chevron h-5 w-5 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </span>
                        </button>
                    </div>
                    <div id="service-list" class="pos-section-content border-t border-slate-200 bg-slate-50">
                        <div class="pos-section-inner space-y-3">
                            @foreach($services as $service)
                            @php
                                $serviceColor = $serviceColors[$loop->index % count($serviceColors)];
                                $serviceUnit = match ($service->pricing_method) {
                                    'per_piece' => 'piece',
                                    'per_kg', 'per_load' => 'kg',
                                    'fixed' => 'service',
                                    default => str_replace('per_', '', $service->pricing_method),
                                };
                                $configuredQuantity = (float) ($service->minimum_weight ?? 0);
                                $minimumQuantity = in_array($service->pricing_method, ['fixed', 'per_load'], true)
                                    ? 1
                                    : ($configuredQuantity > 0 ? $configuredQuantity : 1);
                                $quantityPrecision = match ($service->pricing_method) {
                                    'per_kg', 'per_load' => 2,
                                    default => 0,
                                };
                                $minimumAmount = max(
                                    ($service->pricing_method === 'fixed' ? 1 : $minimumQuantity) * (float) $service->rate,
                                    (float) ($service->minimum_charge ?? 0),
                                ) + (float) ($service->additional_charge ?? 0);
                            @endphp
                            <div class="service-row grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 rounded-2xl border border-slate-100 bg-white p-3 transition hover:border-blue-200 hover:shadow-sm sm:grid-cols-[auto_minmax(0,1fr)_10rem] sm:gap-3 sm:p-4" style="--service-accent: {{ $serviceColor['accent'] }}; --service-tint: {{ $serviceColor['tint'] }};" data-service-name="{{ $service->name }}" data-rate="{{ $service->rate }}" data-method="{{ $service->pricing_method }}" data-minimum-weight="{{ $service->pricing_method === 'per_load' ? 0 : ($service->minimum_weight ?? 0) }}" data-load-capacity-kg="{{ $service->pricing_method === 'per_load' ? ($service->minimum_weight ?? '') : '' }}" data-minimum-charge="{{ $service->minimum_charge ?? 0 }}" data-additional-charge="{{ $service->additional_charge ?? 0 }}">
                                <div class="flex items-center">
                                    <input id="toggle-{{ $service->id }}" class="service-toggle h-5 w-5 rounded border-slate-300 text-[#002c76] focus:ring-[#002c76]" type="checkbox" name="services[{{ $loop->index }}][service_id]" value="{{ $service->id }}" @disabled($service->pricing_method === 'per_load' && $configuredQuantity <= 0)>
                                </div>
                                <label class="cursor-pointer" for="toggle-{{ $service->id }}">
                                    <span class="block font-semibold text-slate-800">{{ $service->name }}</span>
                                </label>
                                <input id="service-{{ $service->id }}" class="service-quantity hidden" type="number" step="0.001" min="0.001" name="services[{{ $loop->index }}][quantity]" disabled>
                                <button type="button" class="service-quantity-trigger col-start-3 row-start-1 flex flex-col items-end justify-center rounded-lg px-1 py-1 text-right disabled:cursor-default sm:px-2" aria-label="Select {{ $service->name }} to enter quantity" disabled>
                                    <span class="service-quantity-display text-sm font-semibold">₱{{ number_format($service->pricing_method === 'per_load' ? (float) $service->rate : $minimumAmount, 2) }}{{ $service->pricing_method === 'per_load' ? ' / load' : ' / min' }}</span>
                                    <span class="service-minimum-display text-xs text-slate-500">{{ $service->pricing_method === 'per_load' ? ($configuredQuantity > 0 ? number_format($configuredQuantity, 2).' kg per load' : 'Set kg per load in service settings') : number_format($minimumQuantity, $quantityPrecision).' '.$serviceUnit.' minimum' }}</span>
                                </button>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="pos-accordion relative z-10 mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div>
                        <button type="button" class="pos-section-toggle pos-section-toggle--addons group flex w-full items-center justify-between gap-4 p-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#002c76]" aria-expanded="true" aria-controls="addon-list" data-pos-section="addons">
                            <span class="min-w-0">
                                <span class="block text-[11px] font-bold uppercase tracking-[0.14em] text-[#002c76]">Choose add-ons</span>
                                <span role="heading" aria-level="3" class="mt-1 block text-base font-bold text-slate-800 sm:text-lg">Optional extras</span>
                                <span class="mt-1 block text-xs text-slate-500">Select any extras to include with this order</span>
                            </span>
                            <span class="pos-section-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-full">
                                <svg class="pos-section-chevron h-5 w-5 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </span>
                        </button>
                    </div>
                    @if($addOns->isNotEmpty())
                        <div id="addon-list" class="pos-section-content border-t border-slate-200 bg-slate-50">
                            <div class="pos-section-inner space-y-3">
                                @foreach($addOns as $addOn)
                                @php($addOnColor = $serviceColors[$loop->index % count($serviceColors)])
                                @php($oldAddOn = old('add_ons.'.$loop->index, []))
                                <div class="addon-row grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 rounded-2xl border border-slate-100 bg-white p-3 transition hover:border-blue-200 hover:shadow-sm sm:grid-cols-[auto_minmax(0,1fr)_10rem] sm:gap-3 sm:p-4" style="--service-accent: {{ $addOnColor['accent'] }}; --service-tint: {{ $addOnColor['tint'] }};">
                                    <div class="flex items-center">
                                        <input id="addon-{{ $addOn->id }}" class="addon-toggle h-5 w-5 rounded border-slate-300" type="checkbox" name="add_ons[{{ $loop->index }}][add_on_id]" value="{{ $addOn->id }}" data-addon-name="{{ $addOn->name }}" data-price="{{ $addOn->price }}" data-quantity="{{ $oldAddOn['quantity'] ?? '' }}" @checked((string) ($oldAddOn['add_on_id'] ?? '') === (string) $addOn->id)>
                                        <input class="addon-quantity hidden" type="hidden" name="add_ons[{{ $loop->index }}][quantity]" value="{{ $oldAddOn['quantity'] ?? '' }}" disabled>
                                    </div>
                                    <label class="cursor-pointer font-semibold text-slate-800" for="addon-{{ $addOn->id }}">{{ $addOn->name }}</label>
                                    <button type="button" class="addon-quantity-trigger col-start-3 row-start-1 flex flex-col items-end justify-center px-1 py-1 text-right text-sm font-semibold text-[#002c76] sm:px-2" aria-label="Set {{ $addOn->name }} quantity">
                                        <span class="addon-price-display">+₱{{ number_format($addOn->price, 2) }}</span>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div id="addon-list" class="pos-section-content border-t border-slate-200 bg-slate-50">
                            <div class="pos-section-inner">
                                <p class="rounded-xl bg-white p-4 text-sm text-slate-500">No add-ons are currently available.</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="relative z-10 mt-4">
                    <label for="instructions" class="block text-sm font-medium text-slate-700">Laundry instructions or remarks (optional)</label>
                    <textarea id="instructions" name="instructions" placeholder="Add any special handling instructions" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]"></textarea>
                </div>
                @if($errors->any())<ul class="relative z-10 mt-4 text-sm text-red-600">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            </section>

            <div class="space-y-5">
                <aside class="h-fit rounded-3xl bg-[#002c76] p-5 text-white shadow-xl shadow-blue-900/20 sm:p-7">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-200">Order summary</p><h3 class="mt-1 text-2xl font-bold">Your total</h3></div>
                    <div class="mt-6 border-b border-white/15 pb-5">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-blue-200">Order breakdown</h4>
                        <div id="summary-breakdown" class="mt-3 space-y-3" aria-live="polite">
                            <p class="text-sm text-blue-100">No services selected yet.</p>
                        </div>
                    </div>
                    <div class="mt-8 space-y-4 border-b border-white/15 pb-5 text-sm">
                        <div class="flex justify-between"><span class="text-blue-100">Subtotal</span><strong id="summary-subtotal">₱0.00</strong></div>
                        <div class="flex justify-between"><span class="text-blue-100">Add-ons</span><strong id="summary-addons">₱0.00</strong></div>
                    </div>
                    <div class="mt-5 flex items-end justify-between"><span class="text-blue-100">Total</span><strong id="summary-total" class="text-3xl">₱0.00</strong></div>
                    <div class="mt-6 space-y-3 rounded-2xl bg-white/10 p-4 text-sm"><div class="flex justify-between"><span class="text-blue-100">Amount paid</span><strong id="summary-paid">₱0.00</strong></div><div class="flex justify-between"><span class="text-blue-100">Balance</span><strong id="summary-balance">₱0.00</strong></div><div class="flex justify-between"><span class="text-blue-100">Change</span><strong id="summary-change">₱0.00</strong></div></div>
                    <p class="mt-5 text-center text-xs text-blue-200">Prices are recalculated securely on the server when the order is saved.</p>
                </aside>

                <section class="laundry-card space-y-4 p-5 sm:p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#002c76]">Checkout</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-800">Payment and order</h3>
                    </div>
                    <div>
                        <label for="payment_status" class="block text-sm font-medium text-slate-600">Payment status</label>
                        <select id="payment_status" name="payment_status" required class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                            <option value="" disabled selected>Select payment status</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div id="payment-method-field" hidden>
                        <label for="payment_method" class="block text-sm font-medium text-slate-600">Payment method</label>
                        <select id="payment_method" name="payment_method" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>
                    <div id="payment-reference-field" hidden>
                        <label for="payment_reference" class="block text-sm font-medium text-slate-600">GCash reference (optional)</label>
                        <input id="payment_reference" name="payment_reference" placeholder="Enter GCash reference" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                    </div>
                    <input id="cash_tendered" name="cash_tendered" type="hidden" value="">
                    <input name="amount_paid" type="hidden" value="0">
                    <button type="submit" class="tracking-submit w-full rounded-xl px-5 py-3 font-semibold shadow-lg transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" disabled>Create order</button>
                </section>
            </div>

            <div id="cash-payment-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-[#002c76]/60 p-4" role="dialog" aria-modal="true" aria-labelledby="cash-modal-title">
                <div class="w-full max-w-md rounded-3xl bg-white p-6 text-[#002c76] shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/65">Cash payment</p>
                            <h2 id="cash-modal-title" class="mt-1 text-xl font-bold">Enter amount received</h2>
                        </div>
                        <button id="cash-modal-close" type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-[#002c76] hover:bg-[#002c76]/10" aria-label="Close cash payment dialog">&times;</button>
                    </div>
                    <div class="mt-5 rounded-2xl bg-[#002c76] p-4 text-white">
                        <p class="text-sm text-white/80">Order total</p>
                        <p id="cash-modal-total" class="mt-1 text-3xl font-bold">₱0.00</p>
                    </div>
                    <label for="cash-received-input" class="mt-5 block text-sm font-semibold">Customer paid</label>
                    <input id="cash-received-input" type="number" min="0" step="0.01" inputmode="decimal" class="mt-2 w-full rounded-xl border-[#002c76]/25 text-lg text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]" placeholder="Enter cash received">
                    <p id="cash-modal-error" class="mt-2 hidden text-sm font-semibold text-red-700">Amount received must be at least the order total.</p>
                    <div class="mt-4 flex items-center justify-between rounded-xl border border-[#002c76]/15 px-4 py-3">
                        <span class="font-semibold">Change</span>
                        <strong id="cash-modal-change" class="text-xl" aria-live="polite" aria-atomic="true">₱0.00</strong>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button id="cash-modal-cancel" type="button" class="flex-1 rounded-xl border border-[#002c76] px-4 py-3 font-semibold text-[#002c76] hover:bg-[#002c76]/5">Cancel</button>
                        <button id="cash-modal-confirm" type="button" class="flex-1 rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Confirm &amp; print</button>
                    </div>
                </div>
            </div>

            <div id="order-confirmation-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-[#002c76]/60 p-4" role="dialog" aria-modal="true" aria-labelledby="order-confirmation-title">
                <div class="w-full max-w-md rounded-3xl bg-white p-6 text-[#002c76] shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/65">Review order</p>
                            <h2 id="order-confirmation-title" class="mt-1 text-xl font-bold">Confirm this order?</h2>
                        </div>
                        <button id="order-confirmation-close" type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-[#002c76] hover:bg-[#002c76]/10" aria-label="Close order confirmation">&times;</button>
                    </div>
                    <div id="order-confirmation-services" tabindex="0" aria-label="Selected order items" class="order-confirmation-list mt-5 max-h-48 space-y-3 overflow-y-auto rounded-2xl bg-slate-50 p-4 text-sm"></div>
                    <p id="order-confirmation-scroll-hint" class="mt-2 hidden text-center text-xs font-medium text-[#002c76]/70">More items below — scroll to see the rest</p>
                    <dl class="mt-4 space-y-2 rounded-2xl border border-[#002c76]/10 p-4 text-sm">
                        <div class="flex justify-between gap-4"><dt>Total</dt><dd id="order-confirmation-total" class="font-bold">₱0.00</dd></div>
                        <div class="flex justify-between gap-4"><dt>Payment status</dt><dd id="order-confirmation-payment" class="font-semibold">Unpaid</dd></div>
                    </dl>
                    <p class="mt-4 text-sm text-slate-500">Please check the order details before continuing.</p>
                    <div class="mt-6 flex gap-3">
                        <button id="order-confirmation-cancel" type="button" class="flex-1 rounded-xl border border-[#002c76] px-4 py-3 font-semibold text-[#002c76] hover:bg-[#002c76]/5">Go back</button>
                        <button id="order-confirmation-confirm" type="button" class="flex-1 rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white hover:bg-blue-900">Confirm order</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <button id="floating-order-summary-button" type="button" class="floating-order-summary-button is-collapsed fixed bottom-4 right-4 z-40 inline-flex items-center gap-3 rounded-full bg-[#002c76] px-5 py-3 text-left text-white shadow-xl shadow-blue-950/25 transition hover:bg-blue-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#002c76] focus-visible:ring-offset-2 lg:hidden" aria-label="Expand order summary button" aria-expanded="false" aria-haspopup="dialog" aria-controls="floating-order-summary-modal">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.75A1.75 1.75 0 015.75 4h12.5A1.75 1.75 0 0120 5.75v12.5A1.75 1.75 0 0118.25 20H5.75A1.75 1.75 0 014 18.25V5.75zM8 8h8M8 12h8M8 16h4"/></svg>
        </span>
        <span class="floating-order-summary-button-details" hidden>
            <span class="block text-xs font-medium text-blue-100">View order summary</span>
            <span id="floating-order-summary-total" class="block text-sm font-bold">₱0.00</span>
        </span>
    </button>

    <dialog id="floating-order-summary-modal" class="order-summary-dialog w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-3xl border-0 bg-white p-0 text-slate-800 shadow-2xl" aria-labelledby="floating-order-summary-title">
        <div class="flex flex-col">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/70">Order summary</p>
                    <h2 id="floating-order-summary-title" class="mt-1 text-xl font-bold">Your total</h2>
                </div>
                <button id="floating-order-summary-close" type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-[#002c76] hover:bg-[#002c76]/10" aria-label="Close order summary">&times;</button>
            </div>
            <div class="order-summary-scroll-area min-h-0 flex-1 overflow-y-auto overscroll-contain p-5" tabindex="0" aria-label="Scrollable order summary and checkout">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Order breakdown</h3>
                <div id="floating-order-summary-breakdown" class="mt-3 space-y-3 rounded-2xl bg-slate-50 p-4 text-sm" aria-live="polite"></div>
                <dl class="mt-5 space-y-3 border-b border-slate-200 pb-5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Subtotal</dt><dd id="floating-order-summary-subtotal" class="font-semibold">₱0.00</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Add-ons</dt><dd id="floating-order-summary-addons" class="font-semibold">₱0.00</dd></div>
                </dl>
                <div class="mt-5 flex items-center justify-between gap-4">
                    <span class="font-semibold text-slate-600">Total</span>
                    <strong id="floating-order-summary-total-detail" class="text-2xl font-bold text-[#002c76]">₱0.00</strong>
                </div>
                <dl class="mt-5 space-y-3 rounded-2xl bg-slate-50 p-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Amount paid</dt><dd id="floating-order-summary-paid" class="font-semibold">₱0.00</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Balance</dt><dd id="floating-order-summary-balance" class="font-semibold">₱0.00</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Change</dt><dd id="floating-order-summary-change" class="font-semibold">₱0.00</dd></div>
                </dl>
                <section class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#002c76]">Checkout</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-800">Payment and order</h3>
                    </div>
                    <div>
                        <label for="floating-payment-status" class="block text-sm font-medium text-slate-600">Payment status</label>
                        <select id="floating-payment-status" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                            <option value="" disabled selected>Select payment status</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div id="floating-payment-method-field" hidden>
                        <label for="floating-payment-method" class="block text-sm font-medium text-slate-600">Payment method</label>
                        <select id="floating-payment-method" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>
                    <div id="floating-payment-reference-field" hidden>
                        <label for="floating-payment-reference" class="block text-sm font-medium text-slate-600">GCash reference (optional)</label>
                        <input id="floating-payment-reference" placeholder="Enter GCash reference" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                    </div>
                </section>
            </div>
            <div class="shrink-0 border-t border-slate-200 bg-white p-4">
                <div class="flex gap-3">
                    <button id="floating-order-summary-done" type="button" class="flex-1 rounded-xl border border-[#002c76] px-4 py-3 font-semibold text-[#002c76] transition hover:bg-[#002c76]/5">Back</button>
                    <button id="floating-order-summary-checkout" type="submit" form="order-form" class="tracking-submit flex-1 rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white transition hover:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50" disabled>Create order</button>
                </div>
            </div>
        </div>
    </dialog>

    <div id="service-quantity-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-[#002c76]/60 p-4" role="dialog" aria-modal="true" aria-labelledby="service-quantity-title">
        <div class="w-full max-w-md rounded-3xl bg-white p-6 text-[#002c76] shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="service-quantity-context" class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/65">Laundry service</p>
                    <h2 id="service-quantity-title" class="mt-1 text-xl font-bold">Enter quantity</h2>
                </div>
                <button id="service-quantity-close" type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-[#002c76] hover:bg-[#002c76]/10" aria-label="Close quantity dialog">&times;</button>
            </div>
            <label id="service-quantity-label" for="service-quantity-input" class="mt-5 block text-sm font-semibold">Quantity</label>
            <input id="service-quantity-input" type="number" min="0.001" step="0.001" inputmode="decimal" class="mt-2 w-full rounded-xl border-[#002c76]/25 text-lg text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]" placeholder="Enter quantity">
            <div class="mt-3 flex justify-center gap-3">
                <button id="addon-quantity-decrement" type="button" class="hidden h-11 w-14 rounded-xl border border-[#002c76]/25 text-2xl font-semibold text-[#002c76] transition hover:bg-[#002c76]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#002c76]" aria-label="Decrease quantity">−</button>
                <button id="addon-quantity-increment" type="button" class="hidden h-11 w-14 rounded-xl border border-[#002c76]/25 text-2xl font-semibold text-[#002c76] transition hover:bg-[#002c76]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#002c76]" aria-label="Increase quantity">+</button>
            </div>
            <p id="service-quantity-error" class="mt-2 hidden text-sm font-semibold text-red-700">Enter a quantity greater than zero.</p>
            <div class="mt-6 flex gap-3">
                <button id="service-quantity-cancel" type="button" class="flex-1 rounded-xl border border-[#002c76] px-2 py-2.5 text-sm font-semibold text-[#002c76] hover:bg-[#002c76]/5">Cancel</button>
                <button id="service-quantity-confirm" type="button" class="flex-1 whitespace-nowrap rounded-xl bg-[#002c76] px-2 py-2.5 text-sm font-semibold text-white hover:bg-blue-900">Confirm quantity</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const money = value => `₱${value.toFixed(2)}`;
            const form = document.getElementById('order-form');
            const paymentStatus = document.getElementById('payment_status');
            const paymentMethod = document.getElementById('payment_method');
            const paymentMethodField = document.getElementById('payment-method-field');
            const paymentReferenceField = document.getElementById('payment-reference-field');
            const cashTendered = document.getElementById('cash_tendered');
            const modal = document.getElementById('cash-payment-modal');
            const cashReceivedInput = document.getElementById('cash-received-input');
            const cashModalTotal = document.getElementById('cash-modal-total');
            const cashModalChange = document.getElementById('cash-modal-change');
            const cashModalError = document.getElementById('cash-modal-error');
            const cashModalConfirm = document.getElementById('cash-modal-confirm');
            const orderConfirmationModal = document.getElementById('order-confirmation-modal');
            const orderConfirmationServices = document.getElementById('order-confirmation-services');
            const orderConfirmationTotal = document.getElementById('order-confirmation-total');
            const orderConfirmationPayment = document.getElementById('order-confirmation-payment');
            const orderConfirmationConfirm = document.getElementById('order-confirmation-confirm');
            const quantityModal = document.getElementById('service-quantity-modal');
            const quantityModalTitle = document.getElementById('service-quantity-title');
            const quantityModalContext = document.getElementById('service-quantity-context');
            const quantityModalLabel = document.getElementById('service-quantity-label');
            const quantityModalInput = document.getElementById('service-quantity-input');
            const quantityModalError = document.getElementById('service-quantity-error');
            const addonQuantityDecrement = document.getElementById('addon-quantity-decrement');
            const addonQuantityIncrement = document.getElementById('addon-quantity-increment');
            const floatingSummaryButton = document.getElementById('floating-order-summary-button');
            const floatingSummaryModal = document.getElementById('floating-order-summary-modal');
            const floatingSummaryClose = document.getElementById('floating-order-summary-close');
            const floatingSummaryDone = document.getElementById('floating-order-summary-done');
            const floatingSummaryDetails = floatingSummaryButton.querySelector('.floating-order-summary-button-details');
            const floatingPaymentStatus = document.getElementById('floating-payment-status');
            const floatingPaymentMethod = document.getElementById('floating-payment-method');
            const floatingPaymentMethodField = document.getElementById('floating-payment-method-field');
            const floatingPaymentReference = document.getElementById('floating-payment-reference');
            const floatingPaymentReferenceField = document.getElementById('floating-payment-reference-field');
            const draftStorageKey = 'laundry-pos-order-draft';
            const orderSummaryScrollArea = floatingSummaryModal.querySelector('.order-summary-scroll-area');
            const clearSavedDraft = () => {
                try {
                    sessionStorage.removeItem(draftStorageKey);
                } catch (error) {
                    console.error('Unable to clear the POS order draft from this tab.', error);
                }

                try {
                    localStorage.removeItem(draftStorageKey);
                } catch (error) {
                    console.error('Unable to clear the saved POS order draft.', error);
                }
            };
            let confirmedCashPayment = false;
            let confirmedOrder = false;
            let pendingQuantityRow = null;
            let pendingAddonRow = null;
            let savedDraft = null;
            let modalState = null;
            let previousSummaryFocus = null;
            let lockedPageScrollY = null;
            let previousBodyScrollStyles = null;
            let previousHtmlOverflow = '';

            const lockPageScroll = () => {
                if (lockedPageScrollY !== null) {
                    return;
                }

                lockedPageScrollY = window.scrollY;
                previousBodyScrollStyles = {
                    position: document.body.style.position,
                    top: document.body.style.top,
                    left: document.body.style.left,
                    right: document.body.style.right,
                    width: document.body.style.width,
                    overflow: document.body.style.overflow,
                };
                previousHtmlOverflow = document.documentElement.style.overflow;
                document.documentElement.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.top = `-${lockedPageScrollY}px`;
                document.body.style.left = '0';
                document.body.style.right = '0';
                document.body.style.width = '100%';
                document.body.style.overflow = 'hidden';
            };

            const unlockPageScroll = () => {
                if (lockedPageScrollY === null || !previousBodyScrollStyles) {
                    return;
                }

                const scrollY = lockedPageScrollY;
                Object.entries(previousBodyScrollStyles).forEach(([property, value]) => {
                    document.body.style[property] = value;
                });
                document.documentElement.style.overflow = previousHtmlOverflow;
                lockedPageScrollY = null;
                previousBodyScrollStyles = null;
                window.scrollTo(0, scrollY);
            };

            try {
                const draft = sessionStorage.getItem(draftStorageKey) ?? localStorage.getItem(draftStorageKey);
                savedDraft = draft ? JSON.parse(draft) : null;
                if (savedDraft && savedDraft.draftVersion !== 2) {
                    savedDraft = { ...savedDraft, paymentStatus: '' };
                }
                modalState = savedDraft?.modalState ?? null;
            } catch (error) {
                console.error('Unable to read the saved POS order draft.', error);
            }

            const saveDraft = () => {
                const draft = {
                    draftVersion: 2,
                    customerName: document.getElementById('customer_name').value,
                    customerContact: document.getElementById('customer_contact').value,
                    instructions: form.querySelector('[name="instructions"]').value,
                    paymentStatus: paymentStatus.value,
                    paymentMethod: paymentMethod.value,
                    paymentReference: document.getElementById('payment_reference').value,
                    cashReceived: cashReceivedInput.value,
                    confirmedCashPayment,
                    modalState,
                    collapsedSections: {},
                    services: {},
                    addOns: {},
                };

                document.querySelectorAll('.pos-section-toggle').forEach(toggle => {
                    draft.collapsedSections[toggle.dataset.posSection] = toggle.getAttribute('aria-expanded') === 'false';
                });

                document.querySelectorAll('.service-row').forEach(row => {
                    const toggle = row.querySelector('.service-toggle');
                    const quantity = row.querySelector('.service-quantity');
                    if (toggle.checked && quantity.value !== '') {
                        draft.services[toggle.value] = quantity.value;
                    }
                });

                document.querySelectorAll('.addon-toggle:checked').forEach(toggle => {
                    if (toggle.dataset.quantity) {
                        draft.addOns[toggle.value] = toggle.dataset.quantity;
                    }
                });

                try {
                    const serializedDraft = JSON.stringify(draft);
                    sessionStorage.setItem(draftStorageKey, serializedDraft);
                    localStorage.setItem(draftStorageKey, serializedDraft);
                } catch (error) {
                    console.error('Unable to save the POS order draft for this tab.', error);
                }
            };

            const setSectionCollapsed = (sectionName, isCollapsed, persist = true) => {
                const toggle = document.querySelector(`.pos-section-toggle[data-pos-section="${sectionName}"]`);
                if (!toggle) {
                    return;
                }

                const content = document.getElementById(toggle.getAttribute('aria-controls'));
                toggle.setAttribute('aria-expanded', String(!isCollapsed));
                content.inert = isCollapsed;
                content.setAttribute('aria-hidden', String(isCollapsed));
                content.classList.toggle('is-collapsed', isCollapsed);
                toggle.querySelector('.pos-section-chevron').classList.toggle('rotate-[-90deg]', isCollapsed);
                if (persist) {
                    saveDraft();
                }
            };

            const restoreDraft = () => {
                if (!savedDraft || typeof savedDraft !== 'object') {
                    return;
                }

                document.getElementById('customer_name').value = savedDraft.customerName ?? '';
                document.getElementById('customer_contact').value = savedDraft.customerContact ?? '';
                form.querySelector('[name="instructions"]').value = savedDraft.instructions ?? '';
                if (['paid', 'unpaid'].includes(savedDraft.paymentStatus)) {
                    paymentStatus.value = savedDraft.paymentStatus;
                }
                if (['cash', 'gcash', 'other'].includes(savedDraft.paymentMethod)) {
                    paymentMethod.value = savedDraft.paymentMethod;
                }
                document.getElementById('payment_reference').value = savedDraft.paymentReference ?? '';
                cashReceivedInput.value = savedDraft.cashReceived ?? '';
                confirmedCashPayment = savedDraft.confirmedCashPayment === true;
                Object.entries(savedDraft.collapsedSections ?? {}).forEach(([sectionName, isCollapsed]) => {
                    if (typeof isCollapsed === 'boolean') {
                        setSectionCollapsed(sectionName, isCollapsed, false);
                    }
                });

                document.querySelectorAll('.service-row').forEach(row => {
                    const toggle = row.querySelector('.service-toggle');
                    const quantity = row.querySelector('.service-quantity');
                    const trigger = row.querySelector('.service-quantity-trigger');
                    const savedQuantity = savedDraft.services?.[toggle.value];
                    if (!savedQuantity) {
                        return;
                    }

                    toggle.checked = true;
                    quantity.value = savedQuantity;
                    quantity.disabled = false;
                    trigger.disabled = false;
                    const unit = unitForMethod(row.dataset.method);
                    const formattedQuantity = formatQuantity(savedQuantity, row.dataset.method);
                    trigger.querySelector('.service-quantity-display').textContent = `${formattedQuantity} ${unit}`;
                    trigger.setAttribute('aria-label', `Edit ${row.dataset.serviceName} quantity, ${formattedQuantity} ${unit}`);
                    row.classList.add('is-selected');
                });

                document.querySelectorAll('.addon-toggle').forEach(toggle => {
                    const row = toggle.closest('.addon-row');
                    const quantity = row.querySelector('.addon-quantity');
                    const savedQuantity = savedDraft.addOns?.[toggle.value];
                    if (!savedQuantity || !Number.isInteger(Number(savedQuantity)) || Number(savedQuantity) < 1) {
                        toggle.checked = false;
                        toggle.dataset.quantity = '';
                        quantity.value = '';
                        quantity.disabled = true;
                        row.classList.remove('is-selected');
                        return;
                    }

                    toggle.checked = true;
                    toggle.dataset.quantity = String(savedQuantity);
                    quantity.value = String(savedQuantity);
                    quantity.disabled = false;
                    row.classList.add('is-selected');
                    row.querySelector('.addon-price-display').textContent = `${savedQuantity} × ${money(Number(toggle.dataset.price))}`;
                    row.querySelector('.addon-quantity-trigger').setAttribute(
                        'aria-label',
                        `Edit ${toggle.dataset.addonName} quantity, ${savedQuantity}`,
                    );
                });
            };

            const restoreOpenModal = () => {
                if (!modalState || typeof modalState !== 'object') {
                    return;
                }

                if (modalState.type === 'service-quantity') {
                    const row = [...document.querySelectorAll('.service-row')].find(
                        candidate => candidate.querySelector('.service-toggle').value === String(modalState.serviceId),
                    );
                    if (row) {
                        row.querySelector('.service-toggle').checked = true;
                        row.querySelector('.service-quantity').disabled = false;
                        row.querySelector('.service-quantity-trigger').disabled = false;
                        openQuantityModal(row, false, modalState.value ?? '');
                    }
                    return;
                }

                if (modalState.type === 'addon-quantity') {
                    const row = [...document.querySelectorAll('.addon-row')].find(
                        candidate => candidate.querySelector('.addon-toggle').value === String(modalState.addOnId),
                    );
                    if (row) {
                        row.querySelector('.addon-toggle').checked = true;
                        openAddonQuantityModal(row, false, modalState.value ?? '');
                    }
                    return;
                }

                if (modalState.type === 'cash-payment') {
                    openCashModal(false, modalState.cashReceived ?? cashReceivedInput.value);
                    return;
                }

                if (modalState.type === 'order-confirmation') {
                    openOrderConfirmation(false);
                }
            };

            const calculatedTotal = () => {
                const subtotal = Number(document.getElementById('summary-subtotal').textContent.replace(/[^\d.]/g, '')) || 0;
                const addOns = Number(document.getElementById('summary-addons').textContent.replace(/[^\d.]/g, '')) || 0;

                return subtotal + addOns;
            };

            const refreshFloatingOrderSummary = () => {
                const summaryFields = [
                    ['summary-subtotal', 'floating-order-summary-subtotal'],
                    ['summary-addons', 'floating-order-summary-addons'],
                    ['summary-total', 'floating-order-summary-total-detail'],
                    ['summary-paid', 'floating-order-summary-paid'],
                    ['summary-balance', 'floating-order-summary-balance'],
                    ['summary-change', 'floating-order-summary-change'],
                ];

                summaryFields.forEach(([sourceId, targetId]) => {
                    document.getElementById(targetId).textContent = document.getElementById(sourceId).textContent;
                });
                floatingSummaryButton.querySelector('#floating-order-summary-total').textContent =
                    document.getElementById('summary-total').textContent;

                const breakdown = document.getElementById('summary-breakdown');
                const floatingBreakdown = document.getElementById('floating-order-summary-breakdown');
                const items = [...breakdown.children].map(item => {
                    const clone = item.cloneNode(true);
                    [clone, ...clone.querySelectorAll('*')].forEach(element => {
                        [
                            ['text-white', 'text-slate-800'],
                            ['text-blue-100', 'text-slate-500'],
                            ['text-blue-200', 'text-[#002c76]'],
                            ['border-white/20', 'border-slate-300'],
                        ].forEach(([oldClass, newClass]) => {
                            if (element.classList.contains(oldClass)) {
                                element.classList.replace(oldClass, newClass);
                            }
                        });
                    });
                    return clone;
                });
                floatingBreakdown.replaceChildren(...items);
            };

            const collapseFloatingSummaryButton = () => {
                floatingSummaryButton.classList.add('is-collapsed');
                floatingSummaryButton.setAttribute('aria-expanded', 'false');
                floatingSummaryButton.setAttribute('aria-label', 'Expand order summary button');
                floatingSummaryDetails.hidden = true;
            };

            const updatePaymentFields = () => {
                const isPaid = paymentStatus.value === 'paid';
                paymentMethodField.hidden = !isPaid;
                paymentReferenceField.hidden = !isPaid || paymentMethod.value !== 'gcash';
                floatingPaymentStatus.value = paymentStatus.value;
                floatingPaymentMethod.value = paymentMethod.value;
                floatingPaymentReference.value = document.getElementById('payment_reference').value;
                floatingPaymentMethodField.hidden = !isPaid;
                floatingPaymentReferenceField.hidden = !isPaid || paymentMethod.value !== 'gcash';
                const total = calculatedTotal();
                const tendered = Number.parseFloat(cashReceivedInput.value) || 0;
                cashModalTotal.textContent = money(total);
                cashModalChange.textContent = money(tendered - total);
                cashModalConfirm.disabled = tendered < total;
                cashModalError.classList.toggle('hidden', tendered >= total || cashReceivedInput.value === '');
            };

            const openCashModal = (focusInput = true, cashReceived = '') => {
                const total = calculatedTotal();
                cashModalTotal.textContent = money(total);
                cashReceivedInput.value = cashReceived;
                cashModalChange.textContent = money(0);
                cashModalError.classList.add('hidden');
                cashModalConfirm.disabled = total > 0;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modalState = { type: 'cash-payment', cashReceived: cashReceivedInput.value };
                saveDraft();
                if (focusInput) {
                    cashReceivedInput.focus();
                }
            };

            const closeCashModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modalState = null;
                saveDraft();
            };

            const openOrderConfirmation = (focusConfirm = true) => {
                orderConfirmationServices.replaceChildren();
                let hasSelectedService = false;
                document.querySelectorAll('.service-row').forEach(row => {
                    const toggle = row.querySelector('.service-toggle');
                    const quantity = row.querySelector('.service-quantity');
                    if (!toggle.checked) return;

                    if (!hasSelectedService) {
                        const sectionLabel = document.createElement('p');
                        sectionLabel.className = 'text-xs font-semibold uppercase tracking-wide text-[#002c76]';
                        sectionLabel.textContent = 'Services';
                        orderConfirmationServices.append(sectionLabel);
                    }
                    hasSelectedService = true;
                    const item = document.createElement('div');
                    item.className = 'ml-3 flex justify-between gap-3 border-l border-[#002c76]/15 pl-3';
                    const description = document.createElement('span');
                    const method = row.dataset.method;
                    const unit = { per_kg: 'kg', per_piece: 'piece', per_load: 'kg', fixed: '' }[method] ?? method;
                    const qty = Number.parseFloat(quantity.value) || 0;
                    const loadCapacity = Number(row.dataset.loadCapacityKg);
                    description.textContent = method === 'per_load' && loadCapacity > 0
                        ? `${row.dataset.serviceName} · ${qty} kg (${Math.ceil(qty / loadCapacity)} loads)`
                        : `${row.dataset.serviceName} · ${qty} ${unit}`.trim();
                    const amount = document.createElement('strong');
                    amount.textContent = row.dataset.lineTotal ? money(Number(row.dataset.lineTotal)) : '—';
                    item.append(description, amount);
                    orderConfirmationServices.append(item);
                });

                let hasSelectedAddon = false;
                document.querySelectorAll('.addon-toggle:checked').forEach(toggle => {
                    if (!hasSelectedAddon) {
                        const sectionLabel = document.createElement('p');
                        sectionLabel.className = 'pt-2 text-xs font-semibold uppercase tracking-wide text-[#002c76]';
                        sectionLabel.textContent = 'Add-ons';
                        orderConfirmationServices.append(sectionLabel);
                    }
                    hasSelectedAddon = true;
                    const item = document.createElement('div');
                    item.className = 'ml-3 border-l border-[#002c76]/15 pl-3';
                    const heading = document.createElement('div');
                    heading.className = 'flex justify-between gap-3 text-sm font-semibold';
                    const description = document.createElement('span');
                    description.className = 'min-w-0 truncate text-[#002c76]';
                    const quantity = Number(toggle.dataset.quantity);
                    const price = Number(toggle.dataset.price);
                    description.textContent = toggle.dataset.addonName;
                    const amount = document.createElement('strong');
                    amount.className = 'shrink-0 text-[#002c76]';
                    amount.textContent = money(price * quantity);
                    heading.append(description, amount);
                    const details = document.createElement('p');
                    details.className = 'mt-1 text-xs text-slate-500';
                    details.textContent = `${quantity} × ${money(price)}`;
                    item.append(heading, details);
                    orderConfirmationServices.append(item);
                });

                if (!hasSelectedService && !hasSelectedAddon) {
                    const empty = document.createElement('p');
                    empty.className = 'text-slate-500';
                    empty.textContent = 'No services or add-ons selected.';
                    orderConfirmationServices.append(empty);
                }

                orderConfirmationTotal.textContent = money(calculatedTotal());
                orderConfirmationPayment.textContent = paymentStatus.options[paymentStatus.selectedIndex].text;
                lockPageScroll();
                orderConfirmationModal.classList.remove('hidden');
                orderConfirmationModal.classList.add('flex');
                modalState = { type: 'order-confirmation' };
                saveDraft();
                requestAnimationFrame(() => {
                    const hasOverflow = orderConfirmationServices.scrollHeight > orderConfirmationServices.clientHeight;
                    orderConfirmationServices.classList.toggle('has-overflow', hasOverflow);
                    document.getElementById('order-confirmation-scroll-hint').classList.toggle('hidden', !hasOverflow);
                });
                if (focusConfirm) {
                    orderConfirmationConfirm.focus();
                }
            };

            const closeOrderConfirmation = () => {
                orderConfirmationModal.classList.add('hidden');
                orderConfirmationModal.classList.remove('flex');
                unlockPageScroll();
                modalState = null;
                saveDraft();
            };

            let confirmationDragStartY = 0;
            let confirmationDragStartScrollTop = 0;
            let confirmationDragPointerId = null;
            let confirmationDragMoved = false;
            orderConfirmationServices.addEventListener('pointerdown', event => {
                if (
                    event.pointerType !== 'mouse'
                    || event.button !== 0
                    || event.target.closest('button, input, select, textarea, a')
                ) {
                    return;
                }

                confirmationDragStartY = event.clientY;
                confirmationDragStartScrollTop = orderConfirmationServices.scrollTop;
                confirmationDragPointerId = event.pointerId;
                confirmationDragMoved = false;
            });
            document.addEventListener('pointermove', event => {
                if (event.pointerId !== confirmationDragPointerId) {
                    return;
                }

                const dragDistance = event.clientY - confirmationDragStartY;
                if (!confirmationDragMoved && Math.abs(dragDistance) < 4) {
                    return;
                }

                confirmationDragMoved = true;
                orderConfirmationServices.classList.add('is-dragging');
                orderConfirmationServices.scrollTop = confirmationDragStartScrollTop - dragDistance;
                event.preventDefault();
            });
            const stopConfirmationDrag = event => {
                if (event.pointerId !== confirmationDragPointerId) {
                    return;
                }

                confirmationDragPointerId = null;
                orderConfirmationServices.classList.remove('is-dragging');
            };
            document.addEventListener('pointerup', stopConfirmationDrag);
            document.addEventListener('pointercancel', stopConfirmationDrag);

            const unitForMethod = method => ({
                per_kg: 'kg',
                per_piece: 'piece',
                per_load: 'kg',
                fixed: 'quantity',
            }[method] ?? method);
            const precisionForMethod = method => ['per_kg', 'per_load'].includes(method) ? 2 : 0;
            const formatQuantity = (quantity, method) => Number(quantity).toFixed(precisionForMethod(method));

            const openQuantityModal = (row, focusControl = true, restoredValue = null) => {
                pendingQuantityRow = row;
                pendingAddonRow = null;
                quantityModalContext.textContent = 'Laundry service';
                document.getElementById('service-quantity-confirm').textContent = 'Confirm quantity';
                addonQuantityDecrement.setAttribute('aria-label', 'Decrease service quantity');
                addonQuantityIncrement.setAttribute('aria-label', 'Increase service quantity');
                const hasWholeUnitQuantity = !['per_kg', 'per_load'].includes(row.dataset.method);
                addonQuantityDecrement.classList.toggle('hidden', !hasWholeUnitQuantity);
                addonQuantityIncrement.classList.toggle('hidden', !hasWholeUnitQuantity);
                const unit = unitForMethod(row.dataset.method);
                quantityModalTitle.textContent = row.dataset.serviceName;
                quantityModalLabel.textContent = `Quantity (${unit})`;
                quantityModalInput.value = restoredValue ?? (row.querySelector('.service-quantity').value
                    ? formatQuantity(row.querySelector('.service-quantity').value, row.dataset.method)
                    : '');
                quantityModalInput.step = precisionForMethod(row.dataset.method) === 2 ? '0.01' : '1';
                quantityModalInput.min = quantityModalInput.step;
                quantityModalInput.max = '';
                quantityModalError.textContent = row.dataset.method === 'per_load'
                    ? 'Enter the actual laundry weight in kg.'
                    : 'Enter a quantity greater than zero.';
                quantityModalError.classList.add('hidden');
                quantityModal.classList.remove('hidden');
                quantityModal.classList.add('flex');
                modalState = {
                    type: 'service-quantity',
                    serviceId: row.querySelector('.service-toggle').value,
                    value: quantityModalInput.value,
                };
                saveDraft();
                if (focusControl) {
                    (hasWholeUnitQuantity ? addonQuantityIncrement : document.getElementById('service-quantity-confirm')).focus();
                }
            };

            const openAddonQuantityModal = (row, focusControl = true, restoredValue = null) => {
                pendingAddonRow = row;
                pendingQuantityRow = null;
                const toggle = row.querySelector('.addon-toggle');
                quantityModalContext.textContent = 'Optional add-on';
                document.getElementById('service-quantity-confirm').textContent = 'Confirm add-on';
                addonQuantityDecrement.setAttribute('aria-label', 'Decrease add-on quantity');
                addonQuantityIncrement.setAttribute('aria-label', 'Increase add-on quantity');
                addonQuantityDecrement.classList.remove('hidden');
                addonQuantityIncrement.classList.remove('hidden');
                quantityModalTitle.textContent = toggle.dataset.addonName;
                quantityModalLabel.textContent = 'Quantity';
                quantityModalInput.value = restoredValue ?? toggle.dataset.quantity ?? '';
                quantityModalInput.step = '1';
                quantityModalInput.min = '1';
                quantityModalInput.max = '';
                quantityModalError.textContent = 'Enter a whole number greater than zero.';
                quantityModalError.classList.add('hidden');
                quantityModal.classList.remove('hidden');
                quantityModal.classList.add('flex');
                modalState = {
                    type: 'addon-quantity',
                    addOnId: toggle.value,
                    value: quantityModalInput.value,
                };
                saveDraft();
                if (focusControl) {
                    addonQuantityIncrement.focus();
                }
            };

            const closeQuantityModal = () => {
                if (pendingQuantityRow) {
                    const quantity = pendingQuantityRow.querySelector('.service-quantity');
                    if (!quantity.value) {
                        pendingQuantityRow.querySelector('.service-toggle').checked = false;
                        quantity.disabled = true;
                        pendingQuantityRow.querySelector('.service-quantity-trigger').disabled = true;
                    }
                    pendingQuantityRow.querySelector('.service-toggle').focus();
                }

                if (pendingAddonRow) {
                    const toggle = pendingAddonRow.querySelector('.addon-toggle');
                    if (!toggle.dataset.quantity) {
                        toggle.checked = false;
                        pendingAddonRow.querySelector('.addon-quantity').disabled = true;
                    }
                    pendingAddonRow.classList.toggle('is-selected', toggle.checked);
                    toggle.focus();
                }

                pendingQuantityRow = null;
                pendingAddonRow = null;
                quantityModal.classList.add('hidden');
                quantityModal.classList.remove('flex');
                modalState = null;
                update();
            };

            const confirmQuantity = () => {
                const enteredQuantity = Number.parseFloat(quantityModalInput.value);
                if (pendingAddonRow) {
                    if (!Number.isInteger(enteredQuantity) || enteredQuantity < 1) {
                        quantityModalError.classList.remove('hidden');
                        quantityModalInput.focus();
                        return;
                    }

                    const row = pendingAddonRow;
                    const toggle = row.querySelector('.addon-toggle');
                    const quantity = row.querySelector('.addon-quantity');
                    toggle.dataset.quantity = String(enteredQuantity);
                    toggle.checked = true;
                    quantity.value = String(enteredQuantity);
                    quantity.disabled = false;
                    row.classList.add('is-selected');
                    row.querySelector('.addon-price-display').textContent = `${enteredQuantity} × ${money(Number(toggle.dataset.price))}`;
                    row.querySelector('.addon-quantity-trigger').setAttribute(
                        'aria-label',
                        `Edit ${toggle.dataset.addonName} quantity, ${enteredQuantity}`,
                    );
                    pendingAddonRow = null;
                    quantityModal.classList.add('hidden');
                    quantityModal.classList.remove('flex');
                    modalState = null;
                    update();
                    row.querySelector('.addon-quantity-trigger').focus();
                    return;
                }

                const precision = precisionForMethod(pendingQuantityRow.dataset.method);
                const quantityValue = Number(enteredQuantity.toFixed(precision));
                const hasExcessPrecision = Math.abs(enteredQuantity - quantityValue) > Number.EPSILON * 10;
                if (!Number.isFinite(quantityValue)
                    || quantityValue <= 0
                    || hasExcessPrecision) {
                    quantityModalError.classList.remove('hidden');
                    quantityModalInput.focus();
                    return;
                }

                const row = pendingQuantityRow;
                const quantity = row.querySelector('.service-quantity');
                const trigger = row.querySelector('.service-quantity-trigger');
                const quantityDisplay = trigger.querySelector('.service-quantity-display');
                quantity.value = quantityValue;
                quantity.disabled = false;
                trigger.disabled = false;
                row.querySelector('.service-toggle').checked = true;
                const unit = unitForMethod(row.dataset.method);
                const formattedQuantity = formatQuantity(quantityValue, row.dataset.method);
                quantityDisplay.textContent = `${formattedQuantity} ${unit}`;
                trigger.setAttribute('aria-label', `Edit ${row.dataset.serviceName} quantity, ${formattedQuantity} ${unit}`);
                pendingQuantityRow = null;
                quantityModal.classList.add('hidden');
                quantityModal.classList.remove('flex');
                modalState = null;
                update();
                trigger.focus();
            };

            const update = () => {
                let subtotal = 0;
                let hasCompleteSelection = false;
                const breakdown = document.getElementById('summary-breakdown');
                breakdown.replaceChildren();
                let hasSelectedService = false;
                document.querySelectorAll('.service-row').forEach(row => {
                    const toggle = row.querySelector('.service-toggle');
                    const quantity = row.querySelector('.service-quantity');
                    quantity.disabled = !toggle.checked;
                    row.classList.toggle('is-selected', toggle.checked && quantity.value !== '');
                    if (!toggle.checked) return;
                    if (!hasSelectedService) {
                        const sectionLabel = document.createElement('p');
                        sectionLabel.className = 'text-xs font-bold uppercase tracking-wider text-blue-200';
                        sectionLabel.textContent = 'Services';
                        breakdown.append(sectionLabel);
                    }
                    hasSelectedService = true;
                    const qty = Number.parseFloat(quantity.value) || 0;
                    const rate = Number(row.dataset.rate);
                    const minimumWeight = Number(row.dataset.minimumWeight);
                    const minimumCharge = Number(row.dataset.minimumCharge);
                    const additionalCharge = Number(row.dataset.additionalCharge);
                    const method = row.dataset.method;
                    const unit = { per_kg: 'kg', per_piece: 'piece', per_load: 'kg', fixed: 'fixed price' }[method] || method;
                    const item = document.createElement('div');
                    item.className = 'ml-3 border-l border-white/20 pl-3';
                    const heading = document.createElement('div');
                    heading.className = 'flex justify-between gap-3 text-sm font-semibold';
                    const name = document.createElement('span');
                    name.className = 'min-w-0 truncate text-white';
                    name.textContent = row.dataset.serviceName;
                    const amount = document.createElement('span');
                    amount.className = 'shrink-0 text-white';

                    if (qty <= 0) {
                        row.dataset.lineTotal = '';
                        amount.textContent = '—';
                        heading.append(name, amount);
                        const hint = document.createElement('p');
                        hint.className = 'mt-1 text-xs text-blue-100';
                        hint.textContent = 'Enter a quantity to calculate this service.';
                        item.append(heading, hint);
                        breakdown.append(item);
                        return;
                    }
                    hasCompleteSelection = true;

                    const loadCapacity = Number(row.dataset.loadCapacityKg);
                    const billable = method === 'fixed'
                        ? 1
                        : (method === 'per_load'
                            ? Math.ceil(qty / loadCapacity)
                            : Math.max(qty, minimumWeight));
                    const calculated = method === 'fixed' ? rate : billable * rate;
                    const baseCharge = Math.max(calculated, minimumCharge);
                    const lineTotal = Math.round((baseCharge + additionalCharge) * 100) / 100;
                    row.dataset.lineTotal = lineTotal.toFixed(2);
                    subtotal += lineTotal;
                    amount.textContent = money(lineTotal);
                    heading.append(name, amount);

                    const details = document.createElement('p');
                    details.className = 'mt-1 text-xs text-blue-100';
                    details.textContent = method === 'fixed'
                        ? `Fixed price ${money(rate)}`
                        : (method === 'per_load'
                            ? `${qty} kg ÷ ${loadCapacity} kg per load · ${billable} loads × ${money(rate)}`
                            : `${qty} ${unit} × ${money(rate)}${billable > qty ? ` · billed at ${billable} ${unit} minimum` : ''}`);
                    item.append(heading, details);

                    if (minimumCharge > calculated) {
                        const minimumNote = document.createElement('p');
                        minimumNote.className = 'mt-1 text-xs text-blue-100';
                        minimumNote.textContent = `Minimum charge applied: ${money(minimumCharge)}`;
                        item.append(minimumNote);
                    }
                    if (additionalCharge > 0) {
                        const additionalNote = document.createElement('p');
                        additionalNote.className = 'mt-1 text-xs text-blue-100';
                        additionalNote.textContent = `Service additional charge: ${money(additionalCharge)}`;
                        item.append(additionalNote);
                    }
                    breakdown.append(item);
                });
                let addOnTotal = 0;
                let hasSelectedAddon = false;
                document.querySelectorAll('.addon-toggle:checked').forEach(toggle => {
                    const price = Number(toggle.dataset.price);
                    const quantity = Number(toggle.dataset.quantity) || 0;
                    if (quantity < 1) return;
                    hasCompleteSelection = true;
                    if (!hasSelectedAddon) {
                        const sectionLabel = document.createElement('p');
                        sectionLabel.className = 'pt-2 text-xs font-bold uppercase tracking-wider text-blue-200';
                        sectionLabel.textContent = 'Add-ons';
                        breakdown.append(sectionLabel);
                    }
                    hasSelectedAddon = true;
                    const lineTotal = price * quantity;
                    addOnTotal += lineTotal;
                    const item = document.createElement('div');
                    item.className = 'ml-3 border-l border-white/20 pl-3';
                    const heading = document.createElement('div');
                    heading.className = 'flex justify-between gap-3 text-sm font-semibold';
                    const name = document.createElement('span');
                    name.className = 'min-w-0 truncate text-white';
                    name.textContent = toggle.dataset.addonName;
                    const amount = document.createElement('span');
                    amount.className = 'shrink-0 text-white';
                    amount.textContent = money(lineTotal);
                    heading.append(name, amount);
                    const details = document.createElement('p');
                    details.className = 'mt-1 text-xs text-blue-100';
                    details.textContent = `${quantity} × ${money(price)}`;
                    item.append(heading, details);
                    breakdown.append(item);
                });
                if (!hasSelectedService && !hasSelectedAddon) {
                    const empty = document.createElement('p');
                    empty.className = 'text-sm text-blue-100';
                    empty.textContent = 'No services or add-ons selected yet.';
                    breakdown.append(empty);
                }
                const total = subtotal + addOnTotal;
                document.getElementById('summary-subtotal').textContent = money(subtotal);
                document.getElementById('summary-addons').textContent = money(addOnTotal);
                document.getElementById('summary-total').textContent = money(total);
                const isPaid = paymentStatus.value === 'paid';
                const paid = isPaid ? total : 0;
                const tendered = Number.parseFloat(cashReceivedInput.value) || 0;
                const change = isPaid && paymentMethod.value === 'cash' ? Math.max(0, tendered - total) : 0;
                document.getElementById('summary-paid').textContent = money(paid);
                document.getElementById('summary-balance').textContent = money(Math.max(0, total - paid));
                document.getElementById('summary-change').textContent = money(change);
                document.querySelectorAll('.tracking-submit').forEach(button => {
                    button.disabled = !hasCompleteSelection;
                });
                updatePaymentFields();
                refreshFloatingOrderSummary();
                saveDraft();
            };
            floatingSummaryButton.addEventListener('click', () => {
                if (floatingSummaryButton.classList.contains('is-collapsed')) {
                    floatingSummaryButton.classList.remove('is-collapsed');
                    floatingSummaryButton.setAttribute('aria-expanded', 'true');
                    floatingSummaryButton.setAttribute('aria-label', 'Open order summary');
                    floatingSummaryDetails.hidden = false;
                    return;
                }

                previousSummaryFocus = document.activeElement;
                floatingSummaryModal.scrollTop = 0;
                orderSummaryScrollArea.scrollTop = 0;
                lockPageScroll();
                floatingSummaryModal.showModal();
                floatingSummaryDone.focus();
            });
            floatingSummaryButton.addEventListener('blur', collapseFloatingSummaryButton);
            let summaryDragStartY = 0;
            let summaryDragStartScrollTop = 0;
            let summaryDragPointerId = null;
            let summaryDragMoved = false;
            orderSummaryScrollArea.addEventListener('pointerdown', event => {
                if (
                    event.pointerType !== 'mouse'
                    || event.button !== 0
                    || event.target.closest('button, input, select, textarea, a')
                ) {
                    return;
                }

                summaryDragStartY = event.clientY;
                summaryDragStartScrollTop = orderSummaryScrollArea.scrollTop;
                summaryDragPointerId = event.pointerId;
                summaryDragMoved = false;
            });
            document.addEventListener('pointermove', event => {
                if (event.pointerId !== summaryDragPointerId) {
                    return;
                }

                const dragDistance = event.clientY - summaryDragStartY;
                if (!summaryDragMoved && Math.abs(dragDistance) < 4) {
                    return;
                }

                summaryDragMoved = true;
                orderSummaryScrollArea.classList.add('is-dragging');
                orderSummaryScrollArea.scrollTop = summaryDragStartScrollTop - dragDistance;
                event.preventDefault();
            });
            const stopSummaryDrag = event => {
                if (event.pointerId !== summaryDragPointerId) {
                    return;
                }

                summaryDragPointerId = null;
                orderSummaryScrollArea.classList.remove('is-dragging');
            };
            document.addEventListener('pointerup', stopSummaryDrag);
            document.addEventListener('pointercancel', stopSummaryDrag);
            document.getElementById('floating-order-summary-checkout').addEventListener('click', () => {
                closeFloatingSummary();
            });
            const closeFloatingSummary = () => floatingSummaryModal.close();
            floatingSummaryClose.addEventListener('click', closeFloatingSummary);
            floatingSummaryDone.addEventListener('click', closeFloatingSummary);
            floatingSummaryModal.addEventListener('close', () => {
                unlockPageScroll();
                if (previousSummaryFocus instanceof HTMLElement) {
                    previousSummaryFocus.focus();
                }
            });
            floatingSummaryModal.addEventListener('click', event => {
                if (event.target === floatingSummaryModal) {
                    closeFloatingSummary();
                }
            });
            document.querySelectorAll('.pos-section-toggle').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    setSectionCollapsed(
                        toggle.dataset.posSection,
                        toggle.getAttribute('aria-expanded') === 'true',
                    );
                });
            });
            document.addEventListener('click', event => {
                if (!(event.target instanceof Element)) {
                    return;
                }

                const link = event.target.closest('a[href]');
                if (
                    !link
                    || link.hasAttribute('download')
                    || (link.target && link.target !== '_self')
                    || event.ctrlKey
                    || event.metaKey
                    || event.shiftKey
                    || event.altKey
                ) {
                    return;
                }

                const destination = new URL(link.href, window.location.href);
                if (destination.origin === window.location.origin && destination.pathname !== window.location.pathname) {
                    clearSavedDraft();
                }
            }, true);
            document.querySelectorAll('.service-toggle').forEach(toggle => {
                toggle.addEventListener('change', () => {
                    const row = toggle.closest('.service-row');
                    const trigger = row.querySelector('.service-quantity-trigger');
                    if (toggle.checked) {
                        setSectionCollapsed('addons', true);
                        trigger.disabled = false;
                        update();
                        openQuantityModal(row);
                        return;
                    }

                    const quantity = row.querySelector('.service-quantity');
                    quantity.value = '';
                    quantity.disabled = true;
                    trigger.disabled = true;
                    if (row.dataset.method === 'per_load') {
                        trigger.querySelector('.service-quantity-display').textContent =
                            `${money(Number(row.dataset.rate))} / load`;
                        trigger.querySelector('.service-minimum-display').textContent =
                            `${Number(row.dataset.loadCapacityKg).toFixed(2)} kg per load`;
                    } else {
                        trigger.textContent = 'Enter quantity';
                    }
                    update();
                });
            });
            document.querySelectorAll('.service-quantity-trigger').forEach(trigger => {
                trigger.addEventListener('click', () => openQuantityModal(trigger.closest('.service-row')));
            });
            document.querySelectorAll('.addon-toggle').forEach(toggle => {
                toggle.addEventListener('change', () => {
                    const row = toggle.closest('.addon-row');
                    if (toggle.checked) {
                        setSectionCollapsed('services', false);
                        update();
                        openAddonQuantityModal(row);
                        return;
                    }

                    toggle.dataset.quantity = '';
                    row.querySelector('.addon-quantity').value = '';
                    row.querySelector('.addon-quantity').disabled = true;
                    row.querySelector('.addon-price-display').textContent = `+${money(Number(toggle.dataset.price))}`;
                    row.querySelector('.addon-quantity-trigger').setAttribute('aria-label', `Set ${toggle.dataset.addonName} quantity`);
                    row.classList.remove('is-selected');
                    update();
                });
                const row = toggle.closest('.addon-row');
                row.classList.toggle('is-selected', toggle.checked && Boolean(toggle.dataset.quantity));
                row.querySelector('.addon-quantity').disabled = !toggle.dataset.quantity;
                if (toggle.dataset.quantity) {
                    row.querySelector('.addon-price-display').textContent = `${toggle.dataset.quantity} × ${money(Number(toggle.dataset.price))}`;
                }
            });
            document.querySelectorAll('.addon-quantity-trigger').forEach(trigger => {
                trigger.addEventListener('click', () => openAddonQuantityModal(trigger.closest('.addon-row')));
            });
            paymentStatus.addEventListener('change', update);
            paymentMethod.addEventListener('change', update);
            floatingPaymentStatus.addEventListener('change', () => {
                paymentStatus.value = floatingPaymentStatus.value;
                update();
            });
            floatingPaymentMethod.addEventListener('change', () => {
                paymentMethod.value = floatingPaymentMethod.value;
                update();
            });
            floatingPaymentReference.addEventListener('input', () => {
                document.getElementById('payment_reference').value = floatingPaymentReference.value;
                saveDraft();
            });
            cashReceivedInput.addEventListener('input', () => {
                if (modalState?.type === 'cash-payment') {
                    modalState.cashReceived = cashReceivedInput.value;
                }
                updatePaymentFields();
                update();
            });
            form.querySelectorAll('#customer_name, #customer_contact, [name="instructions"], #payment_reference').forEach(field => {
                const persistField = () => {
                    if (field.id === 'payment_reference') {
                        floatingPaymentReference.value = field.value;
                    }
                    saveDraft();
                };
                field.addEventListener('input', persistField);
                field.addEventListener('change', persistField);
            });
            form.addEventListener('submit', event => {
                if (paymentStatus.value === 'paid' && paymentMethod.value === 'cash' && !confirmedCashPayment) {
                    event.preventDefault();
                    openCashModal();
                    return;
                }

                if (!confirmedOrder) {
                    event.preventDefault();
                    openOrderConfirmation();
                }
            });
            cashModalConfirm.addEventListener('click', () => {
                const tendered = Number.parseFloat(cashReceivedInput.value) || 0;
                const total = calculatedTotal();
                if (tendered < total) {
                    updatePaymentFields();
                    return;
                }
                cashTendered.value = tendered.toFixed(2);
                confirmedCashPayment = true;
                closeCashModal();
                form.requestSubmit();
            });
            orderConfirmationConfirm.addEventListener('click', () => {
                confirmedOrder = true;
                closeOrderConfirmation();
                form.requestSubmit();
            });
            document.getElementById('order-confirmation-close').addEventListener('click', closeOrderConfirmation);
            document.getElementById('order-confirmation-cancel').addEventListener('click', closeOrderConfirmation);
            document.getElementById('service-quantity-close').addEventListener('click', closeQuantityModal);
            document.getElementById('service-quantity-cancel').addEventListener('click', closeQuantityModal);
            document.getElementById('service-quantity-confirm').addEventListener('click', confirmQuantity);
            addonQuantityDecrement.addEventListener('click', () => {
                const current = Number.parseFloat(quantityModalInput.value);
                const step = Number.parseFloat(quantityModalInput.step) || 1;
                const minimum = Number.parseFloat(quantityModalInput.min) || step;
                if (Number.isFinite(current)) {
                    const precision = (quantityModalInput.step.split('.')[1] || '').length;
                    quantityModalInput.value = String(Math.max(minimum, Number((current - step).toFixed(precision))));
                }
                quantityModalError.classList.add('hidden');
                if (modalState) {
                    modalState.value = quantityModalInput.value;
                }
                saveDraft();
            });
            addonQuantityIncrement.addEventListener('click', () => {
                const current = Number.parseFloat(quantityModalInput.value);
                const step = Number.parseFloat(quantityModalInput.step) || 1;
                const minimum = Number.parseFloat(quantityModalInput.min) || step;
                const precision = (quantityModalInput.step.split('.')[1] || '').length;
                const maximum = Number.parseFloat(quantityModalInput.max);
                const nextValue = Number.isFinite(current) ? Number((current + step).toFixed(precision)) : minimum;
                quantityModalInput.value = String(Number.isFinite(maximum) && maximum > 0
                    ? Math.min(maximum, nextValue)
                    : nextValue);
                quantityModalError.classList.add('hidden');
                if (modalState) {
                    modalState.value = quantityModalInput.value;
                }
                saveDraft();
            });
            quantityModalInput.addEventListener('input', () => {
                if (modalState) {
                    modalState.value = quantityModalInput.value;
                }
                saveDraft();
            });
            quantityModalInput.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    confirmQuantity();
                }
            });
            quantityModal.addEventListener('click', event => {
                if (event.target === quantityModal) closeQuantityModal();
            });
            orderConfirmationModal.addEventListener('click', event => {
                if (event.target === orderConfirmationModal) closeOrderConfirmation();
            });
            document.getElementById('cash-modal-close').addEventListener('click', closeCashModal);
            document.getElementById('cash-modal-cancel').addEventListener('click', closeCashModal);
            modal.addEventListener('click', event => {
                if (event.target === modal) closeCashModal();
            });
            restoreDraft();
            update();
            restoreOpenModal();
        })();
    </script>
</x-app-layout>
