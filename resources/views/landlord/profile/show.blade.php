@extends($isOwner ? 'layouts.landlord' : 'layouts.app', $isOwner ? [] : ['searchBar' => false])
@section('content')
    <div class="{{ auth()->user()->shellContainerClass($isOwner) }} mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10 min-h-[calc(100vh-72px)]">

        {{-- Hero profile card --}}
        <x-profile-hero :user="$user" avatarShape="circle">
            <x-slot:badges>
                <span class="bg-black/15 text-white text-[11px] font-medium px-2.5 py-1 rounded-full">Landlord</span>
                @if($verification && $verification->verification_status === 'Approved')
                    <span class="bg-white text-[#15803D] text-[11px] font-semibold px-2.5 py-1 rounded-full flex items-center gap-1">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Verified
                    </span>
                @endif
            </x-slot:badges>
            <x-slot:actions>
                @if($isOwner)
                    <a href="{{ route('landlord.profile.edit') }}" class="flex items-center gap-2 px-4 py-2 rounded-full bg-white hover:bg-white/90 text-[13px] font-semibold text-[#156F8C] transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit profile
                    </a>
                @else
                    <div class="flex items-center gap-2">
                        @auth
                            <a href="{{ route('conversations.store') }}?landlord_id={{ $user->user_id }}" class="flex items-center gap-2 px-4 py-2 rounded-full bg-white text-[#156F8C] text-[13px] font-semibold hover:bg-white/90 transition-all">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                </svg>
                                Message
                            </a>
                            <a href="{{ route('reports.create', ['user_id' => $user->user_id]) }}" class="flex items-center gap-2 px-4 py-2 rounded-full bg-black/15 hover:bg-black/25 text-[13px] font-semibold text-white transition-all">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                                </svg>
                                Report
                            </a>
                        @endauth
                    </div>
                @endif
            </x-slot:actions>
        </x-profile-hero>

        {{-- Business info card --}}
        @if($business)
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 mb-5">
                <div class="flex items-center gap-3 mb-3">
                    @if($business->logo_url)
                        <img src="{{ $business->logo_url }}" alt="{{ $business->business_name }}" class="w-11 h-11 rounded-xl object-cover">
                    @else
                        <div class="w-11 h-11 rounded-xl bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                        </div>
                    @endif
                    <div>
                        <h2 class="text-[15px] font-bold text-[#1F2937]">{{ $business->business_name }}</h2>
                        @if($business->business_address)
                            <p class="text-[12px] text-[#64748B] mt-0.5">{{ $business->business_address }}</p>
                        @endif
                    </div>
                </div>
                @if($business->description)
                    <p class="text-[13px] text-[#64748B] leading-relaxed">{{ $business->description }}</p>
                @endif
                @if($business->contact_number)
                    <div class="flex items-center gap-1.5 mt-3 text-[12px] text-[#64748B]">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                        {{ $business->contact_number }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Stats row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Properties</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $properties->count() }}</span>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Units</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $totalUnits }}</span>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EF4444]/[0.07] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#DC2626]">{{ $occupiedUnits }}</span>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rating as landlord</span>
                    <div class="w-7 h-7 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#F59E0B">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                </div>
                @if($averageRating !== null)
                    <span class="text-xl font-extrabold text-[#1F2937]">{{ number_format($averageRating, 1) }}</span>
                    <span class="text-[12px] text-[#64748B] font-normal ml-1">from {{ $ratingCount }} {{ Str::plural('tenant', $ratingCount) }}</span>
                @else
                    <span class="text-xl font-extrabold text-[#94A3B8]">—</span>
                    <span class="text-[12px] text-[#64748B] font-normal ml-1">No reviews yet</span>
                @endif
            </div>
        </div>

        {{-- Properties + Reviews side by side --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- Properties grid — limited to 6 --}}
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-bold text-[#1F2937]">Properties</h2>
                    <span class="text-[12px] text-[#64748B]">{{ $properties->count() }} total</span>
                </div>
                @if($properties->count())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($properties->take(6) as $property)
                            <a href="{{ route('properties.show', $property) }}"
                                class="group block rounded-2xl overflow-hidden bg-white ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] hover:shadow-[0_8px_24px_rgba(15,23,42,0.1)] hover:-translate-y-0.5 transition-all duration-300">
                                @php $thumb = $property->media->first(); @endphp
                                <div class="relative aspect-[16/10] overflow-hidden bg-[#EEF8F8]">
                                    @if($thumb)
                                        <img src="{{ $thumb->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-500 ease-out">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="p-3.5">
                                    <p class="text-[13.5px] font-bold text-[#1F2937] truncate">{{ $property->title }}</p>
                                    <p class="text-[12px] text-[#64748B] truncate mt-0.5">{{ $property->address }}</p>
                                    <p class="text-[13px] font-bold text-[#1F2937] mt-1.5">
                                        @if($property->min_rental_fee)
                                            ₱{{ number_format($property->min_rental_fee) }}<span class="text-[#64748B] font-normal text-[12px]"> / month</span>
                                        @else
                                            <span class="text-[#64748B] font-normal text-[12px]">Price not set</span>
                                        @endif
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if($properties->count() > 6)
                        <div class="text-center mt-5">
                           <a href="{{ $isOwner ? route('landlord.properties.index') : route('properties.index', ['landlord' => $user->user_id]) }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full text-[13px] font-semibold text-[#1F2937] bg-white ring-1 ring-[#64748B]/15 hover:bg-[#EEF8F8] transition-all">
                                Show all {{ $properties->count() }} properties
                            </a>
                        </div>
                    @endif
                @else
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] py-10 text-center">
                        <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center mx-auto mb-3">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                            </svg>
                        </div>
                        <p class="text-[13px] text-[#64748B]">No approved properties yet</p>
                    </div>
                @endif
            </div>

            {{-- Reviews received --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[15px] font-bold text-[#1F2937]">Reviews</h2>
                    @if($averageRating !== null)
                        <x-star-rating :rating="$averageRating" :count="$ratingCount" />
                    @endif
                </div>

                @if($ratingCount > 0)
                    {{-- Rating distribution --}}
                    <div class="space-y-1 mb-4">
                        @for($star = 5; $star >= 1; $star--)
                            @php $starCount = $ratingDistribution[$star] ?? 0; @endphp
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-[#64748B] w-2.5 shrink-0">{{ $star }}</span>
                                <div class="flex-1 h-1.5 rounded-full bg-[#E2E8F0] overflow-hidden">
                                    <div class="h-full bg-[#FBBF24] rounded-full" style="width: {{ $ratingCount > 0 ? round(($starCount / $ratingCount) * 100) : 0 }}%"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                @endif

                @forelse($reviews->take(4) as $review)
                    @continue(!$review->property)
                    <div class="py-3 {{ !$loop->first ? 'border-t border-[#64748B]/10' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-[13px] font-semibold text-[#1F2937] truncate">{{ $review->tenant->first_name }} {{ $review->tenant->last_name }}</p>
                            <div class="flex gap-0.5 flex-shrink-0 ml-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E8F0' }}" stroke="none">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                @endfor
                            </div>
                        </div>
                        <a href="{{ route('properties.show', $review->property) }}" class="text-[11px] text-[#64748B] hover:underline">{{ $review->property->title }}</a>
                        <p class="text-[13px] text-[#64748B] leading-relaxed line-clamp-2 mt-1">{{ $review->review_comment }}</p>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <div class="w-11 h-11 rounded-xl bg-[#EEF8F8] flex items-center justify-center mx-auto mb-2">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                        </div>
                        <p class="text-[13px] text-[#64748B]">No reviews received yet</p>
                    </div>
                @endforelse

                @if($isOwner && $ratingCount > 4)
                    <a href="{{ route('landlord.reviews.index') }}" class="flex items-center gap-1.5 text-[12.5px] font-semibold text-[#156F8C] hover:underline mt-2">
                        View all {{ $ratingCount }} reviews
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>

    </div>
@endsection
