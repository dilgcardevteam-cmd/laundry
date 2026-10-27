<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#002c76] border border-[#002c76] rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#002c76] focus:bg-[#002c76] active:bg-[#002c76] focus:outline-none focus:ring-2 focus:ring-[#002c76] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
