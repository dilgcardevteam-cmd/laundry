@php
    $nextOrderCode = $nextOrderCode ?? \App\Models\LaundryOrder::nextOrderCode();
    $customers = $customers ?? \App\Models\Customer::orderBy('name')->get(['id', 'code', 'name', 'contact_number']);
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

        <form id="order-form" method="POST" action="{{ route('orders.store') }}" target="_blank" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            @csrf
            <section class="bubble laundry-card p-5 sm:p-7">
                <div class="relative z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-100 bg-blue-50/70 p-4 text-sm text-[#002c76]">
                    <div class="flex flex-wrap items-center gap-2">
                        <strong>Service code (laundry code):</strong>
                        <span class="rounded-lg bg-blue-100 px-2.5 py-1 font-mono font-bold tracking-wider text-[#002c76]">{{ $nextOrderCode }}</span>
                    </div>
                    <span class="text-xs text-blue-600/80">Generated automatically after you save this order</span>
                </div>

                <div class="relative z-10 mt-7">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#002c76]">Customer info</p>
                            <h3 class="mt-1 text-lg font-bold text-slate-800">Customer details</h3>
                        </div>
                        <span class="text-xs text-slate-500">Leave blank for Walk-in customer</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-slate-700">Customer name</label>
                            <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" list="customers-list" autocomplete="off" placeholder="e.g. Maria Santos" class="mt-1 w-full rounded-xl border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-[#002c76] focus:ring-[#002c76]">
                            <datalist id="customers-list">
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->name }}">{{ $cust->code }} @if($cust->contact_number) · {{ $cust->contact_number }} @endif</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label for="customer_contact" class="block text-sm font-medium text-slate-700">Contact number (optional)</label>
                            <input id="customer_contact" name="customer_contact" value="{{ old('customer_contact') }}" placeholder="e.g. 0917 123 4567" class="mt-1 w-full rounded-xl border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-[#002c76] focus:ring-[#002c76]">
                        </div>
                    </div>
                </div>

                <div class="relative z-10 mt-7">
                    <div class="flex items-end justify-between gap-3">
                        <div><p class="text-xs font-semibold uppercase tracking-wider text-[#002c76]">Choose services</p><h3 class="mt-1 text-lg font-bold text-slate-800">Laundry items</h3></div>
                        <span class="text-xs text-slate-500">Select a service and enter quantity</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach($services as $service)
                            <div class="service-row grid gap-3 rounded-2xl border border-slate-100 bg-white p-4 transition hover:border-blue-200 hover:shadow-sm sm:grid-cols-[auto_minmax(0,1fr)_9rem]" data-service-name="{{ $service->name }}" data-rate="{{ $service->rate }}" data-method="{{ $service->pricing_method }}" data-minimum-weight="{{ $service->minimum_weight ?? 0 }}" data-minimum-charge="{{ $service->minimum_charge ?? 0 }}" data-additional-charge="{{ $service->additional_charge ?? 0 }}">
                                <div class="flex items-center">
                                    <input id="toggle-{{ $service->id }}" class="service-toggle h-5 w-5 rounded border-slate-300 text-[#002c76] focus:ring-[#002c76]" type="checkbox" name="services[{{ $loop->index }}][service_id]" value="{{ $service->id }}">
                                </div>
                                <label class="cursor-pointer" for="toggle-{{ $service->id }}">
                                    <span class="block font-semibold text-slate-800">{{ $service->name }}</span>
                                    <span class="mt-1 block text-sm text-slate-500">₱{{ number_format($service->rate, 2) }} / {{ str_replace(['per_', '_'], ['', ' '], $service->pricing_method) }} @if($service->minimum_weight) · min {{ $service->minimum_weight }} kg @endif</span>
                                </label>
                                <input id="service-{{ $service->id }}" class="service-quantity w-full rounded-xl border-slate-200 bg-slate-50 text-right focus:border-[#002c76] focus:ring-[#002c76]" type="number" step="0.001" min="0.001" name="services[{{ $loop->index }}][quantity]" placeholder="Qty / kg" disabled>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative z-10 mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="payment_status" class="text-sm font-medium text-slate-600">Payment status</label>
                        <select id="payment_status" name="payment_status" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div id="payment-method-field" hidden>
                        <label for="payment_method" class="text-sm font-medium text-slate-600">Payment method</label>
                        <select id="payment_method" name="payment_method" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>
                    <div id="payment-reference-field" hidden class="sm:col-span-2">
                        <label for="payment_reference" class="text-sm font-medium text-slate-600">GCash reference (optional)</label>
                        <input id="payment_reference" name="payment_reference" placeholder="Enter GCash reference" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                    </div>
                </div>
                <input id="cash_tendered" name="cash_tendered" type="hidden" value="">
                <input name="amount_paid" type="hidden" value="0">
                <textarea name="instructions" placeholder="Laundry instructions or remarks (optional)" class="relative z-10 mt-4 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]"></textarea>
                @if($errors->any())<ul class="relative z-10 mt-4 text-sm text-red-600">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                <button type="submit" class="relative z-10 mt-6 w-full rounded-xl bg-[#002c76] px-5 py-3 font-semibold text-white shadow-lg shadow-blue-900/20 transition hover:bg-blue-900">Create order</button>
            </section>

            <aside class="h-fit rounded-3xl bg-[#002c76] p-5 text-white shadow-xl shadow-blue-900/20 lg:sticky lg:top-6 sm:p-7">
                <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-200">Order summary</p><h3 class="mt-1 text-2xl font-bold">Your total</h3></div>
                <div class="mt-6 border-b border-white/15 pb-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-blue-200">Service breakdown</h4>
                    <div id="summary-breakdown" class="mt-3 space-y-3" aria-live="polite">
                        <p class="text-sm text-blue-100">No services selected yet.</p>
                    </div>
                </div>
                <div class="mt-8 space-y-4 border-b border-white/15 pb-5 text-sm">
                    <div class="flex justify-between"><span class="text-blue-100">Subtotal</span><strong id="summary-subtotal">₱0.00</strong></div>
                </div>
                <div class="mt-5 flex items-end justify-between"><span class="text-blue-100">Total</span><strong id="summary-total" class="text-3xl">₱0.00</strong></div>
                <div class="mt-6 space-y-3 rounded-2xl bg-white/10 p-4 text-sm"><div class="flex justify-between"><span class="text-blue-100">Amount paid</span><strong id="summary-paid">₱0.00</strong></div><div class="flex justify-between"><span class="text-blue-100">Balance</span><strong id="summary-balance">₱0.00</strong></div><div class="flex justify-between"><span class="text-blue-100">Change</span><strong id="summary-change">₱0.00</strong></div></div>
                <p class="mt-5 text-center text-xs text-blue-200">Prices are recalculated securely on the server when the order is saved.</p>
            </aside>

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
                        <strong id="cash-modal-change" class="text-xl">₱0.00</strong>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button id="cash-modal-cancel" type="button" class="flex-1 rounded-xl border border-[#002c76] px-4 py-3 font-semibold text-[#002c76] hover:bg-[#002c76]/5">Cancel</button>
                        <button id="cash-modal-confirm" type="button" class="flex-1 rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Confirm &amp; print</button>
                    </div>
                </div>
            </div>
        </form>
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
            let confirmedCashPayment = false;

            const calculatedTotal = () => {
                const subtotal = Number(document.getElementById('summary-subtotal').textContent.replace(/[^\d.]/g, '')) || 0;

                return subtotal;
            };

            const updatePaymentFields = () => {
                const isPaid = paymentStatus.value === 'paid';
                paymentMethodField.hidden = !isPaid;
                paymentReferenceField.hidden = !isPaid || paymentMethod.value !== 'gcash';
                const total = calculatedTotal();
                const tendered = Number.parseFloat(cashReceivedInput.value) || 0;
                cashModalTotal.textContent = money(total);
                cashModalChange.textContent = money(Math.max(0, tendered - total));
                cashModalConfirm.disabled = tendered < total;
                cashModalError.classList.toggle('hidden', tendered >= total || cashReceivedInput.value === '');
            };

            const openCashModal = () => {
                const total = calculatedTotal();
                cashModalTotal.textContent = money(total);
                cashReceivedInput.value = '';
                cashModalChange.textContent = money(0);
                cashModalError.classList.add('hidden');
                cashModalConfirm.disabled = total > 0;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                cashReceivedInput.focus();
            };

            const closeCashModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            const update = () => {
                let subtotal = 0;
                const breakdown = document.getElementById('summary-breakdown');
                breakdown.replaceChildren();
                let hasSelectedService = false;
                document.querySelectorAll('.service-row').forEach(row => {
                    const toggle = row.querySelector('.service-toggle');
                    const quantity = row.querySelector('.service-quantity');
                    quantity.disabled = !toggle.checked;
                    if (!toggle.checked) return;
                    hasSelectedService = true;
                    const qty = Number.parseFloat(quantity.value) || 0;
                    const rate = Number(row.dataset.rate);
                    const minimumWeight = Number(row.dataset.minimumWeight);
                    const minimumCharge = Number(row.dataset.minimumCharge);
                    const additionalCharge = Number(row.dataset.additionalCharge);
                    const method = row.dataset.method;
                    const unit = { per_kg: 'kg', per_piece: 'piece', per_load: 'load', fixed: 'fixed price' }[method] || method;
                    const item = document.createElement('div');
                    const heading = document.createElement('div');
                    heading.className = 'flex justify-between gap-3 text-sm font-semibold';
                    const name = document.createElement('span');
                    name.className = 'min-w-0 truncate text-white';
                    name.textContent = row.dataset.serviceName;
                    const amount = document.createElement('span');
                    amount.className = 'shrink-0 text-white';

                    if (qty <= 0) {
                        amount.textContent = '—';
                        heading.append(name, amount);
                        const hint = document.createElement('p');
                        hint.className = 'mt-1 text-xs text-blue-100';
                        hint.textContent = 'Enter a quantity to calculate this service.';
                        item.append(heading, hint);
                        breakdown.append(item);
                        return;
                    }

                    const billable = method === 'fixed' ? 1 : Math.max(qty, minimumWeight);
                    const calculated = method === 'fixed' ? rate : billable * rate;
                    const baseCharge = Math.max(calculated, minimumCharge);
                    const lineTotal = Math.round((baseCharge + additionalCharge) * 100) / 100;
                    subtotal += lineTotal;
                    amount.textContent = money(lineTotal);
                    heading.append(name, amount);

                    const details = document.createElement('p');
                    details.className = 'mt-1 text-xs text-blue-100';
                    details.textContent = method === 'fixed'
                        ? `Fixed price ${money(rate)}`
                        : `${qty} ${unit} × ${money(rate)}${billable > qty ? ` · billed at ${billable} ${unit} minimum` : ''}`;
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
                if (!hasSelectedService) {
                    const empty = document.createElement('p');
                    empty.className = 'text-sm text-blue-100';
                    empty.textContent = 'No services selected yet.';
                    breakdown.append(empty);
                }
                const total = subtotal;
                document.getElementById('summary-subtotal').textContent = money(subtotal);
                document.getElementById('summary-total').textContent = money(total);
                const isPaid = paymentStatus.value === 'paid';
                const paid = isPaid ? total : 0;
                const tendered = Number.parseFloat(cashReceivedInput.value) || 0;
                const change = isPaid && paymentMethod.value === 'cash' ? Math.max(0, tendered - total) : 0;
                document.getElementById('summary-paid').textContent = money(paid);
                document.getElementById('summary-balance').textContent = money(Math.max(0, total - paid));
                document.getElementById('summary-change').textContent = money(change);
                updatePaymentFields();
            };
            document.querySelectorAll('.service-toggle, .service-quantity').forEach(input => input.addEventListener('input', update));
            paymentStatus.addEventListener('change', update);
            paymentMethod.addEventListener('change', update);
            cashReceivedInput.addEventListener('input', () => {
                updatePaymentFields();
                update();
            });
            form.addEventListener('submit', event => {
                if (paymentStatus.value === 'paid' && paymentMethod.value === 'cash' && !confirmedCashPayment) {
                    event.preventDefault();
                    openCashModal();
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
            document.getElementById('cash-modal-close').addEventListener('click', closeCashModal);
            document.getElementById('cash-modal-cancel').addEventListener('click', closeCashModal);
            modal.addEventListener('click', event => {
                if (event.target === modal) closeCashModal();
            });
            update();
        })();
    </script>
</x-app-layout>
