<x-app-layout>
    <div class="max-w-7xl mx-auto px-6 py-10 pb-16">

        {{-- HEADER --}}
        <x-section-header
            title="Browse Properties"
            sub="Verified rentals across Cebu — every listing reviewed, every landlord checked."
        />

        {{-- ACTIVE FILTERS SUMMARY --}}
        @if(request()->hasAny(['location', 'type', 'price_max', 'verified']))
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-[13px] text-gray-500 font-medium">Filtering by:</span>

                @if(request('location'))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-[13px] font-semibold">
                        📍 {{ request('location') }}
                        <a href="{{ request()->fullUrlWithoutQuery('location') }}" class="hover:text-blue-900">✕</a>
                    </span>
                @endif

                @if(request('type'))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-[13px] font-semibold">
                        🏠 {{ request('type') }}
                        <a href="{{ request()->fullUrlWithoutQuery('type') }}" class="hover:text-blue-900">✕</a>
                    </span>
                @endif

                @if(request('price_max'))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-[13px] font-semibold">
                        💰 Max ₱{{ number_format(request('price_max')) }}
                        <a href="{{ request()->fullUrlWithoutQuery('price_max') }}" class="hover:text-blue-900">✕</a>
                    </span>
                @endif

                @if(request('verified'))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-[13px] font-semibold">
                        ✓ Verified only
                        <a href="{{ request()->fullUrlWithoutQuery('verified') }}" class="hover:text-emerald-900">✕</a>
                    </span>
                @endif

                <a href="{{ route('properties.index') }}" class="text-[13px] text-red-500 hover:text-red-700 font-semibold ml-2">
                    Clear all
                </a>
            </div>
        @endif

        {{-- RESULTS COUNT --}}
        <p class="text-[13px] text-gray-400 font-medium mb-6">
            {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found
        </p>

        {{-- PROPERTY GRID --}}
        @if($properties->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-10">
                @foreach($properties as $property)
                    <a href="{{ route('properties.show', $property->property_id) }}"
                       class="group bg-white rounded-[20px] border border-gray-100 shadow-sm hover:shadow-lg transition-all overflow-hidden flex flex-col">

                        {{-- THUMBNAIL --}}
                        <div class="relative w-full aspect-[4/3] bg-gray-100 overflow-hidden">
                            @if($property->media->first())
                                <img
                                    src="{{ Storage::url($property->media->first()->media_url) }}"
                                    alt="{{ $property->title }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-[#EBF3FF] to-[#D7E8F3]">
                                    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>
                            @endif

                            {{-- TYPE BADGE --}}
                            <div class="absolute top-3 left-3">
                                <span class="px-2.5 py-1 bg-white/90 backdrop-blur-sm text-[11px] font-bold text-gray-700 rounded-full shadow-sm">
                                    {{ $property->property_type }}
                                </span>
                            </div>

                            {{-- VERIFIED BADGE --}}
                            @if($property->landlord->verificationApplication?->verification_status === 'Approved')
                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 bg-emerald-500 text-white text-[11px] font-bold rounded-full shadow-sm flex items-center gap-1">
                                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Verified
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- CARD BODY --}}
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] leading-tight line-clamp-1 group-hover:text-[#286CD2] transition-colors">
                                    {{ $property->title }}
                                </h3>
                            </div>

                            <p class="text-[13px] text-gray-400 mb-3 line-clamp-1">
                                📍 {{ $property->address }}
                            </p>

                            @if($property->amenities->count() > 0)
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    @foreach($property->amenities->take(3) as $amenity)
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[11px] font-medium rounded-full">
                                            {{ $amenity->amenity_name }}
                                        </span>
                                    @endforeach
                                    @if($property->amenities->count() > 3)
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-400 text-[11px] font-medium rounded-full">
                                            +{{ $property->amenities->count() - 3 }} more
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-auto pt-3 border-t border-gray-100 flex items-center justify-between">
                                <div>
                                    <span class="text-[18px] font-black text-[#286CD2]">₱{{ number_format($property->rental_fee) }}</span>
                                    <span class="text-[12px] text-gray-400 font-medium">/month</span>
                                </div>
                                <span class="text-[12px] font-semibold px-2.5 py-1 rounded-full
                                    {{ $property->availability_status === 'Available' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $property->availability_status }}
                                </span>
                            </div>
                        </div>

                    </a>
                @endforeach
            </div>

            {{-- PAGINATION --}}
            <div class="flex justify-center">
                {{ $properties->links() }}
            </div>

        @else
            <x-empty-state
                title="No properties found"
                message="Try adjusting your filters or search in a different area of Cebu."
                :href="route('properties.index')"
                cta="Clear filters"
            >
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </x-slot>
            </x-empty-state>
        @endif

    </div>
</x-app-layout>