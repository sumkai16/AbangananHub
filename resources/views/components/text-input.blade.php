@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#E2E4EC] focus:border-[#DA8E77] focus:ring-[#DA8E77] rounded-xl shadow-sm px-4 py-2.5 text-[#060D26] transition-colors duration-200']) }}>
