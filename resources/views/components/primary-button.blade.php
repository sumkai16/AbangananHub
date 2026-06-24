<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-[#286CD2] border border-transparent rounded-xl font-bold text-[15px] text-white tracking-wide hover:bg-[#1D4ED8] focus:bg-[#1D4ED8] active:bg-[#1E3A8A] focus:outline-none focus:ring-2 focus:ring-[#286CD2] focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm hover:shadow-md']) }}>
    {{ $slot }}
</button>
