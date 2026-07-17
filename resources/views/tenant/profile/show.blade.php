@extends('layouts.app')

@section('hide_search', true)

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-14 min-h-[calc(100vh-72px)]">

        {{-- Success flash --}}
        @if(session('success'))
            <div class="bg-[#EEF8F8] text-[#1F2937] rounded-xl px-4 py-3 text-[13px] font-medium flex items-center justify-between shadow-sm mb-5">
                <span class="flex items-center gap-2">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 text-[#2AA7A1]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    {{ session('success') }}
                </span>
                <button class="opacity-60 hover:opacity-100 pl-3 focus:outline-none" onclick="this.parentElement.remove()">&#10005;</button>
            </div>
        @endif

        {{-- Hero profile card --}}
        <div class="relative overflow-hidden rounded-[28px] bg-[#2AA7A1] p-6 sm:p-8 mb-6 shadow-[0_12px_36px_rgba(21,111,140,0.28)]">
            <div class="absolute inset-0 opacity-[0.08] pointer-events-none"
                style="background-image: radial-gradient(circle at 1px 1px, white 1.3px, transparent 0); background-size: 22px 22px;"></div>
            <div class="absolute -top-20 -right-16 w-64 h-64 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col sm:flex-row gap-6 items-start">
                {{-- Avatar --}}
                <div class="flex-shrink-0">
                    @if($user->profile_picture)
                        <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl object-cover ring-4 ring-white/20">
                    @else
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-white/15 ring-4 ring-white/20 flex items-center justify-center text-white text-[28px] font-black">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                                <span class="inline-flex items-center gap-1 bg-white/15 text-white text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full">Tenant</span>
                                @if($user->hasRole('Landlord'))
                                    <span class="inline-flex items-center gap-1 bg-white text-[#156F8C] text-[11px] font-bold px-2.5 py-1 rounded-full">
                                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Verified landlord
                                    </span>
                                @endif
                            </div>
                            <h1 class="text-[24px] sm:text-[28px] font-black tracking-tight text-white leading-tight">{{ $user->first_name }} {{ $user->last_name }}</h1>
                            <div class="flex items-center gap-2 mt-2 text-[13px] text-white/85 flex-wrap">
                                <span class="flex items-center gap-1.5">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    {{ $user->email }}
                                </span>
                                @if($user->contact_number)
                                    <span class="text-white/40">|</span>
                                    <span class="flex items-center gap-1.5">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                        </svg>
                                        {{ $user->contact_number }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <a href="{{ route('tenant.profile.edit') }}" class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-full bg-black/15 hover:bg-black/25 text-[13px] font-semibold text-white transition-all duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Edit profile
                        </a>
                    </div>

                    <p class="text-[12px] text-white/70 mt-3 flex items-center gap-1.5">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Member since {{ $user->created_at->format('F Y') }}
                    </p>
                </div>
            </div>

            @if($user->bio)
                <div class="relative mt-6 pt-6 border-t border-white/10">
                    <p class="text-[13px] font-semibold text-white/75 mb-1">About</p>
                    <p class="text-[14px] text-white/85 leading-relaxed">{{ $user->bio }}</p>
                </div>
            @endif
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
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
            </div>
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reviews</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#F59E0B">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $reviews->count() }}</span>
                <span class="text-[12px] text-[#64748B] font-normal ml-1">written</span>
            </div>
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
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
            </div>
        </div>

        {{-- Two-column: Reservations + Reviews --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Active reservations --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-5">
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
                                        'Inquiry' => 'bg-amber-50 text-amber-700',
                                        'Under Negotiation' => 'bg-blue-50 text-blue-700',
                                        'Pending Rental Agreement' => 'bg-purple-50 text-purple-700',
                                        'Rental Agreement Signed' => 'bg-teal-50 text-teal-700',
                                        'Occupied' => 'bg-green-50 text-green-700',
                                    ];
                                    $color = $statusColors[$reservation->rental_status] ?? 'bg-gray-50 text-gray-600';
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
            </div>

            {{-- Recent reviews --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
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
                        <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center mx-auto mb-3">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#F59E0B" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                        </div>
                        <p class="text-[13px] text-[#64748B]">No reviews written yet</p>
                        <p class="text-[11.5px] text-[#64748B]/80 mt-1">Reviews appear here once you've completed a stay.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
@endsection
