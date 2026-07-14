@extends('layouts.landlord')

@section('page-title', 'My Tenants')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-[20px] font-bold text-[#156F8C]">My Tenants</h1>
            <p class="text-[13px] text-[#64748B] mt-1">Tenants currently occupying units across your properties.</p>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4">
                <p class="text-[12px] text-[#64748B]">Total tenants</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $reservations->total() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4">
                <p class="text-[12px] text-[#64748B]">Properties with tenants</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $reservations->pluck('property_id')->unique()->count() }}</p>
            </div>
        </div>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('landlord.tenants.index') }}"
            class="flex flex-col sm:flex-row sm:items-center gap-2 mb-6">
            <div class="relative w-full sm:w-64">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />
                </svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tenants..."
                    class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:border-[#2AA7A1]">
            </div>

            <select name="property"
                class="w-full sm:w-56 pl-4 pr-8 py-2.5 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:border-[#2AA7A1]">
                <option value="">All properties</option>
                @foreach($properties as $property)
                    <option value="{{ $property->property_id }}" @selected(request('property') == $property->property_id)>
                        {{ $property->title }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                class="bg-[#156F8C] text-white rounded-xl px-4 py-2.5 text-[13px] font-semibold hover:brightness-95 transition">
                Filter
            </button>
        </form>

        {{-- Tenants table --}}
        @if($reservations->isEmpty())
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-12 flex flex-col items-center text-center">
                <svg class="w-10 h-10 text-[#64748B] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                <p class="text-[14px] font-semibold text-[#1F2937]">No tenants yet</p>
                <p class="text-[13px] text-[#64748B] mt-1">Tenants will appear here once units are occupied.</p>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-[#EEF8F8]">
                                <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Tenant</th>
                                <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Property / Unit</th>
                                <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Move-in date</th>
                                <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Contact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach($reservations as $reservation)
                                <tr class="hover:bg-[#F7FCFC] transition">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[12px] font-bold text-[#156F8C] shrink-0">
                                                {{ strtoupper(substr($reservation->tenant->first_name ?? '', 0, 1) . substr($reservation->tenant->last_name ?? '', 0, 1)) }}
                                            </div>
                                            <span class="text-[13px] font-medium text-[#1F2937]">
                                                {{ $reservation->tenant->first_name }} {{ $reservation->tenant->last_name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-[13px] text-[#1F2937]">
                                        {{ $reservation->property->title }}
                                        <span class="text-[#64748B]">&mdash; {{ $reservation->unit->unit_name ?? 'N/A' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-[13px] text-[#64748B]">
                                        {{ $reservation->target_move_in_date ? \Illuminate\Support\Carbon::parse($reservation->target_move_in_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-[13px] text-[#64748B]">
                                        {{ $reservation->tenant->contact_number ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $reservations->links() }}
            </div>
        @endif
    </div>
@endsection
