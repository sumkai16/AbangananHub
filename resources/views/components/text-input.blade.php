@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#E2E8F0] focus:border-[#2AA7A1] focus:ring-[#2AA7A1] rounded-xl shadow-sm px-4 py-2.5 text-[#156F8C] transition-colors duration-200']) }}>
