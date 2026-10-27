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

        <section class="bubble laundry-card p-5 sm:p-6">
            <div class="relative z-10 mb-5">
                <p class="text-sm font-semibold uppercase tracking-wider text-[#002c76]">Service settings</p>
                <h3 class="mt-1 text-xl font-bold text-[#002c76]">Add a laundry service</h3>
                <p class="mt-1 text-sm text-[#002c76]/70">Set the price and billing unit used by the POS.</p>
            </div>
            <form method="POST" action="{{ route('admin.services.store') }}" class="relative z-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
                <input name="name" required placeholder="Service name" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <select name="pricing_method" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"><option value="per_kg">Per kilogram (kg)</option><option value="per_piece">Per piece</option><option value="per_load">Per load</option><option value="fixed">Fixed price</option></select>
                <input name="rate" required type="number" step="0.01" min="0" placeholder="Price (₱)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <input name="description" placeholder="Description (optional)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <input name="minimum_weight" type="number" step="0.001" min="0" placeholder="Minimum kg (optional)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <input name="minimum_charge" type="number" step="0.01" min="0" placeholder="Minimum charge (₱)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <input name="additional_charge" type="number" step="0.01" min="0" placeholder="Extra charge (₱)" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <input name="estimated_processing_hours" type="number" min="1" placeholder="Processing hours" class="rounded-xl border-[#002c76]/20 bg-white text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                <button class="rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white shadow-lg shadow-[#002c76]/20 hover:bg-[#002c76] sm:col-span-2 lg:col-span-4">Add service</button>
            </form>
        </section>

        <section class="space-y-4">
            @forelse($services as $service)
                <article class="laundry-card p-5 sm:p-6">
                    <form method="POST" action="{{ route('admin.services.update', $service) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf @method('PUT')
                        <div class="lg:col-span-2"><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Service name</label><input name="name" value="{{ $service->name }}" required class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"></div>
                        <div><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Unit</label><select name="pricing_method" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"><option value="per_kg" @selected($service->pricing_method === 'per_kg')>Per kilogram</option><option value="per_piece" @selected($service->pricing_method === 'per_piece')>Per piece</option><option value="per_load" @selected($service->pricing_method === 'per_load')>Per load</option><option value="fixed" @selected($service->pricing_method === 'fixed')>Fixed price</option></select></div>
                        <div><label class="text-xs font-semibold uppercase tracking-wide text-[#002c76]">Price (₱)</label><input name="rate" value="{{ $service->rate }}" required type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"></div>
                        <div class="flex items-end"><button class="w-full rounded-xl bg-[#002c76] px-4 py-2 font-semibold text-white hover:bg-[#002c76]">Save changes</button></div>
                        <input name="description" value="{{ $service->description }}" placeholder="Description" class="rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76] sm:col-span-2 lg:col-span-2">
                        <input name="minimum_weight" value="{{ $service->minimum_weight }}" type="number" step="0.001" min="0" placeholder="Minimum kg" class="rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                        <input name="minimum_charge" value="{{ $service->minimum_charge }}" type="number" step="0.01" min="0" placeholder="Minimum charge" class="rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                        <input name="additional_charge" value="{{ $service->additional_charge }}" type="number" step="0.01" min="0" placeholder="Extra charge" class="rounded-xl border-[#002c76]/20 text-[#002c76] placeholder:text-[#002c76]/60 focus:border-[#002c76] focus:ring-[#002c76]">
                    </form>
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#002c76]/15 pt-4 text-sm">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">
                            @if($service->is_active)
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-7.5 9.5a.75.75 0 01-1.127.075l-4.25-4.25a.75.75 0 111.06-1.06l3.65 3.65 6.976-8.837a.75.75 0 011.048-.13z" clip-rule="evenodd" /></svg>
                            @else
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v3.5a.75.75 0 001.5 0v-3.5zm0 6a.75.75 0 00-1.5 0v.5a.75.75 0 001.5 0v-.5z" clip-rule="evenodd" /></svg>
                            @endif
                            {{ $service->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="text-[#002c76]/70">₱{{ number_format($service->rate, 2) }} / {{ str_replace(['per_', '_'], ['', ' '], $service->pricing_method) }}</span>
                        <form method="POST" action="{{ route('admin.services.toggle', $service) }}">@csrf @method('PATCH')<button class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 px-3 py-1 font-semibold text-amber-700 transition hover:bg-amber-50" title="{{ $service->is_active ? 'Deactivate service' : 'Activate service' }}">
                            @if($service->is_active)
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a7 7 0 105.657 2.879.75.75 0 10-1.214-.872A5.5 5.5 0 1110 4.5a.75.75 0 000-1.5z" /><path d="M10 1.75a.75.75 0 01.75.75v3.25H14a.75.75 0 010 1.5H10a.75.75 0 01-.75-.75V2.5a.75.75 0 01.75-.75z" /></svg>
                            @else
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M15.5 10a5.5 5.5 0 01-9.488 3.76.75.75 0 00-1.078 1.042A7 7 0 1010 3v2.25a.75.75 0 01-1.28.53L5.97 3.03a.75.75 0 010-1.06l2.75-2.75A.75.75 0 0110 0v1.5a8.5 8.5 0 11-5.704 2.194.75.75 0 111.008 1.112A7 7 0 1015.5 10z" clip-rule="evenodd" /></svg>
                            @endif
                            {{ $service->is_active ? 'Deactivate' : 'Activate' }}
                        </button></form>
                        <form method="POST" action="{{ route('admin.services.delete', $service) }}" onsubmit="return confirm('Delete this service? Existing order history will be preserved.')">@csrf @method('DELETE')<button class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1 font-semibold text-red-700 transition hover:bg-red-50" title="Delete service">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.5 2.75A1.75 1.75 0 0110.25 1h1.5a1.75 1.75 0 011.75 1.75V4H16a.75.75 0 010 1.5h-.69l-.626 10.02A2.75 2.75 0 0111.94 18H8.06a2.75 2.75 0 01-2.744-2.48L4.69 5.5H4A.75.75 0 014 4h3.5V2.75zM9.5 4h2.5V2.75a.25.25 0 00-.25-.25h-1.5a.25.25 0 00-.25.25V4zM8 7.25a.75.75 0 00-1.5 0l.5 7.5a.75.75 0 001.5-.1l-.5-7.4zm5.5 0a.75.75 0 00-1.5 0l-.5 7.4a.75.75 0 001.5.1l.5-7.5z" clip-rule="evenodd" /></svg>
                            Delete
                        </button></form>
                    </div>
                </article>
            @empty
                <div class="laundry-card p-8 text-center text-[#002c76]/70">No services configured yet.</div>
            @endforelse
            {{ $services->links() }}
        </section>
    </div>
</x-app-layout>
