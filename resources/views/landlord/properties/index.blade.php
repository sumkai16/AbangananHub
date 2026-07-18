@extends('layouts.landlord')
@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">My Properties</h1>
                    <p class="text-sm text-[#64748B] mt-0.5">Manage and monitor your rental properties on AbangananHub.</p>
                </div>
            </div>
            <a href="{{ route('properties.create') }}"
                class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-full bg-[#2AA7A1] hover:brightness-95 text-white text-sm font-semibold shadow-sm transition-all duration-200 shrink-0">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add New Property
            </a>
        </div>

        {{-- Filter toolbar --}}
        <form method="GET" action="{{ route('landlord.properties.index') }}"
            class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">

                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="15" height="15" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by property name or address..."
                        class="pl-10 pr-4 h-11 w-full rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative">
                        <select name="status"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                            <option value="">All Status</option>
                            <option value="Approved" @selected(request('status') === 'Approved')>Approved</option>
                            <option value="Pending" @selected(request('status') === 'Pending')>Pending</option>
                            <option value="Rejected" @selected(request('status') === 'Rejected')>Rejected</option>
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <div class="relative">
                        <select name="type"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                            <option value="">All Types</option>
                            <option value="Bedspace" @selected(request('type') === 'Bedspace')>Bedspace</option>
                            <option value="Room" @selected(request('type') === 'Room')>Room</option>
                            <option value="Apartment" @selected(request('type') === 'Apartment')>Apartment</option>
                            <option value="House" @selected(request('type') === 'House')>House for Rent</option>
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <button type="submit"
                        class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filter
                    </button>

                    @if(request()->hasAny(['search', 'status', 'type']))
                        <a href="{{ route('landlord.properties.index') }}"
                            class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>

            @if(request()->hasAny(['search', 'status', 'type']))
                <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-[#64748B]/10 text-[12.5px] text-[#64748B]">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span class="font-semibold text-[#1F2937]">{{ $properties->total() }}</span>
                    {{ Str::plural('property', $properties->total()) }} match{{ $properties->total() === 1 ? 'es' : '' }} your filters
                </div>
            @endif
        </form>

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EEF8F8] text-[#1F2937] text-sm font-medium flex items-center gap-2">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    class="shrink-0 text-[#2AA7A1]">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- Empty state --}}
        @if($properties->isEmpty())
            <div class="flex flex-col items-center justify-center py-24 text-center">
                <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                        class="text-[#64748B]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                    </svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1F2937]">No properties yet</p>
                <p class="text-sm text-[#64748B] mt-1 max-w-xs">Add your first property to start receiving reservations from
                    tenants.</p>
                <a href="{{ route('properties.create') }}"
                    class="mt-5 inline-flex items-center gap-2 h-10 px-5 rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Property
                </a>
            </div>

            {{-- Grid --}}
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($properties as $property)
                    @php
                        $thumb = $property->media->first();
                        [$statusBg, $statusText] = match ($property->verification_status) {
                            'Approved' => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Approved'],
                            'Pending' => ['bg-amber-50 text-amber-600 ring-amber-200', 'Pending'],
                            'Rejected' => ['bg-red-50 text-red-600 ring-red-200', 'Rejected'],
                            default => ['bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20', 'Unknown'],
                        };
                    @endphp

                    <article
                        class="group flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#64748B]/15 hover:ring-[#64748B]/30 hover:shadow-lg transition-all duration-300">

                        {{-- Thumbnail --}}
                        <a href="{{ route('landlord.properties.show', $property) }}"
                            class="relative block aspect-[16/10] overflow-hidden bg-[#EEF8F8] shrink-0">
                            @if($thumb)
                                <img src="{{ $thumb->media_url }}" alt="{{ $property->title }}"
                                    class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500 ease-out">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center gap-2">
                                    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"
                                        class="text-[#64748B]/60">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                                    </svg>
                                    <span class="text-xs text-[#64748B]/70">No photos yet</span>
                                </div>
                            @endif

                            {{-- Status chip --}}
                            <span
                                class="absolute top-3 left-3 inline-flex items-center text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ $statusBg }}">
                                {{ $statusText }}
                            </span>
                        </a>

                        {{-- Body --}}
                        <div class="flex flex-col flex-1 p-4 gap-3">

                            {{-- Title + address --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('landlord.properties.show', $property) }}"
                                        class="block text-[15px] font-bold text-[#1F2937] leading-snug line-clamp-1 hover:text-[#EF4444] transition-colors duration-200">
                                        {{ $property->title }}
                                    </a>
                                    <p class="text-[12px] text-[#64748B] mt-0.5 line-clamp-1 flex items-center gap-1">
                                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2" class="shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z" />
                                        </svg>
                                        {{ $property->address }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 text-[11px] font-medium text-[#2AA7A1] border border-[#2AA7A1]/40 rounded-full px-2.5 py-0.5 mt-0.5">
                                    {{ $property->property_type }}
                                </span>
                            </div>

                            {{-- Unit stats --}}
                            <div class="grid grid-cols-4 divide-x divide-[#64748B]/10 rounded-xl bg-[#EEF8F8]/60 py-2.5">
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="text-[14px] font-bold text-[#1F2937]">{{ $property->units_count }}</span>
                                    <span class="text-[10px] text-[#64748B] font-medium">Total</span>
                                </div>
                                <div class="flex flex-col items-center gap-0.5">
                                    <span
                                        class="text-[14px] font-bold text-emerald-600">{{ $property->available_units_count }}</span>
                                    <span class="text-[10px] text-[#64748B] font-medium">Available</span>
                                </div>
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="text-[14px] font-bold text-amber-500">{{ $property->reserved_units_count }}</span>
                                    <span class="text-[10px] text-[#64748B] font-medium">Reserved</span>
                                </div>
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="text-[14px] font-bold text-[#EF4444]">{{ $property->occupied_units_count }}</span>
                                    <span class="text-[10px] text-[#64748B] font-medium">Occupied</span>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 pt-1 mt-auto">
                                <a href="{{ route('landlord.properties.units.index', $property) }}"
                                    class="flex-1 h-9 flex items-center justify-center gap-1.5 rounded-full border border-[#2AA7A1] text-[#2AA7A1] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                                    </svg>
                                    View Units
                                </a>
                                <a href="{{ route('properties.edit', $property) }}"
                                    class="h-9 px-3.5 flex items-center gap-1.5 rounded-full border border-[#64748B]/30 text-[#1F2937] text-[12px] font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z" />
                                    </svg>
                                    Edit
                                </a>
                                <a href="{{ route('landlord.properties.show', $property) }}"
                                    class="h-9 px-3.5 flex items-center gap-1.5 rounded-full border border-[#64748B]/30 text-[#1F2937] text-[12px] font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                    </svg>
                                    View
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-10">
                {{ $properties->links() }}
            </div>
        @endif

    </div>
@endsection