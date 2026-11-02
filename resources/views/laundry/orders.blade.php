<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#002c76]">Laundry workflow</p>
            <h2 class="mt-1 text-xl font-bold text-[#002c76]">Laundry Orders</h2>
        </div>
    </x-slot>

    <div class="tablet-safe mx-auto max-w-7xl space-y-5 p-4 sm:p-6">
        <form class="flex flex-col gap-2 sm:flex-row">
            <input type="hidden" name="claim_status" value="{{ $claimStatus }}">
            <input name="search" value="{{ request('search') }}" placeholder="Search order code or customer" class="w-full rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:max-w-sm">
            @if($claimStatus === 'claimed')
                <label for="released-date-filter" class="sr-only">Released date</label>
                <input id="released-date-filter" type="date" name="released_date" value="{{ $releasedDate }}" class="w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76] sm:w-auto">
            @endif
            <button class="rounded-xl bg-[#002c76] px-5 py-2 font-semibold text-white">Search</button>
            @if(request('search') || $releasedDate)
                <a href="{{ route('orders.index', ['claim_status' => $claimStatus]) }}" class="rounded-xl border border-[#002c76] px-5 py-2 text-center font-semibold text-[#002c76]">Clear</a>
            @endif
        </form>

        <nav class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1.5" aria-label="Order claim status">
            <a href="{{ route('orders.index', array_filter(['claim_status' => 'unclaimed', 'search' => request('search')])) }}"
                @if($claimStatus === 'unclaimed') aria-current="page" @endif
                class="rounded-xl px-4 py-3 text-center text-sm font-semibold transition {{ $claimStatus === 'unclaimed' ? 'bg-[#002c76] text-white shadow-sm' : 'text-[#002c76] hover:bg-white' }}">
                Unclaimed
            </a>
            <a href="{{ route('orders.index', array_filter(['claim_status' => 'claimed', 'search' => request('search'), 'released_date' => $releasedDate])) }}"
                @if($claimStatus === 'claimed') aria-current="page" @endif
                class="rounded-xl px-4 py-3 text-center text-sm font-semibold transition {{ $claimStatus === 'claimed' ? 'bg-[#002c76] text-white shadow-sm' : 'text-[#002c76] hover:bg-white' }}">
                Claimed
            </a>
        </nav>

        @if($canUpdateStatus)
            <section class="rounded-2xl border border-[#002c76]/15 bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-4">
                <div>
                    <h3 class="font-bold text-[#002c76]">Update with QR scan</h3>
                    <p class="mt-1 text-sm text-[#002c76]/70">Scan an order QR code to move it to the next service stage.</p>
                </div>
                <button id="open-order-qr-scanner" type="button" data-endpoint="{{ route('orders.scan-status') }}" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#002c76] px-5 py-3 font-semibold text-white transition hover:bg-[#0645a2] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#002c76] focus-visible:ring-offset-2 sm:mt-0 sm:w-auto">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2"/><path d="M8 8h3v3H8zM14 8h2M14 11h2M8 14h3v3H8zM14 14h3v3h-3z"/></svg>
                    Scan order QR
                </button>
            </section>

            <div id="order-qr-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-[#002c76]/70 p-4" role="dialog" aria-modal="true" aria-labelledby="order-qr-title">
                <section class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="order-qr-title" class="text-lg font-bold text-[#002c76]">Scan order QR code</h2>
                            <p class="mt-1 text-sm text-[#002c76]/70">Each scan advances one step: On Queue, Washing, Drying, Folding, then Ready for Pick up.</p>
                        </div>
                        <button id="close-order-qr-scanner" type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-[#002c76] hover:bg-[#002c76]/10" aria-label="Close QR scanner">&times;</button>
                    </div>

                    <div id="order-qr-reader" class="mt-4 overflow-hidden rounded-xl border border-[#002c76]/15"></div>
                    <div class="mt-3 flex justify-end">
                        <button id="flip-order-qr-camera" type="button" class="hidden inline-flex items-center gap-2 rounded-xl border border-[#002c76]/20 px-4 py-2 text-sm font-semibold text-[#002c76] transition hover:bg-[#002c76]/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#002c76]" aria-label="Flip camera">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 7h-3l-2-2H9L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><circle cx="12" cy="13" r="3"/></svg>
                            Flip camera
                        </button>
                    </div>
                    <p id="order-qr-message" class="mt-3 min-h-6 text-sm font-medium text-[#002c76]" role="status" aria-live="polite">Allow camera access to start scanning.</p>
                    <div class="mt-4 flex justify-end">
                        <button id="restart-order-qr-scanner" type="button" class="hidden rounded-xl bg-[#002c76] px-5 py-2.5 font-semibold text-white hover:bg-[#0645a2]">Scan next order</button>
                    </div>
                </section>
            </div>
        @endif

        <div class="space-y-3">
            @forelse($orders as $order)
                @php
                    $customerName = $order->customer?->name ?? 'Walk-in customer';
                    $customerNameToken = preg_split('/\s+/', trim($customerName), 2)[0] ?? 'Walk-in';
                @endphp
                <article class="order-list-item {{ $order->payment_status === 'unpaid' ? 'order-list-item--unpaid' : '' }} rounded-2xl border bg-white p-4 shadow-sm sm:p-5" data-order-id="{{ $order->id }}">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(15rem,1.2fr)] md:items-center">
                        <div class="min-w-0">
                            <span class="order-field-label">Order code</span>
                            <a href="{{ route('orders.show', $order) }}" class="order-code-link mt-1 block truncate">{{ $order->order_code }}</a>
                            <span class="order-field-label mt-3">Customer name</span>
                            <p title="{{ $customerName }}" class="order-customer-name mt-1 truncate">
                                <span class="sm:hidden">{{ $customerNameToken }}</span>
                                <span class="hidden sm:inline">{{ $customerName }}</span>
                            </p>
                        </div>

                        <div class="space-y-1 text-sm">
                            <span class="order-field-label">Received</span>
                            <span class="order-field-value">{{ $order->received_at?->format('M d, Y h:i A') ?? '—' }}</span>
                        </div>

                        <div class="space-y-1 text-sm">
                            <span class="order-field-label">Total</span>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="order-total-value">₱{{ number_format($order->total_amount, 2) }}</span>
                                <span class="payment-status-badge rounded-full border px-2 py-1 text-xs {{ $order->payment_status === 'unpaid' ? 'payment-status-badge--unpaid font-semibold' : ($order->payment_status === 'paid' ? 'payment-status-badge--paid' : 'payment-status-badge--partial') }}">{{ str_replace('_', ' ', $order->payment_status) }}</span>
                            </div>
                        </div>

                        @if($canUpdateStatus)
                            <form method="POST" action="{{ route('orders.status', $order) }}" class="order-status-form flex items-end gap-2 border-t pt-3 md:border-0 md:pt-0" data-payment-status="{{ $order->payment_status }}" data-outstanding-balance="{{ $order->outstanding_balance }}">
                                @csrf
                                @method('PATCH')
                                <div class="min-w-0 flex-1">
                                    <label for="laundry-status-{{ $order->id }}" class="order-field-label mb-1">Service status</label>
                                    <select id="laundry-status-{{ $order->id }}" name="laundry_status" class="order-status-select w-full rounded-xl text-sm">
                                        @foreach($availableStatuses as $status => $label)
                                            <option value="{{ $status }}" @selected($order->laundry_status === $status || ($order->laundry_status === 'ironing' && $status === 'drying') || ($order->laundry_status === 'cancelled' && $status === 'received'))>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="order-status-button shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold text-white">Update</button>
                            </form>
                        @else
                            <div class="space-y-1 border-t border-[#002c76]/10 pt-3 md:border-0 md:pt-0">
                                <span class="order-field-label">Service status</span>
                                <span class="workflow-status workflow-status--{{ $order->laundry_status === 'received' || $order->laundry_status === 'cancelled' ? 'pending' : (in_array($order->laundry_status, ['washing', 'drying', 'ironing', 'folding'], true) ? 'ongoing' : ($order->laundry_status === 'ready_for_pickup' ? 'ready' : 'released')) }}">
                                    {{ $availableStatuses[$order->laundry_status] ?? ($order->laundry_status === 'ironing' ? 'Drying' : ($order->laundry_status === 'released' ? 'Ready for Pick up' : ucfirst(str_replace('_', ' ', $order->laundry_status)))) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[#002c76]/20 bg-white p-8 text-center text-sm text-[#002c76]/65">No orders found.</div>
            @endforelse
        </div>

        @if($orders->hasPages())
            <div class="border-t border-[#002c76]/10 pt-4 text-sm">
                {{ $orders->links() }}
            </div>
        @endif

        @if($canUpdateStatus)
            <dialog id="release-payment-modal" class="release-payment-modal w-[calc(100%-2rem)] max-w-md rounded-3xl border-0 bg-white p-0 text-[#002c76] shadow-2xl backdrop:bg-[#002c76]/60" aria-labelledby="release-payment-title">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/65">Payment required</p>
                            <h2 id="release-payment-title" class="mt-1 text-xl font-bold">Pay before release</h2>
                        </div>
                        <button id="release-payment-close" type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-[#002c76] hover:bg-[#002c76]/10" aria-label="Close payment dialog">&times;</button>
                    </div>
                    <p class="mt-4 text-sm text-slate-600">This order is unpaid. Record the full balance before marking it as released.</p>
                    <div class="mt-5 rounded-2xl bg-[#002c76] p-4 text-white">
                        <span class="block text-sm text-white/80">Balance due</span>
                        <strong id="release-payment-balance" class="mt-1 block text-3xl">₱0.00</strong>
                    </div>
                    <label for="release-payment-method" class="mt-5 block text-sm font-medium text-slate-600">Payment method</label>
                    <select id="release-payment-method" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="other">Other</option>
                    </select>
                    <div id="release-cash-field" class="mt-4">
                        <label for="release-cash-tendered" class="block text-sm font-medium text-slate-600">Amount received</label>
                        <input id="release-cash-tendered" type="number" min="0.01" step="0.01" inputmode="numeric" placeholder="Enter cash received" class="mt-2 w-full rounded-xl border-[#002c76]/25 text-lg text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]">
                    </div>
                    <div id="release-cash-change-panel" class="mt-4 flex items-center justify-between rounded-xl border border-[#002c76]/15 px-4 py-3">
                        <span id="release-cash-change-label" class="font-semibold">Change</span>
                        <strong id="release-cash-change" class="text-xl" aria-live="polite" aria-atomic="true">₱0.00</strong>
                    </div>
                    <div id="release-reference-field" class="mt-4" hidden>
                        <label for="release-payment-reference" class="block text-sm font-medium text-slate-600">Payment reference (optional)</label>
                        <input id="release-payment-reference" maxlength="100" class="mt-1 w-full rounded-xl border-slate-200 focus:border-[#002c76] focus:ring-[#002c76]">
                    </div>
                    <p id="release-payment-error" class="mt-2 hidden text-sm font-semibold text-red-700" role="alert"></p>
                    <div class="mt-6 flex gap-3">
                        <button id="release-payment-cancel" type="button" class="flex-1 rounded-xl border border-[#002c76] px-4 py-3 font-semibold text-[#002c76] hover:bg-[#002c76]/5">Cancel</button>
                        <button id="release-payment-confirm" type="button" class="flex-1 rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white hover:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50">Pay and release</button>
                    </div>
                </div>
            </dialog>
            <script>
                (() => {
                    const modal = document.getElementById('release-payment-modal');
                    const method = document.getElementById('release-payment-method');
                    const cashField = document.getElementById('release-cash-field');
                    const cashTendered = document.getElementById('release-cash-tendered');
                    const cashChange = document.getElementById('release-cash-change');
                    const cashChangeLabel = document.getElementById('release-cash-change-label');
                    const cashChangePanel = document.getElementById('release-cash-change-panel');
                    const referenceField = document.getElementById('release-reference-field');
                    const reference = document.getElementById('release-payment-reference');
                    const balance = document.getElementById('release-payment-balance');
                    const error = document.getElementById('release-payment-error');
                    let pendingForm = null;
                    let submittingAfterPayment = false;

                    const updatePaymentFields = () => {
                        const isCash = method.value === 'cash';
                        cashField.hidden = !isCash;
                        referenceField.hidden = isCash;
                        const due = Number.parseFloat(pendingForm?.dataset.outstandingBalance ?? '') || 0;
                        const tendered = Number.parseFloat(cashTendered.value) || 0;
                        const difference = cashTendered.value === '' ? 0 : tendered - due;
                        cashChangeLabel.textContent = difference >= 0 ? 'Change' : 'Remaining due';
                        cashChange.textContent = `₱${Math.abs(difference).toFixed(2)}`;
                        cashChangePanel.hidden = !isCash;
                        document.getElementById('release-payment-confirm').disabled =
                            isCash && (cashTendered.value === '' || tendered < due || tendered <= 0);
                    };

                    document.querySelectorAll('.order-status-form').forEach(form => {
                        form.addEventListener('submit', event => {
                            const selectedStatus = form.querySelector('[name="laundry_status"]').value;
                            if (submittingAfterPayment || selectedStatus !== 'released' || form.dataset.paymentStatus !== 'unpaid') {
                                return;
                            }

                            event.preventDefault();
                            pendingForm = form;
                            const due = Number.parseFloat(form.dataset.outstandingBalance) || 0;
                            balance.textContent = `₱${due.toFixed(2)}`;
                            cashTendered.value = '';
                            reference.value = '';
                            error.textContent = '';
                            error.classList.add('hidden');
                            method.value = 'cash';
                            updatePaymentFields();
                            modal.showModal();
                        });
                    });

                    method.addEventListener('change', updatePaymentFields);
                    cashTendered.addEventListener('input', updatePaymentFields);
                    document.getElementById('release-payment-close').addEventListener('click', () => modal.close());
                    document.getElementById('release-payment-cancel').addEventListener('click', () => modal.close());
                    document.getElementById('release-payment-confirm').addEventListener('click', () => {
                        if (!pendingForm) {
                            return;
                        }

                        const due = Number.parseFloat(pendingForm.dataset.outstandingBalance) || 0;
                        const tendered = Number.parseFloat(cashTendered.value) || 0;
                        if (method.value === 'cash' && tendered < due) {
                            error.textContent = 'Amount received must cover the full order balance.';
                            error.classList.remove('hidden');
                            cashTendered.focus();
                            return;
                        }

                        pendingForm.querySelectorAll('[data-release-payment-field]').forEach(field => field.remove());
                        const addField = (name, value) => {
                            const field = document.createElement('input');
                            field.type = 'hidden';
                            field.name = name;
                            field.value = value;
                            field.dataset.releasePaymentField = 'true';
                            pendingForm.append(field);
                        };

                        addField('payment_method', method.value);
                        if (method.value === 'cash') {
                            addField('cash_tendered', tendered.toFixed(2));
                        } else if (reference.value.trim() !== '') {
                            addField('reference_number', reference.value.trim());
                        }

                        submittingAfterPayment = true;
                        modal.close();
                        pendingForm.requestSubmit();
                    });
                    modal.addEventListener('close', () => {
                        pendingForm = null;
                        submittingAfterPayment = false;
                    });
                })();
            </script>
        @endif
    </div>
</x-app-layout>
@if($canUpdateStatus)
    @vite('resources/js/order-scanner.js')
@endif
