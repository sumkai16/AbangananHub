@extends('layouts.app', ['searchBar' => false])

@section('title', 'All areas — AbangananHub')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-16 min-h-[60vh]">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12px] font-semibold uppercase tracking-[0.08em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>All areas</span>
            <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#FF8A66] via-[#FF8A66]/70 to-[#FF8A66]/10" aria-hidden="true"></span>
            <span class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-medium text-[#5B6A8E] whitespace-nowrap">
                {{ $areas->count() }} {{ Str::plural('area', $areas->count()) }} &middot; {{ $areas->sum('count') }} listings
            </span>
        </div>
        <h1 class="mt-2 mb-6 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[24px] sm:text-[32px] font-bold leading-tight text-[#060D26]">Every neighborhood, <span class="text-[#5B6A8E]">one place.</span></h1>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @foreach($areas as $area)
                <a href="{{ route('properties.index', ['location' => $area['name']]) }}"
                    class="relative h-40 rounded-2xl overflow-hidden group border border-[#E2E4EC] bg-[#ECEEF6] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#FF8A66] hover:shadow-[0_10px_24px_rgba(6,13,38,0.14)] motion-reduce:hover:translate-y-0">
                    @if($area['photo'])
                        <img src="{{ $area['photo'] }}" alt="{{ $area['name'] }}" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/90 via-[#060D26]/35 to-transparent"></div>
                    <div class="absolute bottom-3 left-3 right-3">
                        <p class="text-white font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[16px] font-bold leading-tight truncate [text-shadow:0_1px_6px_rgba(6,13,38,0.5)]">{{ $area['name'] }}</p>
                        <p class="mt-1 inline-flex font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12px] font-semibold text-white px-2.5 py-0.5 rounded-full border border-[#FF8A66]/70 bg-[#060D26]/40">
                            {{ $area['count'] }} {{ Str::plural('listing', $area['count']) }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection
