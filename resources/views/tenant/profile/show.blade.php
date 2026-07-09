@extends('layouts.app')

@section('hide_search', true)

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8">

        {{-- Success flash --}}
        @if(session('success'))
            <div
                class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-[13px] font-medium flex items-center justify-between shadow-sm mb-6">
                <span>{{ session('success') }}</span>
                <button class="opacity-60 hover:opacity-100 pl-3 focus:outline-none"
                    onclick="this.parentElement.remove()">&#10005;</button>
            </div>
        @endif

        {{-- Profile header card --}}
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-6 sm:p-8 mb-6">
            <div class="flex flex-col sm:flex-row gap-6 items-start">

                {{-- Avatar --}}
                <div class="flex-shrink-0">
                    @if($user->profile_picture)
                        <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}"
                            class="w-24 h-24 rounded-full object-cover">
                    @else
                        <div
                            class="w-24 h-24 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[32px] font-bold">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-[22px] font-bold text-[#1F2937] leading-tight">{{ $user->first_name }}
                                {{ $user->last_name }}</h1>
                            <div class="flex items-center gap-2 mt-2 text-[13px] text-[#64748B] flex-wrap">
                                <span class="flex items-center gap-1.5">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    {{ $user->email }}
                                </span>
                                @if($user->contact_number)
                                    <span class="text-[#E2E8F0]">|</span>
                                    <span class="flex items-center gap-1.5">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                        </svg>
                                        {{ $user->contact_number }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Edit button --}}
                        <a href="{{ route('tenant.profile.edit') }}"
                            class="flex-shrink-0 flex items-center gap-2 px-4 py-2 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] hover:brightness-95 bg-white transition-all">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Edit profile
                        </a>
                    </div>

                    {{-- Badges --}}
                    <div class="flex items-center gap-2 mt-4 flex-wrap">
                        <span
                            class="bg-[#EEF8F8] text-[#156F8C] text-[12px] font-medium px-3 py-1 rounded-full">Tenant</span>
                        @if($user->hasRole('Landlord'))
                            <span
                                class="bg-green-50 text-green-700 text-[12px] font-medium px-3 py-1 rounded-full flex items-center gap-1">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Verified landlord
                            </span>
                        @endif
                        <span class="text-[12px] text-[#64748B] ml-1 flex items-center gap-1.5">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            Member since {{ $user->created_at->format('F Y') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Bio --}}
            @if($user->bio)
                <div class="mt-6 pt-6 border-t border-[#E2E8F0]">
                    <p class="text-[13px] font-semibold text-[#64748B] mb-1">About</p>
                    <p class="text-[14px] text-[#1F2937] leading-relaxed">{{ $user->bio }}</p>
                </div>
            @endif
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4 text-center">
                <p class="text-[13px] text-[#64748B] mb-1">Saved listings</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $favoritesCount }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4 text-center">
                <p class="text-[13px] text-[#64748B] mb-1">Reviews written</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $reviews->count() }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4 text-center">
                <p class="text-[13px] text-[#64748B] mb-1">Active reservations</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $activeReservations->count() }}</p>
            </div>
        </div>

        {{-- Two-column: Reviews + Reservations --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Recent reviews --}}
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-bold text-[#1F2937]">Recent reviews</h2>
                </div>

                @forelse($reviews as $review)
                    @continue(!$review->property)
                    <div class="py-3 {{ !$loop->first ? 'border-t border-[#E2E8F0]' : '' }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <a href="{{ route('properties.show', $review->property) }}"
                                class="text-[13px] font-semibold text-[#1F2937] hover:text-[#156F8C] transition-colors">{{ $review->property->title }}</a>
                            <div class="flex gap-0.5 flex-shrink-0 ml-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg width="13" height="13" viewBox="0 0 24 24"
                                        fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E8F0' }}" stroke="none">
                                        <path
                                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                @endfor
                            </div>
                        </div>
                        <p class="text-[13px] text-[#64748B] leading-relaxed line-clamp-2">{{ $review->review_comment }}</p>
                        <p class="text-[11px] text-[#64748B]/70 mt-1.5">{{ $review->created_at->format('M d, Y') }}</p>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#E2E8F0" stroke-width="1.5"
                            class="mx-auto mb-2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                        </svg>
                        <p class="text-[13px] text-[#64748B]">No reviews written yet</p>
                    </div>
                @endforelse
            </div>

            {{-- Active reservations --}}
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-bold text-[#1F2937]">Active reservations</h2>
                    <a href="{{ route('reservations.index') }}"
                        class="text-[12px] font-semibold text-[#156F8C] hover:underline">View all</a>
                </div>

                @forelse($activeReservations as $reservation)
                    @continue(!$reservation->property)
                    <div
                        class="p-3 border border-[#E2E8F0] rounded-lg flex gap-3 items-center {{ !$loop->first ? 'mt-3' : '' }}">
                        @php $thumb = $reservation->property->media->first(); @endphp
                        @if($thumb)
                            <img src="{{ $thumb->media_url }}" alt="" class="w-16 h-12 rounded-md object-cover flex-shrink-0">
                        @else
                            <div class="w-16 h-12 rounded-md bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                                </svg>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-[#1F2937] truncate">{{ $reservation->property->title }}</p>
                            <div class="mt-1">
                                @php
                                    $statusColors = [
                                        'Inquiry' => 'bg-amber-50 text-amber-700',
                                        'Under Negotiation' => 'bg-blue-50 text-blue-700',
                                        'Pending Rental Agreement' => 'bg-purple-50 text-purple-700',
                                        'Rental Agreement Signed' => 'bg-teal-50 text-teal-700',
                                        'Occupied' => 'bg-green-50 text-green-700',
                                    ];
                                    $color = $statusColors[$reservation->rental_status] ?? 'bg-gray-50 text-gray-600';
                                @endphp
                                <span
                                    class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-full {{ $color }}">{{ $reservation->rental_status }}</span>
                            </div>
                        </div>
                        <a href="{{ route('reservations.index') }}" class="flex-shrink-0">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>
                @empty
                    <div class="py-8 text-center bg-[#F7FCFC] rounded-lg">
                        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#E2E8F0" stroke-width="1.5"
                            class="mx-auto mb-2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <p class="text-[13px] text-[#64748B]">No active reservations</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
@endsection