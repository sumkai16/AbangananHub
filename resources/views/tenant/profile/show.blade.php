@extends('layouts.app')

@section('hide_search', true)

@section('content')
    @php
        $liveReservations = $activeReservations->filter(fn ($r) => $r->property)->values();
        $liveReviews = $reviews->filter(fn ($r) => $r->property)->values();
        $tabs = ['overview' => 'Overview', 'reservations' => 'Reservations', 'reviews' => 'Reviews'];
        $subtitle = implode(' · ', array_filter([$user->email, $user->contact_number, 'Member since ' . $user->created_at->format('F Y')]));
        $starPath = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
        $statusColors = [
            'Inquiry' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
            'Under Negotiation' => 'bg-[#ECEEF6] text-[#060D26]',
            'Pending Rental Agreement' => 'bg-[#ECEEF6] text-[#060D26]',
            'Rental Agreement Signed' => 'bg-[#ECEEF6] text-[#060D26]',
            'Occupied' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
        ];
        $ctaClass = 'inline-flex h-10 items-center gap-1.5 px-4 rounded-xl bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200';
        $houseIcon = 'M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z';
    @endphp
    <div class="min-h-[calc(100vh-72px)] pb-10" x-data="{
            tab: @js(array_keys($tabs)).includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview',
            go(t) { this.tab = t; history.replaceState(null, '', '#' + t); }
        }">
        <x-profile-banner :user="$user" :subtitle="$subtitle" avatar-shape="square">
            <x-slot:badges>
                <span class="rounded-full bg-[#ECEEF6] px-2.5 py-1 text-[11.5px] font-semibold text-[#060D26]">Tenant</span>
                @if($user->hasRole('Landlord'))
                    <span class="inline-flex items-center gap-1 rounded-full bg-[#15803D] px-2.5 py-1 text-[11.5px] font-semibold text-white">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Verified landlord
                    </span>
                @endif
            </x-slot:badges>
            <x-slot:actions>
                <a href="{{ route('tenant.profile.edit') }}" class="inline-flex h-10 items-center gap-2 px-4 rounded-xl bg-white text-[13px] font-semibold text-[#060D26] hover:brightness-95 transition duration-200">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                    Edit profile
                </a>
            </x-slot:actions>

            {{-- Tabs --}}
            <div class="mt-5 border-b border-[#E2E4EC]">
                <div role="tablist" aria-label="Profile sections" class="flex gap-6 overflow-x-auto">
                    @foreach($tabs as $key => $label)
                        <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                            :aria-selected="tab === '{{ $key }}'" @click="go('{{ $key }}')"
                            :class="tab === '{{ $key }}' ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]'"
                            class="whitespace-nowrap border-b-2 pb-3 text-[13.5px] font-bold transition-colors duration-200 cursor-pointer">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="py-6">

                {{-- ── Overview ── --}}
                <div id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" x-show="tab === 'overview'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                    <div class="lg:col-span-2 space-y-5">

                        {{-- Numbers: how landlords rate them leads on navy. --}}
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="rounded-2xl bg-[#060D26] p-4 text-white">
                                <p class="text-[12px] font-semibold text-white/70">Rating as tenant</p>
                                @if($tenantRating['avg'] !== null)
                                    <p class="mt-2 flex items-baseline gap-1.5">
                                        <span class="text-[26px] font-extrabold leading-none tabular-nums">{{ number_format($tenantRating['avg'], 1) }}</span>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#FBBF24" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                                    </p>
                                    <p class="mt-2 text-[12px] text-white/65">From {{ $tenantRating['count'] }} {{ Str::plural('landlord', $tenantRating['count']) }}</p>
                                @else
                                    <p class="mt-2 text-[26px] font-extrabold leading-none text-white/60">&mdash;</p>
                                    <p class="mt-2 text-[12px] text-white/65">No ratings yet</p>
                                @endif
                            </div>
                            <a href="{{ route('favorites.index') }}" class="group rounded-2xl border border-[#E2E4EC] bg-white p-4 hover:border-[#5B6A8E]/40 transition-colors duration-200">
                                <p class="text-[12px] font-semibold text-[#5B6A8E]">Saved</p>
                                <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $favoritesCount }}</p>
                                <p class="mt-2 text-[12px] text-[#5B6A8E] group-hover:text-[#060D26] transition-colors duration-200">{{ Str::plural('Listing', $favoritesCount) }} saved</p>
                            </a>
                            <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                                <p class="text-[12px] font-semibold text-[#5B6A8E]">Reviews</p>
                                <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $reviews->count() }}</p>
                                <p class="mt-2 text-[12px] text-[#5B6A8E]">Written by you</p>
                            </div>
                            <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                                <p class="text-[12px] font-semibold text-[#5B6A8E]">Reservations</p>
                                <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $activeReservations->count() }}</p>
                                <p class="mt-2 text-[12px] text-[#5B6A8E]">Active now</p>
                            </div>
                        </div>

                        {{-- About + contact --}}
                        <x-card class="space-y-6">
                            <div>
                                <p class="text-[12px] font-semibold text-[#5B6A8E] mb-1.5">About</p>
                                @if($user->bio)
                                    <p class="text-[14px] leading-relaxed text-[#060D26] whitespace-pre-line">{{ $user->bio }}</p>
                                @else
                                    <p class="text-[14px] text-[#5B6A8E]">No bio yet. <a href="{{ route('tenant.profile.edit') }}" class="font-semibold text-[#B35A3D] hover:underline">Tell landlords a bit about yourself.</a></p>
                                @endif
                            </div>
                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5 border-t border-[#E2E4EC] pt-6">
                                <div class="min-w-0">
                                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Email</dt>
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26] truncate" title="{{ $user->email }}">
                                        <a href="mailto:{{ $user->email }}" class="hover:text-[#B35A3D] hover:underline">{{ $user->email }}</a>
                                    </dd>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $user->email_verified_at ? 'Email verified' : 'Email not verified' }}</p>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Contact number</dt>
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">
                                        @if($user->contact_number)
                                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $user->contact_number) }}" class="hover:text-[#B35A3D] hover:underline">{{ $user->contact_number }}</a>
                                        @else
                                            <span class="font-normal text-[#5B6A8E]">Not provided</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Member since</dt>
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $user->created_at->format('F Y') }}</dd>
                                </div>
                            </dl>
                        </x-card>
                    </div>

                    {{-- Right rail: glanceable summaries that hand off to their full tabs. --}}
                    <aside class="space-y-5">
                        <x-card>
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-[16px] font-semibold text-[#060D26]">Active reservations</h2>
                                @if($liveReservations->count())
                                    <button type="button" @click="go('reservations')" class="text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">View all</button>
                                @endif
                            </div>
                            @forelse($liveReservations->take(3) as $reservation)
                                @php $thumb = $reservation->property->media->first(); @endphp
                                <a href="{{ route('reservations.index') }}" class="group mt-3 flex items-center gap-3 rounded-xl p-2 -mx-2 hover:bg-[#F7F8FC] transition-colors duration-200">
                                    <div class="h-12 w-14 shrink-0 overflow-hidden rounded-lg bg-[#ECEEF6]">
                                        @if($thumb)
                                            <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="" class="h-full w-full object-cover">
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[13.5px] font-semibold text-[#060D26] truncate group-hover:text-[#B35A3D] transition-colors duration-200">{{ $reservation->property->title }}</p>
                                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusColors[$reservation->rental_status] ?? 'bg-[#F7F8FC] text-[#5B6A8E]' }}">{{ $reservation->rental_status }}</span>
                                    </div>
                                </a>
                            @empty
                                <p class="mt-3 text-[13.5px] text-[#5B6A8E]">No active reservations yet.</p>
                                <a href="{{ route('properties.index') }}" class="{{ $ctaClass }} mt-3">Browse properties</a>
                            @endforelse
                        </x-card>

                        <x-card>
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-[16px] font-semibold text-[#060D26]">Your reviews</h2>
                                @if($liveReviews->count())
                                    <button type="button" @click="go('reviews')" class="text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">See all</button>
                                @endif
                            </div>
                            @if($liveReviews->count())
                                @php $latest = $liveReviews->first(); @endphp
                                <figure class="mt-4 rounded-xl bg-[#F7F8FC] p-3.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <a href="{{ route('properties.show', $latest->property) }}" class="text-[13px] font-semibold text-[#060D26] hover:underline truncate">{{ $latest->property->title }}</a>
                                        <div class="flex gap-0.5 shrink-0" aria-label="{{ $latest->rating }} out of 5 stars">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i <= $latest->rating ? '#FBBF24' : '#E2E4EC' }}" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                                            @endfor
                                        </div>
                                    </div>
                                    <blockquote class="mt-2 text-[13px] leading-relaxed text-[#5B6A8E] line-clamp-3">{{ $latest->review_comment }}</blockquote>
                                    <figcaption class="mt-2 text-[12px] text-[#5B6A8E]">{{ $latest->created_at->format('M d, Y') }}</figcaption>
                                </figure>
                            @else
                                <p class="mt-3 text-[13.5px] text-[#5B6A8E]">No reviews written yet.</p>
                                <p class="mt-1 text-[12.5px] text-[#5B6A8E]">Reviews appear here once you've completed a stay.</p>
                            @endif
                        </x-card>
                    </aside>
                </div>

                {{-- ── Reservations ── --}}
                <div id="panel-reservations" role="tabpanel" aria-labelledby="tab-reservations" x-show="tab === 'reservations'" x-cloak>
                    <div class="flex items-baseline justify-between mb-4">
                        <h2 class="text-[16px] font-semibold text-[#060D26]">Active reservations</h2>
                        <a href="{{ route('reservations.index') }}" class="text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">Manage reservations</a>
                    </div>
                    @if($liveReservations->count())
                        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                            @foreach($liveReservations as $reservation)
                                @php $thumb = $reservation->property->media->first(); @endphp
                                <li>
                                    <a href="{{ route('reservations.index') }}" class="group block h-full overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white hover:border-[#5B6A8E]/40 transition-colors duration-200">
                                        <div class="h-36 overflow-hidden bg-[#ECEEF6]">
                                            @if($thumb)
                                                <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300 motion-reduce:transition-none">
                                            @else
                                                <div class="h-full w-full flex items-center justify-center" aria-hidden="true">
                                                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $houseIcon }}" /></svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="p-3.5">
                                            <p class="text-[14px] font-bold text-[#060D26] truncate group-hover:text-[#B35A3D] transition-colors duration-200">{{ $reservation->property->title }}</p>
                                            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] truncate">{{ $reservation->property->property_type }} &middot; {{ $reservation->property->city_municipality }}</p>
                                            <div class="mt-2 flex items-center justify-between gap-2">
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusColors[$reservation->rental_status] ?? 'bg-[#F7F8FC] text-[#5B6A8E]' }}">{{ $reservation->rental_status }}</span>
                                                @if($reservation->agreed_monthly_rent)
                                                    <span class="text-[13px] font-bold text-[#060D26] tabular-nums">&#8369;{{ number_format($reservation->agreed_monthly_rent) }}<span class="text-[11.5px] font-normal text-[#5B6A8E]">/mo</span></span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="rounded-2xl border border-[#E2E4EC] bg-white py-10 text-center">
                            <p class="text-[14px] font-semibold text-[#060D26]">No active reservations yet</p>
                            <p class="mt-1 text-[13px] text-[#5B6A8E]">Find a place and send an inquiry to get started.</p>
                            <a href="{{ route('properties.index') }}" class="{{ $ctaClass }} mt-4">Browse properties</a>
                        </div>
                    @endif
                </div>

                {{-- ── Reviews ── --}}
                <div id="panel-reviews" role="tabpanel" aria-labelledby="tab-reviews" x-show="tab === 'reviews'" x-cloak>
                    <x-card>
                        <h2 class="text-[16px] font-semibold text-[#060D26] mb-2">Reviews you've written</h2>
                        <div class="divide-y divide-[#5B6A8E]/10">
                            @forelse($liveReviews as $review)
                                <div class="py-4">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <a href="{{ route('properties.show', $review->property) }}" class="text-[14px] font-semibold text-[#060D26] hover:underline truncate">{{ $review->property->title }}</a>
                                        <div class="flex gap-0.5 shrink-0" aria-label="{{ $review->rating }} out of 5 stars">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E4EC' }}" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                                            @endfor
                                        </div>
                                    </div>
                                    <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed">{{ $review->review_comment }}</p>
                                    @if($review->landlord_reply)
                                        <div class="mt-2.5 pl-3 border-l-2 border-[#FF8A66]/40">
                                            <p class="text-[12px] font-semibold text-[#060D26]">Landlord reply</p>
                                            <p class="mt-0.5 text-[13px] text-[#5B6A8E] leading-relaxed">{{ $review->landlord_reply }}</p>
                                        </div>
                                    @endif
                                    <p class="mt-2 text-[12px] text-[#5B6A8E]">{{ $review->created_at->format('M d, Y') }}</p>
                                </div>
                            @empty
                                <div class="py-8 text-center">
                                    <p class="text-[14px] font-semibold text-[#060D26]">No reviews written yet</p>
                                    <p class="mt-1 text-[13px] text-[#5B6A8E]">Reviews appear here once you've completed a stay.</p>
                                </div>
                            @endforelse
                        </div>
                    </x-card>
                </div>
            </div>
        </x-profile-banner>
    </div>
@endsection
