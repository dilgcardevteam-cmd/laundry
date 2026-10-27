<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white border border-[#002c76] rounded-md font-semibold text-xs text-[#002c76] uppercase tracking-widest shadow-sm hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#002c76] focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
