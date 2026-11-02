<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#002c76]">Settings</p>
            <h2 class="mt-1 text-xl font-bold text-[#002c76]">Laundry services</h2>
        </div>
    </x-slot>
    <div class="tablet-safe mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        @if(session('success'))<div class="rounded-xl border border-[#002c76] bg-[#002c76] p-3 font-medium text-white">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-xl border border-[#002c76] bg-white p-3 font-medium text-[#002c76]">{{ $errors->first() }}</div>@endif

        <section class="bubble laundry-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="relative z-10">
                <p class="text-sm font-semibold uppercase tracking-wider text-[#002c76]">Service settings</p>
                <h3 class="mt-1 text-xl font-bold text-[#002c76]">Manage laundry services</h3>
                <p class="mt-1 text-sm text-[#002c76]/70">Set the price and billing unit used by the POS.</p>
            </div>
            <button type="button" x-data="" x-on:click="$dispatch('open-modal', 'add-laundry-service')" class="relative z-10 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#002c76] px-5 py-3 font-semibold text-white shadow-lg shadow-[#002c76]/20 transition hover:bg-[#0645a2] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#002c76] focus-visible:ring-offset-2 sm:w-auto">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Add service
            </button>
        </section>

        <x-modal name="add-laundry-service" :show="$errors->has('name') || $errors->has('pricing_method') || $errors->has('rate') || $errors->has('description') || $errors->has('minimum_weight') || $errors->has('minimum_charge')" maxWidth="lg" focusable>
            <form id="new-service-form" method="POST" action="{{ route('admin.services.store') }}" class="max-h-[90vh] overflow-y-auto p-5 sm:p-6">
                @csrf
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/65">Service settings</p>
                    <h3 class="mt-1 text-xl font-bold text-[#002c76]">Add a laundry service</h3>
                    <p class="mt-1 text-sm text-[#002c76]/70">Set the price and billing unit used by the POS.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <input name="name" value="{{ old('name') }}" required placeholder="Service name" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2">
                    <select id="new-service-pricing-method" name="pricing_method" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2">
                        <option value="per_kg" @selected(old('pricing_method', 'per_kg') === 'per_kg')>Per kilogram (kg)</option>
                        <option value="per_piece" @selected(old('pricing_method') === 'per_piece')>Per piece</option>
                        <option value="per_load" @selected(old('pricing_method') === 'per_load')>Per load</option>
                        <option value="fixed" @selected(old('pricing_method') === 'fixed')>Fixed price</option>
                    </select>
                    <input name="rate" value="{{ old('rate') }}" required type="number" step="0.01" min="0" placeholder="Price (₱)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2">
                    <input name="description" value="{{ old('description') }}" placeholder="Description (optional)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2">
                    <label class="service-minimum-label sr-only" for="new-service-minimum">Minimum kg (optional)</label>
                    <input id="new-service-minimum" class="service-minimum-quantity rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]" name="minimum_weight" value="{{ old('minimum_weight') }}" type="number" step="0.001" min="0" placeholder="Minimum kg (optional)">
                    <input name="minimum_charge" value="{{ old('minimum_charge') }}" type="number" step="0.01" min="0" placeholder="Minimum charge (₱)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-xl border border-[#002c76]/20 px-5 py-3 font-semibold text-[#002c76] transition hover:bg-[#002c76]/5">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#002c76] px-5 py-3 font-semibold text-white shadow-lg shadow-[#002c76]/20 transition hover:bg-[#0645a2]">Add service</button>
                </div>
            </form>
        </x-modal>

        <section class="space-y-4">
            @forelse($services as $service)
                <article class="service-accordion laundry-card overflow-hidden">
                    <details>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 marker:hidden transition hover:bg-[#f3f7ff] sm:p-5">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eaf2ff] text-[#174a99]">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="10" cy="17" r="1"/></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate font-bold text-[#002c76]">{{ $service->name }}</span>
                                    <span class="mt-0.5 block text-sm text-[#002c76]/65">₱{{ number_format($service->rate, 2) }} / {{ str_replace(['per_', '_'], ['', ' '], $service->pricing_method) }}</span>
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">{{ $service->is_active ? 'Active' : 'Inactive' }}</span>
                                <svg class="service-accordion-chevron h-5 w-5 text-[#002c76]/65 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </span>
                        </summary>
                        <div class="border-t border-[#002c76]/10 p-4 sm:p-5">
                            <form method="POST" action="{{ route('admin.services.update', $service) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf @method('PUT')
                                <div class="lg:col-span-2"><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Service name</label><input name="name" value="{{ $service->name }}" required class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Unit</label><select name="pricing_method" class="service-pricing-method mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"><option value="per_kg" @selected($service->pricing_method === 'per_kg')>Per kilogram</option><option value="per_piece" @selected($service->pricing_method === 'per_piece')>Per piece</option><option value="per_load" @selected($service->pricing_method === 'per_load')>Per load</option><option value="fixed" @selected($service->pricing_method === 'fixed')>Fixed price</option></select></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Price (₱)</label><input name="rate" value="{{ $service->rate }}" required type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Description</label><input name="description" value="{{ $service->description }}" placeholder="Description" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]"></div>
                                <div><label for="service-minimum-{{ $service->id }}" class="service-minimum-label text-xs font-semibold uppercase tracking-wide text-[#002c76]">{{ $service->pricing_method === 'per_piece' ? 'Minimum pieces' : ($service->pricing_method === 'per_load' ? 'Maximum kg per load' : ($service->pricing_method === 'fixed' ? 'Minimum quantity' : 'Minimum kg')) }}</label><input id="service-minimum-{{ $service->id }}" name="minimum_weight" value="{{ $service->minimum_weight }}" type="number" step="{{ in_array($service->pricing_method, ['per_kg', 'per_load'], true) ? '0.01' : '1' }}" min="{{ $service->pricing_method === 'per_load' ? '0.01' : '0' }}" placeholder="{{ $service->pricing_method === 'per_piece' ? 'Minimum pieces' : ($service->pricing_method === 'per_load' ? 'Maximum kg per load' : ($service->pricing_method === 'fixed' ? 'Minimum quantity' : 'Minimum kg')) }}" class="service-minimum-quantity mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]"></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Minimum charge</label><input name="minimum_charge" value="{{ $service->minimum_charge }}" type="number" step="0.01" min="0" placeholder="Minimum charge" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]"></div>
                                <div class="flex items-end"><button class="w-full rounded-xl bg-[#002c76] px-4 py-2.5 font-semibold text-white transition hover:bg-[#0645a2]">Save changes</button></div>
                            </form>
                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#002c76]/10 pt-4 text-sm">
                                <form method="POST" action="{{ route('admin.services.toggle', $service) }}">@csrf @method('PATCH')<button class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 px-3 py-1.5 font-semibold text-amber-700 transition hover:bg-amber-50" title="{{ $service->is_active ? 'Deactivate service' : 'Activate service' }}">
                                    @if($service->is_active)
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a7 7 0 105.657 2.879.75.75 0 10-1.214-.872A5.5 5.5 0 1110 4.5a.75.75 0 000-1.5z"/><path d="M10 1.75a.75.75 0 01.75.75v3.25H14a.75.75 0 010 1.5H10a.75.75 0 01-.75-.75V2.5a.75.75 0 01.75-.75z"/></svg>
                                    @else
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M15.5 10a5.5 5.5 0 01-9.488 3.76.75.75 0 00-1.078 1.042A7 7 0 1010 3v2.25a.75.75 0 01-1.28.53L5.97 3.03a.75.75 0 010-1.06l2.75-2.75A.75.75 0 0110 0v1.5a8.5 8.5 0 11-5.704 2.194.75.75 0 111.008 1.112A7 7 0 1015.5 10z" clip-rule="evenodd"/></svg>
                                    @endif
                                    {{ $service->is_active ? 'Deactivate' : 'Activate' }}
                                </button></form>
                                <form method="POST" action="{{ route('admin.services.delete', $service) }}" onsubmit="return confirm('Delete this service? Existing order history will be preserved.')">@csrf @method('DELETE')<button class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 font-semibold text-red-700 transition hover:bg-red-50" title="Delete service">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.5 2.75A1.75 1.75 0 0110.25 1h1.5a1.75 1.75 0 011.75 1.75V4H16a.75.75 0 010 1.5h-.69l-.626 10.02A2.75 2.75 0 0111.94 18H8.06a2.75 2.75 0 01-2.744-2.48L4.69 5.5H4A.75.75 0 014 4h3.5V2.75zM9.5 4h2.5V2.75a.25.25 0 00-.25-.25h-1.5a.25.25 0 00-.25.25V4zM8 7.25a.75.75 0 00-1.5 0l.5 7.5a.75.75 0 001.5-.1l-.5-7.4zm5.5 0a.75.75 0 00-1.5 0l-.5 7.4a.75.75 0 001.5.1l.5-7.5z" clip-rule="evenodd"/></svg>
                                    Delete
                                </button></form>
                            </div>
                        </div>
                    </details>
                </article>
            @empty
                <div class="laundry-card p-8 text-center text-[#002c76]/70">No services configured yet.</div>
            @endforelse
            {{ $services->links() }}
        </section>

        <section class="bubble laundry-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="relative z-10">
                <p class="text-sm font-semibold uppercase tracking-wider text-[#002c76]">Optional extras</p>
                <h3 class="mt-1 text-xl font-bold text-[#002c76]">Manage add-ons</h3>
                <p class="mt-1 text-sm text-[#002c76]/70">These can be selected individually for each POS order.</p>
            </div>
            <button type="button" x-data="" x-on:click="$dispatch('open-modal', 'add-laundry-addon')" class="relative z-10 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#002c76] px-5 py-3 font-semibold text-white shadow-lg shadow-[#002c76]/20 transition hover:bg-[#0645a2] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#002c76] focus-visible:ring-offset-2 sm:w-auto">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Add add-on
            </button>
        </section>

        <x-modal name="add-laundry-addon" :show="$errors->has('add_on_name') || $errors->has('price')" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('admin.add-ons.store') }}" class="p-5 sm:p-6">
                @csrf
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#002c76]/65">Optional extra</p>
                <h3 class="mt-1 text-xl font-bold text-[#002c76]">Add an order add-on</h3>
                <p class="mt-1 text-sm text-[#002c76]/70">Set a name and a flat price for each order that selects it.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <input name="add_on_name" value="{{ old('add_on_name') }}" required maxlength="255" placeholder="Add-on name" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2">
                    <input name="price" value="{{ old('price') }}" required type="number" step="0.01" min="0" placeholder="Price (₱)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2">
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-xl border border-[#002c76]/20 px-5 py-3 font-semibold text-[#002c76] transition hover:bg-[#002c76]/5">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#002c76] px-5 py-3 font-semibold text-white shadow-lg shadow-[#002c76]/20 transition hover:bg-[#0645a2]">Add add-on</button>
                </div>
            </form>
        </x-modal>

        <section class="space-y-3">
            @forelse($addOns as $addOn)
                <article class="service-accordion laundry-card overflow-hidden">
                    <details>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 marker:hidden transition hover:bg-[#f3f7ff] sm:p-5">
                            <span class="min-w-0">
                                <span class="block truncate font-bold text-[#002c76]">{{ $addOn->name }}</span>
                                <span class="mt-0.5 block text-sm text-[#002c76]/65">₱{{ number_format($addOn->price, 2) }} per order</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $addOn->is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">{{ $addOn->is_active ? 'Active' : 'Inactive' }}</span>
                                <svg class="service-accordion-chevron h-5 w-5 text-[#002c76]/65 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </span>
                        </summary>
                        <div class="border-t border-[#002c76]/10 p-4 sm:p-5">
                            <form method="POST" action="{{ route('admin.add-ons.update', $addOn) }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">@csrf @method('PUT')
                                <label class="sr-only" for="add-on-name-{{ $addOn->id }}">Add-on name</label>
                                <input id="add-on-name-{{ $addOn->id }}" name="add_on_name" value="{{ $addOn->name }}" required maxlength="255" class="rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]">
                                <label class="sr-only" for="add-on-price-{{ $addOn->id }}">Price</label>
                                <input id="add-on-price-{{ $addOn->id }}" name="price" value="{{ $addOn->price }}" required type="number" step="0.01" min="0" class="rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]">
                                <button class="rounded-xl bg-[#002c76] px-4 py-2.5 font-semibold text-white transition hover:bg-[#0645a2]">Save changes</button>
                            </form>
                            <div class="mt-4 flex flex-wrap gap-2 border-t border-[#002c76]/10 pt-4 text-sm">
                                <form method="POST" action="{{ route('admin.add-ons.toggle', $addOn) }}">@csrf @method('PATCH')<button class="rounded-lg border border-amber-300 px-3 py-1.5 font-semibold text-amber-700 transition hover:bg-amber-50">{{ $addOn->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                <form method="POST" action="{{ route('admin.add-ons.delete', $addOn) }}" onsubmit="return confirm('Delete this add-on? Existing order history will be preserved.')">@csrf @method('DELETE')<button class="rounded-lg border border-red-300 px-3 py-1.5 font-semibold text-red-700 transition hover:bg-red-50">Delete</button></form>
                            </div>
                        </div>
                    </details>
                </article>
            @empty
                <div class="laundry-card p-8 text-center text-[#002c76]/70">No add-ons configured yet.</div>
            @endforelse
            {{ $addOns->links() }}
        </section>
    </div>
    <script>
        (() => {
            const minimumQuantityLabels = {
                per_kg: 'Minimum kg',
                per_piece: 'Minimum pieces',
                per_load: 'Maximum kg per load',
                fixed: 'Minimum quantity',
            };

            const configureMinimumQuantity = form => {
                const pricingMethod = form.querySelector('[name="pricing_method"]');
                const minimumQuantity = form.querySelector('.service-minimum-quantity');
                const minimumLabel = form.querySelector('.service-minimum-label');
                const label = minimumQuantityLabels[pricingMethod.value] ?? 'Minimum quantity';

                minimumQuantity.step = ['per_kg', 'per_load'].includes(pricingMethod.value) ? '0.01' : '1';
                minimumQuantity.min = pricingMethod.value === 'per_load' ? '0.01' : '0';
                minimumQuantity.placeholder = `${label}${form.id === 'new-service-form' ? ' (optional)' : ''}`;
                minimumLabel.textContent = label;
            };

            document.querySelectorAll('.service-minimum-quantity').forEach(minimumQuantity => {
                const form = minimumQuantity.closest('form');
                const pricingMethod = form.querySelector('[name="pricing_method"]');

                configureMinimumQuantity(form);
                pricingMethod.addEventListener('change', () => configureMinimumQuantity(form));
            });
        })();
    </script>
</x-app-layout>
