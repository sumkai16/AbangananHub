@extends('layouts.app', ['searchBar' => false])

@section('content')
<div class="w-full px-[50px] py-10 pb-16">
    <div class="flex items-center justify-between gap-4 mb-8">
        <x-section-header title="My Listings" sub="Manage, update, and monitor your rental properties registered on AbangananHub." />
        <a href="{{ route('properties.create') }}" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full bg-[#286CD2] hover:bg-[#1e5bb8] text-white text-[13.5px] font-bold shadow-sm hover:shadow transition-all duration-300">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add New Property
        </a>
    </div>

    @if($properties->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($properties as $property)
                <div class="group relative flex flex-col transition-all duration-300 hover:-translate-y-1">
                    {{-- IMAGE CONTAINER --}}
                    <div class="relative w-full aspect-square rounded-3xl overflow-hidden bg-gray-100 shadow-sm group-hover:shadow-lg transition-all duration-500">
                        @if($property->media->first())
                            <img src="{{ $property->media->first()->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-[#EBF3FF] text-[#286CD2]">
                                <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                        @endif

                        <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                            @if($property->verification_status === 'Approved')
                                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider bg-emerald-500 text-white rounded-full shadow-sm">Approved</span>
                            @elseif($property->verification_status === 'Pending')
                                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-white rounded-full shadow-sm">Pending</span>
                            @else
                                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider bg-red-500 text-white rounded-full shadow-sm">Rejected</span>
                            @endif
                        </div>
                    </div>

                    {{-- TEXT CONTENT (No-Card Layout) --}}
                    <div class="mt-3 px-1 flex-grow flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-[14px] font-semibold text-[#1A1A2E] leading-snug line-clamp-1">
                                    {{ $property->title }}
                                </h3>
                                <span class="text-[12px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0 {{ $property->availability_status === 'Available' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $property->availability_status }}
                                </span>
                            </div>

                            <p class="text-[13px] text-gray-400 mt-0.5 line-clamp-1 flex items-center gap-1">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                {{ $property->address }}
                            </p>

                            <p class="text-[14px] font-semibold text-[#1A1A2E] mt-1">
                                ₱{{ number_format($property->rental_fee) }}
                                <span class="text-[13px] font-normal text-gray-400">/month</span>
                            </p>
                        </div>

                        {{-- ACTIONS BAR --}}
                        <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-gray-100">
                            <a href="{{ route('properties.edit', $property->property_id) }}" class="flex items-center justify-center h-9 rounded-xl bg-gray-50 border border-gray-150 text-[13px] font-bold text-gray-650 hover:bg-gray-100 transition-colors">
                                Edit Details
                            </a>
                            <form action="{{ route('properties.destroy', $property->property_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full flex items-center justify-center h-9 rounded-xl bg-red-50 border border-red-100 text-[13px] font-bold text-red-600 hover:bg-red-100 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state title="No properties listed yet"
            message="Start earning by showcasing your space to looking tenants in Cebu."
            href="{{ route('properties.create') }}"
            cta="List Your First Property">
            <x-slot name="icon">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </x-slot>
        </x-empty-state>
    @endif
</div>
@endsection