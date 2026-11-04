@props(['align' => 'left'])
{{-- One admin table header. Money and count columns pass align="right" and right-align their cells with tabular-nums. --}}
<th scope="col" {{ $attributes->class(['px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide whitespace-nowrap text-[#5B6A8E]', 'text-right' => $align === 'right', 'text-center' => $align === 'center', 'text-left' => $align === 'left']) }}>{{ $slot }}</th>
