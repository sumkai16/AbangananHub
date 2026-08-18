@extends('layouts.app')

@section('hide_search', true)

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-14 min-h-[calc(100vh-72px)]">


        {{-- Hero profile card --}}
        <x-profile-hero :user="$user" avatarShape="square">
            <x-slot:badges>
                <span class="bg-black/15 text-white text-[11px] font-medium px-2.5 py-1 rounded-full">Tenant</span>
                @if($user->hasRole('Landlord'))
                    <span class="bg-white text-[#156F8C] text-[11px] font-semibold px-2.5 py-1 rounded-full flex items-center gap-1">
                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Verified landlord
                    </span>
                @endif
            </x-slot:badges>
            <x-slot:actions>
                <a href="{{ route('tenant.profile.edit') }}" class="flex items-center gap-2 px-4 py-2 rounded-full bg-white hover:bg-white/90 text-[13px] font-semibold text-[#156F8C] transition-all duration-200">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                    Edit profile
                </a>
            </x-slot:actions>
        </x-profile-hero>

        {{-- Stats row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            {{-- Rating as a tenant — what landlords rated this tenant. --}}
            <x-card flush class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rating as tenant</span>
                    <div class="w-7 h-7 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#F59E0B">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                </div>
                @if($tenantRating['avg'] !== null)
                    <span class="text-xl font-extrabold text-[#1F2937]">{{ number_format($tenantRating['avg'], 1) }}</span>
                    <span class="text-[12px] text-[#64748B] font-normal ml-1">from {{ $tenantRating['count'] }} {{ Str::plural('landlord', $tenantRating['count']) }}</span>
                @else
                    <span class="text-xl font-extrabold text-[#94A3B8]">—</span>
                    <span class="text-[12px] text-[#64748B] font-normal ml-1">No ratings yet</span>
                @endif
            </x-card>
            <x-card flush class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Saved</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75c-1.5-3.5-6-4-8.25-1.5-2.25 2.5-1.5 6 1.5 8.5L12 20l6.75-6.25c3-2.5 3.75-6 1.5-8.5-2.25-2.5-6.75-2-8.25 1.5z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $favoritesCount }}</span>
                <span class="text-[12px] text-[#64748B] font-normal ml-1">{{ Str::plural('listing', $favoritesCount) }}</span>
            </x-card>
            <x-card flush class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reviews</span>
                    <div class="w-7 h-7 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#F59E0B">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $reviews->count() }}</span>
                <span class="text-[12px] text-[#64748B] font-normal ml-1">written</span>
            </x-card>
            <x-card flush class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reservations</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $activeReservations->count() }}</span>
                <span class="text-[12px] text-[#64748B] font-normal ml-1">active</span>
            </x-card>
        </div>

        {{-- Two-column: Reservations + Reviews --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Active reservations --}}
            <x-card flush class="p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                        </svg>
                    </div>
                    <h2 class="text-[15px] font-bold text-[#1F2937] flex-1">Active reservations</h2>
                    <a href="{{ route('reservations.index') }}" class="text-[12px] font-semibold text-[#156F8C] hover:underline">View all</a>
                </div>

                @forelse($activeReservations as $reservation)
                    @continue(!$reservation->property)
                    <a href="{{ route('reservations.index') }}"
                        class="group flex gap-3 items-center rounded-xl p-2.5 -mx-2.5 hover:bg-[#F7FCFC] transition-colors {{ !$loop->first ? 'mt-1.5' : '' }}">
                        @php $thumb = $reservation->property->media->first(); @endphp
                        @if($thumb)
                            <img src="{{ $thumb->media_url }}" alt="" class="w-16 h-14 rounded-lg object-cover flex-shrink-0">
                        @else
                            <div class="w-16 h-14 rounded-lg bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                                </svg>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-[#1F2937] truncate">{{ $reservation->property->title }}</p>
                            <div class="mt-1.5">
                                @php
                                    $statusColors = [
                                        'Inquiry' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                                        'Under Negotiation' => 'bg-[#EEF8F8] text-[#156F8C]',
                                        'Pending Rental Agreement' => 'bg-[#EEF8F8] text-[#156F8C]',
                                        'Rental Agreement Signed' => 'bg-[#EEF8F8] text-[#156F8C]',
                                        'Occupied' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
                                    ];
                                    $color = $statusColors[$reservation->rental_status] ?? 'bg-[#F7FCFC] text-[#64748B]';
                                @endphp
                                <span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-full {{ $color }}">{{ $reservation->rental_status }}</span>
                            </div>
                        </div>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.8" class="shrink-0 opacity-0 group-hover:opacity-100 -translate-x-1 group-hover:translate-x-0 transition-all">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                @empty
                    <div class="py-9 text-center">
                        <div class="w-11 h-11 rounded-xl bg-[#EEF8F8] flex items-center justify-center mx-auto mb-3">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                            </svg>
                        </div>
                        <p class="text-[13px] text-[#64748B] mb-3">No active reservations yet</p>
                        <a href="{{ route('properties.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[12.5px] font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all">
                            Browse properties
                        </a>
                    </div>
                @endforelse
            </x-card>

            {{-- Recent reviews --}}
            <x-card flush class="p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#F59E0B">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                    <h2 class="text-[15px] font-bold text-[#1F2937]">Reviews you've written</h2>
                </div>

                @forelse($reviews as $review)
                    @continue(!$review->property)
                    <div class="py-3 {{ !$loop->first ? 'border-t border-[#64748B]/10' : '' }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <a href="{{ route('properties.show', $review->property) }}"
                                class="text-[13px] font-semibold text-[#1F2937] hover:text-[#156F8C] transition-colors truncate">{{ $review->property->title }}</a>
                            <div class="flex gap-0.5 flex-shrink-0 ml-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E8F0' }}" stroke="none">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                @endfor
                            </div>
                        </div>
                        <p class="text-[13px] text-[#64748B] leading-relaxed line-clamp-2">{{ $review->review_comment }}</p>
                        @if($review->landlord_reply)
                            <div class="mt-2 pl-3 border-l-2 border-[#2AA7A1]/30">
                                <p class="text-[11px] font-semibold text-[#156F8C]">Landlord reply</p>
                                <p class="text-[12px] text-[#64748B] leading-relaxed mt-0.5">{{ $review->landlord_reply }}</p>
                            </div>
                        @endif
                        <p class="text-[11px] text-[#64748B]/70 mt-1.5">{{ $review->created_at->format('M d, Y') }}</p>
                    </div>
                @empty
                    <div class="py-9 text-center">
                        <div class="w-11 h-11 rounded-xl bg-[#FBBF24]/[0.10] flex items-center justify-center mx-auto mb-3">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#F59E0B" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                        </div>
                        <p class="text-[13px] text-[#64748B]">No reviews written yet</p>
                        <p class="text-[11.5px] text-[#64748B]/80 mt-1">Reviews appear here once you've completed a stay.</p>
                    </div>
                @endforelse
            </x-card>
        </div>

    </div>
@endsection
