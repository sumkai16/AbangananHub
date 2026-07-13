@extends('layouts.app', ['searchBar' => false])

@section('title', 'Profile')

@section('content')
    <div class="max-w-[900px] mx-auto px-4 sm:px-6 lg:px-8 py-10">

        {{-- Profile header card --}}
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-6 sm:p-8 mb-6 flex flex-col sm:flex-row sm:items-center gap-5">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[#2AA7A1] text-2xl font-bold text-white overflow-hidden">
                @if($user->profile_picture)
                    <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="h-full w-full object-cover">
                @else
                    {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-[22px] font-bold tracking-tight text-[#1F2937]">
                        {{ $user->first_name }} {{ $user->last_name }}
                    </h1>
                    @foreach($roles as $role)
                        <span class="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C] border border-[#2AA7A1]/20">
                            {{ $role }}
                        </span>
                    @endforeach
                    @if($user->rentalBusiness)
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Verified Landlord
                        </span>
                    @endif
                </div>
                <p class="text-sm text-[#64748B] mt-1">{{ $user->email }}</p>
                @if($user->contact_number)
                    <p class="text-sm text-[#64748B] mt-0.5">{{ $user->contact_number }}</p>
                @endif
                <p class="text-xs text-[#64748B]/80 mt-1.5">Member since {{ $user->created_at->format('F Y') }}</p>
                @if($user->bio)
                    <p class="text-sm text-[#1F2937] mt-3 leading-relaxed">{{ $user->bio }}</p>
                @endif
            </div>
            <a href="{{ route('profile.edit') }}"
                class="inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-lg border border-[#E2E8F0] text-[13px] font-semibold text-[#1F2937] hover:bg-[#EEF8F8] transition-colors shrink-0 self-start sm:self-center">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Account Settings
            </a>
        </div>

        {{-- Activity stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-4 text-center">
                <p class="text-2xl font-extrabold text-[#1F2937]">{{ $reservationCount }}</p>
                <p class="text-[12px] text-[#64748B] mt-0.5">Reservations</p>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-4 text-center">
                <p class="text-2xl font-extrabold text-[#1F2937]">{{ $favoriteCount }}</p>
                <p class="text-[12px] text-[#64748B] mt-0.5">Favorites</p>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-4 text-center">
                <p class="text-2xl font-extrabold text-[#1F2937]">{{ $reviewCount }}</p>
                <p class="text-[12px] text-[#64748B] mt-0.5">Reviews written</p>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-4 text-center">
                <p class="text-2xl font-extrabold text-[#1F2937]">{{ $propertyCount }}</p>
                <p class="text-[12px] text-[#64748B] mt-0.5">Properties listed</p>
            </div>
        </div>

        {{-- Quick links --}}
        <div class="bg-white border border-[#E2E8F0] rounded-2xl overflow-hidden">
            @if($roles->contains('Tenant'))
                <a href="{{ route('reservations.index') }}"
                    class="flex items-center justify-between px-5 py-4 border-b border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors">
                    <span class="text-[13.5px] font-semibold text-[#1F2937]">My Reservations</span>
                    <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endif
            @if($roles->contains('Landlord'))
                <a href="{{ route('landlord.properties.index') }}"
                    class="flex items-center justify-between px-5 py-4 border-b border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors">
                    <span class="text-[13.5px] font-semibold text-[#1F2937]">My Properties</span>
                    <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endif
            <a href="{{ route('favorites.index') }}"
                class="flex items-center justify-between px-5 py-4 border-b border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors">
                <span class="text-[13.5px] font-semibold text-[#1F2937]">Saved Properties</span>
                <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('profile.edit') }}"
                class="flex items-center justify-between px-5 py-4 hover:bg-[#EEF8F8] transition-colors">
                <span class="text-[13.5px] font-semibold text-[#1F2937]">Account Settings</span>
                <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

    </div>
@endsection
