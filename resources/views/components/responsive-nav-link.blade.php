@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#002c76] text-start text-base font-medium text-[#002c76] bg-white focus:outline-none focus:text-[#002c76] focus:bg-white focus:border-[#002c76] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#002c76] hover:text-[#002c76] hover:bg-white hover:border-[#002c76] focus:outline-none focus:text-[#002c76] focus:bg-white focus:border-[#002c76] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
