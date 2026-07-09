@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-1.5 text-sm text-[#64748B] mb-2">
            <a href="{{ route('landlord.properties.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">Properties</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.show', $property) }}"
                class="hover:text-[#1F2937] transition-colors duration-200">{{ $property->title }}</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">Units</span>
        </div>

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Units</h1>
                <p class="text-sm text-[#64748B] mt-1">Manage all units and their availability for {{ $property->title }}.
                </p>
            </div>
            <a href="{{ route('landlord.properties.units.create', $property) }}"
                class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-full bg-[#1F2937] hover:brightness-95 text-white text-sm font-semibold shadow-sm transition-all duration-200 shrink-0">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add New Unit
            </a>
        </div>

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
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm font-medium">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Stat cards --}}
        @php
            $total = $units->count();
            $available = $units->where('availability_status', 'Available')->count();
            $reserved = $units->where('availability_status', 'Reserved')->count();
            $occupied = $units->where('availability_status', 'Occupied')->count();
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <div class="flex items-center gap-2 mb-1">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="text-[#64748B]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#64748B]">Total Units</span>
                </div>
                <span class="text-xl font-bold text-[#1F2937]">{{ $total }}</span>
                <p class="text-[10px] text-[#64748B] mt-0.5">All units in this property</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <div class="flex items-center gap-2 mb-1">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="text-emerald-600">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#64748B]">Available</span>
                </div>
                <span class="text-xl font-bold text-emerald-600">{{ $available }}</span>
                <p class="text-[10px] text-[#64748B] mt-0.5">{{ $total > 0 ? round($available / $total * 100) : 0 }}% of
                    total units</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <div class="flex items-center gap-2 mb-1">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="text-amber-500">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#64748B]">Reserved</span>
                </div>
                <span class="text-xl font-bold text-amber-500">{{ $reserved }}</span>
                <p class="text-[10px] text-[#64748B] mt-0.5">{{ $total > 0 ? round($reserved / $total * 100) : 0 }}% of
                    total units</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <div class="flex items-center gap-2 mb-1">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="text-[#EF4444]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#64748B]">Occupied</span>
                </div>
                <span class="text-xl font-bold text-[#EF4444]">{{ $occupied }}</span>
                <p class="text-[10px] text-[#64748B] mt-0.5">{{ $total > 0 ? round($occupied / $total * 100) : 0 }}% of
                    total units</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap items-center gap-2 mb-6">
            <div class="relative flex-1 min-w-[200px] max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B]" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search units by name or number..."
                    class="pl-9 pr-4 h-10 w-full rounded-full border border-[#64748B]/30 bg-white text-[13px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
            </div>

            <select name="status" onchange="this.form.submit()"
                class="h-10 pl-4 pr-8 rounded-full border border-[#64748B]/30 bg-white text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition">
                <option value="">All Status</option>
                <option value="Available" @selected(request('status') === 'Available')>Available</option>
                <option value="Reserved" @selected(request('status') === 'Reserved')>Reserved</option>
                <option value="Occupied" @selected(request('status') === 'Occupied')>Occupied</option>
            </select>

            <button type="submit"
                class="h-10 px-5 rounded-full bg-[#1F2937] text-white text-[13px] font-semibold hover:brightness-95 transition-all duration-200">
                Filter
            </button>

            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('landlord.properties.units.index', $property) }}"
                    class="h-10 px-4 rounded-full border border-[#64748B]/30 text-[13px] text-[#64748B] hover:text-[#1F2937] hover:border-[#64748B]/60 transition-colors duration-200 inline-flex items-center gap-1.5">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    Clear
                </a>
            @endif
        </form>

        @php
            $filtered = $units
                ->when(request('search'), fn($c, $s) => $c->filter(fn($u) => str_contains(strtolower($u->unit_label), strtolower($s))))
                ->when(request('status'), fn($c, $s) => $c->where('availability_status', $s));
        @endphp

        {{-- Empty state --}}
        @if($filtered->isEmpty())
            <div
                class="rounded-2xl border border-dashed border-[#64748B]/30 bg-white flex flex-col items-center justify-center py-16 text-center">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"
                    class="text-[#64748B]/50 mb-3">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                </svg>
                <p class="text-sm font-semibold text-[#1F2937]">No units found</p>
                <p class="text-xs text-[#64748B] mt-1">Try adjusting your search or filters.</p>
            </div>

            {{-- Table --}}
        @else
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-[#64748B]/15 text-left">
                                <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Unit
                                </th>
                                <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Monthly
                                    Rent</th>
                                <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Status
                                </th>
                                <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Capacity
                                </th>
                                <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">
                                    Verification</th>
                                <th
                                    class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider text-right">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#64748B]/10">
                            @foreach($filtered as $unit)
                                @php
                                    $thumb = $unit->media->first();
                                    [$avBg] = match ($unit->availability_status) {
                                        'Available' => ['bg-emerald-50 text-emerald-700 ring-emerald-200'],
                                        'Reserved' => ['bg-amber-50 text-amber-600 ring-amber-200'],
                                        'Occupied' => ['bg-red-50 text-red-600 ring-red-200'],
                                        default => ['bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20'],
                                    };
                                    [$vrBg] = match ($unit->verification_status) {
                                        'Approved' => ['bg-emerald-50 text-emerald-700'],
                                        'Pending' => ['bg-amber-50 text-amber-600'],
                                        'Rejected' => ['bg-red-50 text-red-600'],
                                        default => ['bg-[#EEF8F8] text-[#64748B]'],
                                    };
                                @endphp
                                <tr class="hover:bg-[#EEF8F8]/30 transition-colors duration-150">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-lg overflow-hidden bg-[#EEF8F8] shrink-0">
                                                @if($thumb)
                                                    <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}"
                                                        class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center">
                                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor" stroke-width="1.5" class="text-[#64748B]/40">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-[#1F2937] leading-tight">{{ $unit->unit_label }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-[#1F2937]">
                                        ₱{{ number_format($unit->rental_fee, 0) }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ $avBg }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                            {{ $unit->availability_status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-[#1F2937]">
                                        {{ $unit->occupancy_limit }} {{ Str::plural('person', $unit->occupancy_limit) }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="text-[11px] font-medium px-2.5 py-1 rounded-full {{ $vrBg }}">
                                            {{ $unit->verification_status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('landlord.properties.units.edit', [$property, $unit]) }}"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#64748B]/30 text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200">
                                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                    stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z" />
                                                </svg>
                                            </a>
                                            <form method="POST"
                                                action="{{ route('landlord.properties.units.destroy', [$property, $unit]) }}"
                                                onsubmit="return confirm('Remove {{ addslashes($unit->unit_label) }}? This cannot be undone.')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition-colors duration-200">
                                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3 text-xs text-[#64748B]">
                Showing {{ $filtered->count() }} of {{ $total }} units
            </div>
        @endif

    </div>
@endsection