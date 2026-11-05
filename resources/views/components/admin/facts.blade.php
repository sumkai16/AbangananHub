@props(['items', 'cols' => 3])
{{--
    Label and value pairs on admin detail pages. $items: label => value, or label => ['value' => ..., 'num' => true] for
    figures that line up. A blank value shows a dash, so a missing fact reads as missing rather than as a layout gap.
--}}
@php
    $grid = [1 => 'grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 xl:grid-cols-3', 4 => 'sm:grid-cols-2 xl:grid-cols-4'][$cols] ?? 'sm:grid-cols-2 xl:grid-cols-3';
@endphp
<dl {{ $attributes->class("grid grid-cols-1 gap-x-6 gap-y-4 {$grid}") }}>
    @foreach($items as $label => $item)
        @php
            $value = is_array($item) ? ($item['value'] ?? null) : $item;
            $isBlank = $value === null || $value === '';
        @endphp
        <div class="min-w-0">
            <dt class="text-[12.5px] text-[#5B6A8E]">{{ $label }}</dt>
            <dd class="mt-0.5 break-words text-[14px] font-medium text-[#060D26] {{ is_array($item) && ! empty($item['num']) ? 'tabular-nums' : '' }}">{{ $isBlank ? '—' : $value }}</dd>
        </div>
    @endforeach
</dl>
