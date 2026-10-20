<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-[#FF8A66] border border-transparent rounded-xl font-semibold text-[15px] text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus:ring-2 focus:ring-[#FF8A66] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors duration-200']) }}>
    {{ $slot }}
</button>
