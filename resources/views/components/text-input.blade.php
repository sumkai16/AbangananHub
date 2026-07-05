@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-200 focus:border-[#FF8A65] focus:ring-[#FF8A65] rounded-xl shadow-sm px-4 py-2.5 text-[#1A1A2E] transition-colors duration-200']) }}>
