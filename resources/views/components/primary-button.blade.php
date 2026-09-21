<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-[#FF8A66] border border-transparent rounded-xl font-bold text-[15px] text-[#060D26] tracking-wide hover:bg-[#E96F4F] active:brightness-90 focus:outline-none focus:ring-2 focus:ring-[#FF8A66] focus:ring-offset-2 transition ease-in-out duration-200 shadow-sm hover:shadow-md']) }}>
    {{ $slot }}
</button>
