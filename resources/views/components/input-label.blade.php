@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#002c76]']) }}>
    {{ $value ?? $slot }}
</label>
