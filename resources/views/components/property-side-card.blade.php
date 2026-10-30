@props(['property', 'unit' => null, 'photo' => null, 'eyebrow' => 'Property'])
{{-- Right-hand card on the landlord chat pages: the property photo and name, then whatever the page puts in the slot. --}}
<aside class="hidden xl:flex w-[300px] flex-shrink-0 flex-col overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]" aria-label="{{ $eyebrow }}">
    @if($photo)
        <img src="{{ $photo }}" alt="{{ $property->title }}" class="h-44 w-full flex-shrink-0 object-cover" loading="lazy">
    @else
        <div class="flex h-44 w-full flex-shrink-0 items-center justify-center bg-[#ECEEF6] text-[12px] text-[#5B6A8E]">No photo yet</div>
    @endif
    <div class="flex flex-col gap-4 overflow-y-auto p-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#5B6A8E]">{{ $eyebrow }}</p>
            <h3 class="mt-1 text-[15px] font-semibold leading-snug text-[#060D26]">{{ $property->title }}</h3>
            @if($unit)
                <p class="text-[13px] text-[#060D26]">{{ $unit->unit_label }}</p>
            @endif
            <p class="mt-1 text-[12px] leading-5 text-[#5B6A8E]">{{ collect([$property->address, $property->barangay, $property->city_municipality])->filter()->join(', ') }}</p>
        </div>
        {{ $slot }}
        <a href="{{ route('properties.show', $property) }}"
            class="inline-flex h-9 items-center justify-center rounded-lg border border-[#E2E4EC] px-3 text-[12px] font-bold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">View listing</a>
    </div>
</aside>
