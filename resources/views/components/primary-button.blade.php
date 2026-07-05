<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-[#2AA7A1] border border-transparent rounded-xl font-bold text-[15px] text-white tracking-wide hover:brightness-95 focus:brightness-95 active:brightness-90 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm hover:shadow-md']) }}>
    {{ $slot }}
</button>
