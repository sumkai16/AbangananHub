<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-white border border-[#E2E4EC] rounded-xl font-bold text-[15px] text-[#060D26] tracking-wide shadow-sm hover:bg-[#F7F8FC] hover:border-[#E2E4EC] focus:outline-none focus:ring-2 focus:ring-[#C9A84C] focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
