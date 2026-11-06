@props(['items', 'cols' => 3, 'compact' => false, 'hideBlank' => false])
{{--
    Label and value pairs on admin detail pages. $items: label => value, or label => ['value' => ..., 'num' => true] for
    figures that line up. A blank value shows a dash, so a missing fact reads as missing rather than as a layout gap.
    `compact` tightens it for the compact pages. `hide-blank` drops the blanks and names them once in a line underneath,
    so a half-filled record does not spend whole rows on dashes.
--}}
@php
    $grid = [1 => 'grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 xl:grid-cols-3', 4 => 'sm:grid-cols-2 xl:grid-cols-4', 5 => 'sm:grid-cols-3 xl:grid-cols-5'][$cols] ?? 'sm:grid-cols-2 xl:grid-cols-3';
    $spacing = $compact ? 'grid-cols-2 gap-x-5 gap-y-2.5' : 'grid-cols-1 gap-x-6 gap-y-4';
    $isBlank = function ($item) {
        $value = is_array($item) ? ($item['value'] ?? null) : $item;

        return $value === null || $value === '';
    };
    $shown = $hideBlank ? collect($items)->reject($isBlank) : collect($items);
    $missing = $hideBlank ? collect($items)->filter($isBlank)->keys() : collect();
@endphp
<dl {{ $attributes->class("grid {$spacing} {$grid}") }}>
    @foreach($shown as $label => $item)
        @php $value = is_array($item) ? ($item['value'] ?? null) : $item; @endphp
        <div class="min-w-0">
            <dt class="{{ $compact ? 'text-[12px] leading-4' : 'text-[12.5px]' }} text-[#5B6A8E]">{{ $label }}</dt>
            <dd class="{{ $compact ? 'text-[13.5px] leading-5' : 'mt-0.5 text-[14px]' }} break-words font-medium text-[#060D26] {{ is_array($item) && ! empty($item['num']) ? 'tabular-nums' : '' }}">{{ $isBlank($item) ? '—' : $value }}</dd>
        </div>
    @endforeach
</dl>
@if($missing->isNotEmpty())
    <p class="mt-2 text-[12px] text-[#5B6A8E]">Not filled in: {{ $missing->implode(', ') }}.</p>
@endif
