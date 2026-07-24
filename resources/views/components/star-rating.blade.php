@props([
    'rating' => null,   // float|null — null or 0 count renders the empty state
    'count' => null,    // int|null — shown as "(N)" when provided
    'size' => 'sm',     // 'sm' | 'md'
    'showValue' => true, // show the numeric average beside the stars
    'emptyLabel' => 'No ratings yet',
])

@php
    // Star pixel size + text size per scale. One place so every rating on the
    // site lines up.
    $px = $size === 'md' ? 16 : 13;
    $valueClass = $size === 'md' ? 'text-[14px]' : 'text-[12.5px]';
    $countClass = $size === 'md' ? 'text-[12px]' : 'text-[11px]';

    $value = $rating !== null ? (float) $rating : null;
    $hasRating = $value !== null && ($count === null || $count > 0);
    $filled = $hasRating ? (int) round($value) : 0;
@endphp

@if($hasRating)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
        <span class="inline-flex gap-0.5" role="img"
            aria-label="Rated {{ number_format($value, 1) }} out of 5{{ $count !== null ? ' from ' . $count . ' ' . \Illuminate\Support\Str::plural('rating', $count) : '' }}">
            @for($i = 1; $i <= 5; $i++)
                <svg width="{{ $px }}" height="{{ $px }}" viewBox="0 0 24 24"
                    fill="{{ $i <= $filled ? '#FBBF24' : '#E2E8F0' }}" stroke="none" aria-hidden="true">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
            @endfor
        </span>
        @if($showValue)
            <span class="{{ $valueClass }} font-bold text-[#1F2937]">{{ number_format($value, 1) }}</span>
        @endif
        @if($count !== null)
            <span class="{{ $countClass }} text-[#64748B]">({{ $count }})</span>
        @endif
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
        <span class="inline-flex gap-0.5" aria-hidden="true">
            @for($i = 1; $i <= 5; $i++)
                <svg width="{{ $px }}" height="{{ $px }}" viewBox="0 0 24 24" fill="#E2E8F0" stroke="none">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
            @endfor
        </span>
        <span class="{{ $countClass }} text-[#94A3B8]">{{ $emptyLabel }}</span>
    </span>
@endif
