@props([
    // ['total' => int, 'available' => int, 'reserved' => int, 'occupied' => int, 'maintenance' => int]
    'stats',
    // Optional: fn (string $status): string — when given, each legend row is a link that filters by that status.
    'filterUrl' => null,
    'active' => null,
    // 'row' = donut left, legend right (wide cards). 'stack' = donut above, legend below (narrow cards).
    'layout' => 'row',
])

{{--
    Unit status ring + legend. One component so the Units page and the property
    page can't drift apart on which colour means which status again (the property
    page used to paint Occupied green in the ring but Available navy in the legend).
    Colours follow the status badges used everywhere else: Available green,
    Reserved amber, Occupied red, Maintenance grey (#94A3B8, per DESIGN.md §7).
--}}
@php
    $circ = 251.33; // 2 * pi * r, r = 40
    $total = (int) ($stats['total'] ?? 0);
    $rows = [
        ['Available', (int) ($stats['available'] ?? 0), '#22C55E'],
        ['Reserved', (int) ($stats['reserved'] ?? 0), '#FBBF24'],
        ['Occupied', (int) ($stats['occupied'] ?? 0), '#EF4444'],
        ['Maintenance', (int) ($stats['maintenance'] ?? 0), '#94A3B8'],
    ];
    $occupiedPct = $total > 0 ? round(($stats['occupied'] ?? 0) / $total * 100) : 0;
    $cursor = 0;
@endphp

<div {{ $attributes->class(['flex gap-6', 'sm:gap-10' => $layout === 'row', 'flex-col items-center' => $layout === 'stack', 'flex-col sm:flex-row items-center' => $layout === 'row']) }}>
    <div class="relative w-32 h-32 shrink-0">
        <svg viewBox="0 0 112 112" class="w-full h-full -rotate-90" role="img"
            aria-label="{{ collect($rows)->map(fn ($r) => $r[1] . ' ' . strtolower($r[0]))->implode(', ') }}">
            <circle cx="56" cy="56" r="40" fill="none" stroke="#ECEEF6" stroke-width="14" />
            @if($total > 0)
                @foreach($rows as [$label, $count, $color])
                    @php $len = $count / $total * $circ; @endphp
                    @if($count > 0)
                        <circle cx="56" cy="56" r="40" fill="none" stroke="{{ $color }}" stroke-width="14"
                            stroke-dasharray="{{ round(max($len - 1.5, 0.5), 2) }} {{ round($circ - max($len - 1.5, 0.5), 2) }}"
                            stroke-dashoffset="-{{ round($cursor, 2) }}" />
                    @endif
                    @php $cursor += $len; @endphp
                @endforeach
            @endif
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <span class="text-[26px] font-semibold leading-none tabular-nums text-[#060D26]">{{ $total }}</span>
            <span class="mt-1 text-[11px] text-[#5B6A8E]">{{ Str::plural('unit', $total) }}</span>
        </div>
    </div>

    <div class="w-full min-w-0 {{ $layout === 'row' ? 'sm:max-w-md' : '' }}">
        <ul class="divide-y divide-[#E2E4EC]">
            @foreach($rows as [$label, $count, $color])
                @continue($label === 'Maintenance' && $count === 0 && $active !== $label)
                @php
                    $pct = $total > 0 ? round($count / $total * 100) : 0;
                    $isActive = $active === $label;
                    $rowClass = 'flex items-center gap-3 py-2.5 text-[13.5px]';
                @endphp
                <li>
                    @if($filterUrl)
                        <a href="{{ $filterUrl($label) }}" @if($isActive) aria-current="true" @endif
                            class="{{ $rowClass }} -mx-2 px-2 rounded-lg transition-colors duration-200 hover:bg-[#F7F8FC] {{ $isActive ? 'bg-[#F7F8FC]' : '' }}">
                    @else
                        <div class="{{ $rowClass }}">
                    @endif
                        <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $color }}" aria-hidden="true"></span>
                        <span class="flex-1 {{ $isActive ? 'font-semibold text-[#060D26]' : 'text-[#060D26]' }}">{{ $label }}</span>
                        <span class="w-10 text-right text-[12.5px] tabular-nums text-[#5B6A8E]">{{ $pct }}%</span>
                        <span class="w-8 text-right font-semibold tabular-nums text-[#060D26]">{{ $count }}</span>
                    @if($filterUrl)
                        </a>
                    @else
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
