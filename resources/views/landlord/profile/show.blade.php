@extends($isOwner ? 'layouts.landlord' : 'layouts.app', $isOwner ? [] : ['searchBar' => false])
@section('content')
    @php
        $hasPayout = $isOwner && $user->hasPayoutDestination();
        $stats = [
            ['Properties', $properties->count(), 'Approved and listed'],
            ['Units', $totalUnits, 'Across all properties'],
            $isOwner
                ? ['Occupied', $occupiedUnits, $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100) . '% occupancy' : 'No units yet']
                : ['Available now', $availableUnits, 'Ready for tenants'],
        ];
    @endphp
    <div class="{{ auth()->user()->shellContainerClass($isOwner) }} mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10 min-h-[calc(100vh-72px)]">

        {{-- Hero profile card --}}
        <x-profile-hero :user="$user" avatarShape="circle" :show-contact="false" :show-bio="false"
            :subtitle="($business?->business_name ? $business->business_name . ' · ' : 'Independent landlord · ') . 'Member since ' . $user->created_at->format('F Y')">
            <x-slot:badges>
                <span class="bg-white/15 text-white text-[12px] font-medium px-2.5 py-1 rounded-full">Landlord</span>
                @if($verification && $verification->verification_status === 'Approved')
                    <span class="bg-white text-[#15803D] text-[12px] font-semibold px-2.5 py-1 rounded-full flex items-center gap-1">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Verified
                    </span>
                @endif
            </x-slot:badges>
            <x-slot:actions>
                @if($isOwner)
                    <a href="{{ route('landlord.profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-white/90 text-[13px] font-semibold text-[#060D26] transition-colors duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit profile
                    </a>
                @else
                    <div class="flex items-center gap-2">
                        @auth
                            <a href="{{ route('conversations.store') }}?landlord_id={{ $user->user_id }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold hover:bg-[#E96F4F] transition-colors duration-200">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                </svg>
                                Message
                            </a>
                            <a href="{{ route('reports.create', ['user_id' => $user->user_id]) }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-[13px] font-semibold text-white transition-colors duration-200">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                                </svg>
                                Report
                            </a>
                        @endauth
                    </div>
                @endif
            </x-slot:actions>
        </x-profile-hero>

        {{-- Profile details — the person first: every fact carries a visible label. --}}
        @php
            $visibilityLabels = [
                'public' => ['Public', 'Anyone can view this page'],
                'landlords_only' => ['Landlords only', 'Only other landlords can view this page'],
                'private' => ['Private', 'Only you can see this page'],
            ];
            [$visibilityTitle, $visibilitySub] = $visibilityLabels[$user->profile_visibility ?? 'private'] ?? $visibilityLabels['private'];
            $isVerified = $verification && $verification->verification_status === 'Approved';
        @endphp
        <x-card flush class="mb-6">
            <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                <h2 class="text-[17px] font-semibold text-[#060D26]">Profile details</h2>
                @if($isOwner)
                    <a href="{{ route('landlord.profile.edit') }}" class="text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">Edit</a>
                @endif
            </div>

            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-[#E2E4EC]">
                <div class="bg-white px-5 py-4 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Full name</dt>
                    <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26] truncate">{{ $user->first_name }} {{ $user->last_name }}</dd>
                </div>
                <div class="bg-white px-5 py-4 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Email</dt>
                    <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26] truncate" title="{{ $user->email }}">
                        <a href="mailto:{{ $user->email }}" class="hover:text-[#B35A3D] hover:underline">{{ $user->email }}</a>
                    </dd>
                    <p class="text-[12px] text-[#5B6A8E]">{{ $user->email_verified_at ? 'Email verified' : 'Email not verified' }}</p>
                </div>
                <div class="bg-white px-5 py-4 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Contact number</dt>
                    <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26] truncate">
                        @if($user->contact_number)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $user->contact_number) }}" class="hover:text-[#B35A3D] hover:underline">{{ $user->contact_number }}</a>
                        @else
                            <span class="font-normal text-[#5B6A8E]">Not provided</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-white px-5 py-4 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Member since</dt>
                    <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26]">{{ $user->created_at->format('F Y') }}</dd>
                </div>
                <div class="bg-white px-5 py-4 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Identity</dt>
                    <dd class="mt-1 text-[14.5px] font-semibold {{ $isVerified ? 'text-[#15803D]' : 'text-[#060D26]' }}">
                        {{ $isVerified ? 'Verified landlord' : ($verification ? ucfirst(strtolower($verification->verification_status)) : 'Not verified') }}
                    </dd>
                    @if($isVerified && $verification->reviewed_at)
                        <p class="text-[12px] text-[#5B6A8E]">Checked {{ $verification->reviewed_at->format('M d, Y') }}</p>
                    @endif
                </div>
                <div class="bg-white px-5 py-4 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Role</dt>
                    <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26]">Landlord</dd>
                </div>
                @if($isOwner)
                    <div class="bg-white px-5 py-4 min-w-0">
                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Profile visibility</dt>
                        <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26]">{{ $visibilityTitle }}</dd>
                        <p class="text-[12px] text-[#5B6A8E]">{{ $visibilitySub }}</p>
                    </div>
                    <div class="bg-white px-5 py-4 min-w-0">
                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Payout (GCash)</dt>
                        @if($hasPayout)
                            <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26] tabular-nums">&bull;&bull;&bull;&bull;{{ substr(preg_replace('/\D/', '', $user->gcash_number), -4) }}</dd>
                            <p class="text-[12px] text-[#5B6A8E] truncate">{{ $user->gcash_account_name }}</p>
                        @else
                            <dd class="mt-1 text-[14.5px] font-semibold text-[#B45309]">Not set</dd>
                            <p class="text-[12px] text-[#5B6A8E]"><a href="{{ route('landlord.profile.edit') }}" class="font-semibold text-[#B35A3D] hover:underline">Add GCash details</a> to get paid</p>
                        @endif
                    </div>
                @else
                    <div class="bg-white px-5 py-4 min-w-0">
                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Listings</dt>
                        <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26]">{{ $properties->count() }} {{ Str::plural('property', $properties->count()) }}</dd>
                    </div>
                    <div class="bg-white px-5 py-4 min-w-0">
                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Rating</dt>
                        <dd class="mt-1 text-[14.5px] font-semibold text-[#060D26]">
                            {{ $averageRating !== null ? number_format($averageRating, 1) . ' / 5' : 'No reviews yet' }}
                        </dd>
                    </div>
                @endif
            </dl>

            {{-- About + Business side by side --}}
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-[#E2E4EC] border-t border-[#E2E4EC]">
                <div class="px-5 sm:px-6 py-5">
                    <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">About</p>
                    @if($user->bio)
                        <p class="text-[14px] leading-relaxed text-[#060D26] whitespace-pre-line">{{ $user->bio }}</p>
                    @elseif($isOwner)
                        <p class="text-[14px] text-[#5B6A8E]">No bio yet. <a href="{{ route('landlord.profile.edit') }}" class="font-semibold text-[#B35A3D] hover:underline">Tell tenants a bit about yourself.</a></p>
                    @else
                        <p class="text-[14px] text-[#5B6A8E]">This landlord has not added a bio.</p>
                    @endif
                </div>
                <div class="px-5 sm:px-6 py-5">
                    <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">Business</p>
                    @if($business)
                        <div class="flex items-start gap-3">
                            @if($business->logo_url)
                                <img src="{{ $business->logo_url }}" alt="{{ $business->business_name }}" class="w-12 h-12 rounded-xl object-cover shrink-0">
                            @endif
                            <div class="min-w-0">
                                <p class="text-[14.5px] font-semibold text-[#060D26]">{{ $business->business_name }}</p>
                                @if($business->business_address)
                                    <p class="mt-0.5 text-[13.5px] text-[#5B6A8E]">{{ $business->business_address }}</p>
                                @endif
                                @if($business->contact_number)
                                    <p class="mt-0.5 text-[13.5px] text-[#5B6A8E]">
                                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $business->contact_number) }}" class="hover:text-[#B35A3D] hover:underline">{{ $business->contact_number }}</a>
                                    </p>
                                @endif
                            </div>
                        </div>
                        @if($business->description)
                            <p class="mt-3 text-[13.5px] leading-relaxed text-[#5B6A8E]">{{ $business->description }}</p>
                        @endif
                    @elseif($isOwner)
                        <p class="text-[14px] text-[#5B6A8E]">No business details yet. <a href="{{ route('landlord.profile.edit') }}" class="font-semibold text-[#B35A3D] hover:underline">Add them</a> if you rent under a business name.</p>
                    @else
                        <p class="text-[14px] text-[#5B6A8E]">Renting as an individual.</p>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Stats strip — plain labelled numbers; the hairline grid replaces four boxed tiles. --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-px mb-6 overflow-hidden rounded-2xl border border-[#E2E4EC] bg-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
            @foreach($stats as [$label, $value, $sub])
                <div class="bg-white p-4 sm:p-5">
                    <p class="text-[12px] font-semibold text-[#5B6A8E]">{{ $label }}</p>
                    <p class="mt-2 text-[28px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $value }}</p>
                    <p class="mt-2 text-[12.5px] text-[#5B6A8E]">{{ $sub }}</p>
                </div>
            @endforeach
            <div class="bg-white p-4 sm:p-5">
                <p class="text-[12px] font-semibold text-[#5B6A8E]">Rating as landlord</p>
                @if($averageRating !== null)
                    <p class="mt-2 flex items-baseline gap-1.5">
                        <span class="text-[28px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ number_format($averageRating, 1) }}</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#FBBF24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                    </p>
                    <p class="mt-2 text-[12.5px] text-[#5B6A8E]">From {{ $ratingCount }} {{ Str::plural('tenant', $ratingCount) }}</p>
                @else
                    <p class="mt-2 text-[28px] font-extrabold leading-none text-[#5B6A8E]">&mdash;</p>
                    <p class="mt-2 text-[12.5px] text-[#5B6A8E]">No reviews yet</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            {{-- Properties — up to 6 --}}
            <div class="lg:col-span-2">
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="text-[17px] font-semibold text-[#060D26]">Properties</h2>
                    <span class="text-[13px] text-[#5B6A8E]">{{ $properties->count() }} total</span>
                </div>
                @if($properties->count())
                    {{-- Compact list: one row per property, so six listings take a fraction of the old card grid's height. --}}
                    <x-card flush>
                        <ul class="divide-y divide-[#5B6A8E]/10">
                            @foreach($properties->take(6) as $property)
                                @php
                                    $thumb = $property->media->first();
                                    $availableCount = $property->units->where('availability_status', 'Available')->count();
                                @endphp
                                <li>
                                    <a href="{{ route('properties.show', $property) }}"
                                        class="group flex items-center gap-3 sm:gap-4 px-4 py-3 hover:bg-[#F7F8FC] transition-colors duration-200">
                                        <div class="w-16 h-16 sm:w-[72px] sm:h-[72px] rounded-xl overflow-hidden bg-[#ECEEF6] shrink-0 ring-1 ring-[#5B6A8E]/10">
                                            @if($thumb)
                                                <img src="{{ $thumb->media_url }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 motion-reduce:transition-none">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center" aria-hidden="true">
                                                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[15px] font-bold text-[#060D26] truncate group-hover:text-[#B35A3D] transition-colors duration-200">{{ $property->title }}</p>
                                            <p class="text-[13px] text-[#5B6A8E] truncate mt-0.5">{{ $property->property_type }} &middot; {{ $property->city_municipality }}</p>
                                            <p class="mt-1 text-[12.5px] font-medium sm:hidden {{ $availableCount > 0 ? 'text-[#15803D]' : 'text-[#5B6A8E]' }}">
                                                {{ $availableCount > 0 ? $availableCount . ' available' : 'Fully occupied' }}
                                            </p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-[15px] font-bold text-[#060D26] tabular-nums">
                                                @if($property->min_rental_fee)
                                                    &#8369;{{ number_format($property->min_rental_fee) }}
                                                @else
                                                    <span class="text-[13px] font-normal text-[#5B6A8E]">Not set</span>
                                                @endif
                                            </p>
                                            <p class="text-[12px] text-[#5B6A8E]">/ month</p>
                                            <p class="hidden sm:block mt-1 text-[12.5px] font-medium {{ $availableCount > 0 ? 'text-[#15803D]' : 'text-[#5B6A8E]' }}">
                                                {{ $availableCount > 0 ? $availableCount . ' available' : 'Fully occupied' }}
                                            </p>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>

                    @if($properties->count() > 6)
                        <div class="text-center mt-5">
                            <a href="{{ $isOwner ? route('landlord.properties.index') : route('properties.index', ['landlord' => $user->user_id]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-[#060D26]/25 text-[13.5px] font-semibold text-[#060D26] hover:border-[#060D26] hover:bg-[#F7F8FC] transition-colors duration-200">
                                Show all {{ $properties->count() }} properties
                            </a>
                        </div>
                    @endif
                @else
                    <x-card class="py-10 text-center">
                        <p class="text-[14px] font-semibold text-[#060D26]">No approved properties yet</p>
                        @if($isOwner)
                            <p class="mt-1 text-[13px] text-[#5B6A8E]">Listings appear here once an admin approves them.</p>
                        @endif
                    </x-card>
                @endif
            </div>

            {{-- Side column: who they are, how they get paid, what tenants say --}}
            <div class="space-y-5">

                {{-- Reviews received --}}
                <x-card>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <h2 class="text-[17px] font-semibold text-[#060D26]">Reviews</h2>
                        @if($averageRating !== null)
                            <x-star-rating :rating="$averageRating" :count="$ratingCount" />
                        @endif
                    </div>

                    @if($ratingCount > 0)
                        <div class="space-y-1.5 mb-4">
                            @for($star = 5; $star >= 1; $star--)
                                @php $starCount = $ratingDistribution[$star] ?? 0; @endphp
                                <div class="flex items-center gap-2 text-[12px] text-[#5B6A8E]">
                                    <span class="w-6 shrink-0 tabular-nums">{{ $star }}&#9733;</span>
                                    <div class="flex-1 h-1.5 rounded-full bg-[#E2E4EC] overflow-hidden">
                                        <div class="h-full bg-[#FBBF24] rounded-full" style="width: {{ round(($starCount / $ratingCount) * 100) }}%"></div>
                                    </div>
                                    <span class="w-5 text-right tabular-nums">{{ $starCount }}</span>
                                </div>
                            @endfor
                        </div>
                    @endif

                    @forelse($reviews->take(4) as $review)
                        @continue(!$review->property)
                        <div class="py-3 {{ !$loop->first ? 'border-t border-[#5B6A8E]/10' : '' }}">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-[14px] font-semibold text-[#060D26] truncate">{{ $review->tenant->first_name }} {{ $review->tenant->last_name }}</p>
                                <div class="flex gap-0.5 shrink-0" aria-label="{{ $review->rating }} out of 5 stars">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E4EC' }}" stroke="none" aria-hidden="true">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                            <a href="{{ route('properties.show', $review->property) }}" class="text-[12.5px] text-[#5B6A8E] hover:underline">{{ $review->property->title }}</a>
                            <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed line-clamp-3 mt-1">{{ $review->review_comment }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-[13.5px] text-[#5B6A8E]">No reviews received yet</p>
                    @endforelse

                    @if($isOwner && $ratingCount > 0)
                        <a href="{{ route('landlord.reviews.index') }}" class="mt-2 inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">
                            View all {{ $ratingCount }} {{ Str::plural('review', $ratingCount) }}
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    @endif
                </x-card>
            </div>
        </div>

    </div>
@endsection
