<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#002c76]">Settings</p>
            <h2 class="mt-1 text-xl font-bold text-[#002c76]">Business settings</h2>
        </div>
    </x-slot>
    <div class="tablet-safe mx-auto max-w-3xl p-4 sm:p-6">
        @if(session('success'))<div class="mb-4 rounded-xl bg-[#002c76] p-3 font-medium text-white">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-xl border border-[#002c76] bg-white p-3 font-medium text-[#002c76]">{{ $errors->first() }}</div>@endif
        @php($values = $settings->pluck('value', 'key'))
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="laundry-card space-y-5 p-5 sm:p-7">@csrf @method('PUT')
            <div class="flex items-center gap-3 border-b border-[#002c76]/15 pb-5">
                <span class="rounded-xl bg-[#002c76] p-3 text-white"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7.5A2.5 2.5 0 015.5 5h2l1.2-1.5h2.6L12.5 5h6A2.5 2.5 0 0121 7.5v10a2.5 2.5 0 01-2.5 2.5h-13A2.5 2.5 0 013 17.5v-10z"/><circle cx="12" cy="12.5" r="3.5"/></svg></span>
                <div><h3 class="font-bold text-[#002c76]">Business profile</h3><p class="text-sm text-[#002c76]/70">These details appear throughout the POS and receipts.</p></div>
            </div>
            <div class="rounded-2xl border border-[#002c76]/15 p-4">
                <label class="flex items-center gap-2 text-sm font-semibold text-[#002c76]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 016.5 3h11A2.5 2.5 0 0120 5.5v13a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 014 18.5v-13z"/><circle cx="9" cy="9" r="1.5"/><path d="M4.5 17l4.5-4 3 2.5 2-2 5.5 4.5"/></svg>Business logo</label>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    @if(!empty($values['business_logo']))
                        <img src="{{ asset('storage/'.$values['business_logo']) }}" alt="Current business logo" class="h-16 w-16 rounded-xl border border-[#002c76]/15 object-contain p-1">
                    @else
                        <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-[#002c76] text-white"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 016.5 3h11A2.5 2.5 0 0120 5.5v13a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 014 18.5v-13z"/><circle cx="9" cy="9" r="1.5"/><path d="M4.5 17l4.5-4 3 2.5 2-2 5.5 4.5"/></svg></div>
                    @endif
                    <div><input name="business_logo" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" class="block w-full text-sm text-[#002c76] file:mr-3 file:rounded-lg file:border-0 file:bg-[#002c76] file:px-3 file:py-2 file:font-semibold file:text-white"><p class="mt-1 text-xs text-[#002c76]/65">JPG, PNG, WEBP, or SVG. Maximum 2 MB.</p></div>
                </div>
            </div>
            <div><label class="flex items-center gap-2 text-sm font-semibold text-[#002c76]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/></svg>Business name</label><input name="business_name" required value="{{ old('business_name', $values['business_name'] ?? 'Laundry POS') }}" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"></div>
            <div><label class="flex items-center gap-2 text-sm font-semibold text-[#002c76]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6.5 4h3l1.5 4-2 1.5a15 15 0 006 6L16.5 14l4 1.5v3A1.5 1.5 0 0119 20C10.7 20 4 13.3 4 5a1.5 1.5 0 011.5-1z"/></svg>Phone</label><input name="business_phone" value="{{ old('business_phone', $values['business_phone'] ?? '') }}" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]"></div>
            <div><label class="flex items-center gap-2 text-sm font-semibold text-[#002c76]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 10-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/></svg>Address</label><textarea name="business_address" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]">{{ old('business_address', $values['business_address'] ?? '') }}</textarea></div>
            <div><label class="flex items-center gap-2 text-sm font-semibold text-[#002c76]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 4h12v16H6zM9 8h6M9 12h6M9 16h4"/></svg>Receipt terms</label><textarea name="receipt_terms" class="mt-1 w-full rounded-xl border-[#002c76]/20 text-[#002c76] focus:border-[#002c76] focus:ring-[#002c76]">{{ old('receipt_terms', $values['receipt_terms'] ?? '') }}</textarea></div>
            <button class="inline-flex items-center gap-2 rounded-xl bg-[#002c76] px-4 py-3 font-semibold text-white shadow-lg shadow-[#002c76]/20 hover:bg-[#002c76]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 4h11l3 3v13H5z"/><path d="M8 4v5h8V4M8 20v-6h8v6"/></svg>Save settings</button>
        </form>
    </div>
</x-app-layout>
