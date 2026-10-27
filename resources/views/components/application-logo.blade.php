@php
    $businessLogo = \App\Models\BusinessSetting::where('key', 'business_logo')->value('value');
@endphp

@if($businessLogo)
    <img src="{{ asset('storage/'.$businessLogo) }}" alt="{{ \App\Models\BusinessSetting::where('key', 'business_name')->value('value') ?: 'Laundry POS' }}" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <svg viewBox="0 0 316 316" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
        <path fill="currentColor" d="M305.8 81.125 249.12 48.275 192.33 81.125v58.5l-43.71 25.17V52.575L91.76 18.585 35 52.575v179.66l109.28 64.78 109.27-64.77v-57.36l50.26-28.94V82.265zM144.2 227.205 96.13 194.995l100.53-57.8 43.67 25.14-96.13 64.87z"/>
    </svg>
@endif
