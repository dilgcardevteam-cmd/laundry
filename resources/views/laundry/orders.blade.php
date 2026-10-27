<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#002c76]">Laundry workflow</p>
            <h2 class="mt-1 text-xl font-bold text-[#002c76]">Laundry Orders</h2>
        </div>
    </x-slot>

    <div class="tablet-safe mx-auto max-w-7xl space-y-5 p-4 sm:p-6">
        <form class="flex flex-col gap-2 sm:flex-row">
            <input name="search" value="{{ request('search') }}" placeholder="Search order code or customer" class="w-full rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:max-w-sm">
            <button class="rounded-xl bg-[#002c76] px-5 py-2 font-semibold text-white">Search</button>
            @if(request('search'))
                <a href="{{ route('orders.index') }}" class="rounded-xl border border-[#002c76] px-5 py-2 text-center font-semibold text-[#002c76]">Clear</a>
            @endif
        </form>

        @if($canUpdateStatus)
            <p class="text-sm text-[#002c76]/70">Drag orders between columns to change status. You can move them back or to any column.</p>
        @endif

        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($columns as $key => $column)
                <section class="order-drop-column workflow-column workflow-column--{{ $key }} min-w-0 rounded-2xl border bg-white p-3 transition-colors" data-drop-column="{{ $key }}" aria-labelledby="column-{{ $key }}">
                    <div class="mb-3 flex items-center justify-between gap-2 border-b border-[#002c76]/15 px-1 pb-3">
                        <h3 id="column-{{ $key }}" class="workflow-heading font-bold">{{ $column['title'] }}</h3>
                        <span class="workflow-count rounded-full px-2.5 py-1 text-xs font-semibold">{{ $column['orders']->total() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($column['orders'] as $order)
                            <article class="order-card rounded-xl border p-4 shadow-sm transition {{ $order->payment_status === 'unpaid' ? 'order-card--unpaid' : 'border-[#002c76]/15 bg-white' }}" @if($canUpdateStatus) draggable="true" data-status-form="status-form-{{ $order->id }}" @endif>
                                @if($canUpdateStatus)
                                    <form id="status-form-{{ $order->id }}" method="POST" action="{{ route('orders.status', $order) }}" class="hidden">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="laundry_status" value="">
                                    </form>
                                @endif
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        @if($canUpdateStatus)
                                            <button type="button" class="order-drag-handle shrink-0 rounded-lg p-2 text-[#002c76]/70 hover:bg-[#002c76]/10" aria-label="Drag order {{ $order->order_code }} to another status" title="Drag to change status">
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="8" cy="5" r="1.5"/><circle cx="16" cy="5" r="1.5"/><circle cx="8" cy="12" r="1.5"/><circle cx="16" cy="12" r="1.5"/><circle cx="8" cy="19" r="1.5"/><circle cx="16" cy="19" r="1.5"/></svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('orders.show', $order) }}" class="truncate font-bold text-[#002c76] hover:underline">{{ $order->order_code }}</a>
                                    </div>
                                </div>
                                <p class="mt-2 truncate text-sm font-medium text-[#002c76]">{{ $order->customer?->name ?? 'Walk-in customer' }}</p>
                                <div class="mt-3 flex items-center justify-between gap-2 text-sm">
                                    <span class="font-bold text-[#002c76]">₱{{ number_format($order->total_amount, 2) }}</span>
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <span class="payment-status-badge rounded-full border px-2 py-1 text-xs {{ $order->payment_status === 'unpaid' ? 'payment-status-badge--unpaid font-semibold' : 'border-[#002c76]/20 text-[#002c76]' }}">{{ str_replace('_', ' ', $order->payment_status) }}</span>
                                        <span class="workflow-status workflow-status--{{ $order->laundry_status === 'received' || $order->laundry_status === 'cancelled' ? 'pending' : (in_array($order->laundry_status, ['washing', 'drying', 'ironing', 'folding'], true) ? 'ongoing' : ($order->laundry_status === 'ready_for_pickup' ? 'ready' : 'released')) }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->laundry_status)) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-[#002c76]/10 pt-3 text-xs text-[#002c76]/70">
                                    <span>{{ $order->received_at?->format('M d, Y') }}</span>
                                    @if($order->expected_completion_at)
                                        <span>Due {{ $order->expected_completion_at->format('M d') }}</span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#002c76]/20 p-5 text-center text-sm text-[#002c76]/65">No orders</div>
                        @endforelse
                    </div>

                    @if($column['orders']->hasPages())
                        <div class="mt-3 border-t border-[#002c76]/10 pt-3 text-sm">
                            {{ $column['orders']->links() }}
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    @if($canUpdateStatus)
        <script>
            (() => {
                let draggedCard = null;
                const columns = document.querySelectorAll('.order-drop-column');
                const cards = document.querySelectorAll('.order-card[draggable="true"]');
                const columnStatuses = {
                    pending: 'received',
                    ongoing: 'washing',
                    ready: 'ready_for_pickup',
                    released: 'released',
                };
                const targetStatus = column => columnStatuses[column.dataset.dropColumn];
                let pointerDrag = null;

                const clearDropHighlights = () => {
                    columns.forEach(column => column.classList.remove('ring-2', 'ring-[#002c76]'));
                };

                const columnAtPoint = (x, y) => document.elementFromPoint(x, y)?.closest('.order-drop-column');

                cards.forEach(card => {
                    card.addEventListener('dragstart', event => {
                        draggedCard = card;
                        card.classList.add('opacity-50');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', card.dataset.statusForm);
                    });

                    card.addEventListener('dragend', () => {
                        card.classList.remove('opacity-50');
                        clearDropHighlights();
                        draggedCard = null;
                    });

                    const handle = card.querySelector('.order-drag-handle');
                    if (!handle) return;

                    handle.addEventListener('pointerdown', event => {
                        if (event.pointerType === 'mouse' || !event.isPrimary) return;
                        pointerDrag = {
                            card,
                            pointerId: event.pointerId,
                            startX: event.clientX,
                            startY: event.clientY,
                            started: false,
                        };
                        handle.setPointerCapture(event.pointerId);
                    });

                    handle.addEventListener('pointermove', event => {
                        if (!pointerDrag || pointerDrag.pointerId !== event.pointerId) return;
                        const distance = Math.hypot(event.clientX - pointerDrag.startX, event.clientY - pointerDrag.startY);
                        if (!pointerDrag.started && distance < 8) return;

                        event.preventDefault();
                        pointerDrag.started = true;
                        draggedCard = pointerDrag.card;
                        draggedCard.classList.add('opacity-50');
                        clearDropHighlights();

                        const targetColumn = columnAtPoint(event.clientX, event.clientY);
                        if (targetColumn) targetColumn.classList.add('ring-2', 'ring-[#002c76]');
                    });

                    const finishPointerDrag = event => {
                        if (!pointerDrag || pointerDrag.pointerId !== event.pointerId) return;
                        if (pointerDrag.started && event.type === 'pointerup') {
                            const targetColumn = columnAtPoint(event.clientX, event.clientY);
                            const nextStatus = targetColumn ? targetStatus(targetColumn) : null;
                            if (nextStatus) {
                                const form = document.getElementById(pointerDrag.card.dataset.statusForm);
                                if (form) {
                                    form.querySelector('[name="laundry_status"]').value = nextStatus;
                                    form.requestSubmit();
                                }
                            }
                            pointerDrag.card.classList.remove('opacity-50');
                            clearDropHighlights();
                            draggedCard = null;
                        }
                        pointerDrag = null;
                    };

                    handle.addEventListener('pointerup', finishPointerDrag);
                    handle.addEventListener('pointercancel', finishPointerDrag);
                });

                columns.forEach(column => {
                    column.addEventListener('dragover', event => {
                        if (!draggedCard) return;
                        if (!targetStatus(column)) return;
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        column.classList.add('ring-2', 'ring-[#002c76]');
                    });

                    column.addEventListener('dragleave', event => {
                        if (!column.contains(event.relatedTarget)) {
                            column.classList.remove('ring-2', 'ring-[#002c76]');
                        }
                    });

                    column.addEventListener('drop', event => {
                        if (!draggedCard) return;
                        const nextStatus = targetStatus(column);
                        if (!nextStatus) return;
                        event.preventDefault();
                        const formId = event.dataTransfer.getData('text/plain');
                        const form = document.getElementById(formId);
                        if (!form) return;
                        form.querySelector('[name="laundry_status"]').value = nextStatus;
                        form.requestSubmit();
                    });
                });
            })();
        </script>
    @endif
</x-app-layout>
