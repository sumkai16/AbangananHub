@php
    $thumb = $property->media->first();
    $availableCount = $property->units->where('availability_status', 'Available')->count();
@endphp
<li>
    <a href="{{ route('properties.show', $property) }}"
        class="group block h-full overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white hover:border-[#5B6A8E]/40 transition-colors duration-200">
        <div class="h-36 overflow-hidden bg-[#ECEEF6]">
            @if($thumb)
                <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300 motion-reduce:transition-none">
            @else
                <div class="h-full w-full flex items-center justify-center" aria-hidden="true">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                    </svg>
                </div>
            @endif
        </div>
        <div class="p-3.5">
            <p class="text-[14px] font-bold text-[#060D26] truncate group-hover:text-[#B35A3D] transition-colors duration-200">{{ $property->title }}</p>
            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] truncate">{{ $property->property_type }} &middot; {{ $property->city_municipality }}</p>
            <div class="mt-2 flex items-baseline justify-between gap-2">
                <p class="text-[14px] font-bold text-[#060D26] tabular-nums">
                    @if($property->min_rental_fee)
                        &#8369;{{ number_format($property->min_rental_fee) }}<span class="text-[12px] font-normal text-[#5B6A8E]">/mo</span>
                    @else
                        <span class="text-[13px] font-normal text-[#5B6A8E]">Price not set</span>
                    @endif
                </p>
                <p class="text-[12px] font-medium {{ $availableCount > 0 ? 'text-[#15803D]' : 'text-[#5B6A8E]' }}">
                    {{ $availableCount > 0 ? $availableCount . ' available' : 'Fully occupied' }}
                </p>
            </div>
        </div>
    </a>
</li>
