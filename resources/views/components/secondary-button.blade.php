<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-white border border-[#E2E8F0] rounded-xl font-bold text-[15px] text-[#1F2937] tracking-wide shadow-sm hover:bg-[#F7FCFC] hover:border-[#E2E8F0] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
