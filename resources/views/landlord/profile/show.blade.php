@extends($isOwner ? 'layouts.landlord' : 'layouts.app', $isOwner ? [] : ['searchBar' => false])
@section('content')
    @php
        $hasPayout = $isOwner && $user->hasPayoutDestination();
        $isVerified = $verification && $verification->verification_status === 'Approved';
        $subtitle = ($business?->business_name ? $business->business_name . ' · ' : 'Independent landlord · ') . 'Member since ' . $user->created_at->format('F Y');
        $visibilityLabels = [
            'public' => ['Public', 'Anyone can view this page'],
            'landlords_only' => ['Landlords only', 'Only other landlords can view this page'],
            'private' => ['Private', 'Only you can see this page'],
        ];
        [$visibilityTitle, $visibilitySub] = $visibilityLabels[$user->profile_visibility ?? 'private'] ?? $visibilityLabels['private'];
        $tabs = ['details' => 'Details', 'properties' => 'Properties', 'reviews' => 'Reviews'];
        $starPath = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
    @endphp
    <div class="min-h-[calc(100vh-72px)] pb-10" x-data="{
                tab: @js(array_keys($tabs)).includes(location.hash.slice(1)) ? location.hash.slice(1) : 'details',
                go(t) { this.tab = t; history.replaceState(null, '', '#' + t); }
            }">

        <x-profile-banner :user="$user" :subtitle="$subtitle" :container="auth()->user()->shellContainerClass($isOwner)">
            <x-slot:badges>
                <span class="rounded-full bg-[#ECEEF6] px-2.5 py-1 text-[11.5px] font-semibold text-[#060D26]">Landlord</span>
                @if($isVerified)
                    <span class="inline-flex items-center gap-1 rounded-full bg-[#15803D] px-2.5 py-1 text-[11.5px] font-semibold text-white">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Verified
                    </span>
                @endif
            </x-slot:badges>
            <x-slot:actions>
                @if($isOwner)
                    <a href="{{ route('landlord.profile.edit') }}" class="inline-flex h-10 items-center gap-2 px-4 rounded-xl bg-white text-[13px] font-semibold text-[#060D26] hover:brightness-95 transition duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit profile
                    </a>
                @else
                    @auth
                        <a href="{{ route('conversations.store') }}?landlord_id={{ $user->user_id }}" class="inline-flex h-10 items-center gap-2 px-4 rounded-xl bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold hover:bg-[#E96F4F] transition-colors duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                            Message
                        </a>
                        <a href="{{ route('reports.create', ['user_id' => $user->user_id]) }}" class="inline-flex h-10 items-center gap-2 px-4 rounded-xl bg-white/10 hover:bg-white/20 text-[13px] font-semibold text-white transition-colors duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                            </svg>
                            Report
                        </a>
                    @endauth
                @endif
            </x-slot:actions>

            {{-- Tabs --}}
            <div class="mt-5 border-b border-[#E2E4EC]">
                <div role="tablist" aria-label="Landlord profile sections" class="flex gap-6 overflow-x-auto">
                    @foreach($tabs as $key => $label)
                        <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                            :aria-selected="tab === '{{ $key }}'" @click="go('{{ $key }}')"
                            :class="tab === '{{ $key }}' ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]'"
                            class="whitespace-nowrap border-b-2 pb-3 text-[13.5px] font-bold transition-colors duration-200 cursor-pointer">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="py-6">

                {{-- ── Details ── --}}
                <div id="panel-details" role="tabpanel" aria-labelledby="tab-details" x-show="tab === 'details'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                  <div class="lg:col-span-2 space-y-5">

                    {{-- Numbers: the rating leads on navy, the rest sit quietly beside it. --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-2xl bg-[#060D26] p-4 text-white">
                            <p class="text-[12px] font-semibold text-white/70">Rating as landlord</p>
                            @if($averageRating !== null)
                                <p class="mt-2 flex items-baseline gap-1.5">
                                    <span class="text-[26px] font-extrabold leading-none tabular-nums">{{ number_format($averageRating, 1) }}</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#FBBF24" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                                </p>
                                <p class="mt-2 text-[12px] text-white/65">From {{ $ratingCount }} {{ Str::plural('tenant', $ratingCount) }}</p>
                            @else
                                <p class="mt-2 text-[26px] font-extrabold leading-none text-white/60">&mdash;</p>
                                <p class="mt-2 text-[12px] text-white/65">No reviews yet</p>
                            @endif
                        </div>
                        <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                            <p class="text-[12px] font-semibold text-[#5B6A8E]">Properties</p>
                            <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $propertyCount }}</p>
                            <p class="mt-2 text-[12px] text-[#5B6A8E]">Approved and listed</p>
                        </div>
                        <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                            <p class="text-[12px] font-semibold text-[#5B6A8E]">Units</p>
                            <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $totalUnits }}</p>
                            <p class="mt-2 text-[12px] text-[#5B6A8E]">Across all properties</p>
                        </div>
                        <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                            @if($isOwner)
                                <p class="text-[12px] font-semibold text-[#5B6A8E]">Occupied</p>
                                <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $occupiedUnits }}</p>
                                <p class="mt-2 text-[12px] text-[#5B6A8E]">{{ $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100) . '% occupancy' : 'No units yet' }}</p>
                            @else
                                <p class="text-[12px] font-semibold text-[#5B6A8E]">Available now</p>
                                <p class="mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $availableUnits }}</p>
                                <p class="mt-2 text-[12px] text-[#5B6A8E]">Ready for tenants</p>
                            @endif
                        </div>
                    </div>

                    {{-- About + Business + contact, on one white card --}}
                    <x-card class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-[12px] font-semibold text-[#5B6A8E] mb-1.5">About</p>
                            @if($user->bio)
                                <p class="text-[14px] leading-relaxed text-[#060D26] whitespace-pre-line">{{ $user->bio }}</p>
                            @elseif($isOwner)
                                <p class="text-[14px] text-[#5B6A8E]">No bio yet. <a href="{{ route('landlord.profile.edit') }}" class="font-semibold text-[#B35A3D] hover:underline">Tell tenants a bit about yourself.</a></p>
                            @else
                                <p class="text-[14px] text-[#5B6A8E]">This landlord has not added a bio.</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-[12px] font-semibold text-[#5B6A8E] mb-1.5">Business</p>
                            @if($business)
                                <div class="flex items-start gap-3">
                                    @if($business->logo_url)
                                        <img loading="lazy" decoding="async" src="{{ $business->logo_url }}" alt="{{ $business->business_name }}" class="w-12 h-12 rounded-xl object-cover shrink-0">
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

                    {{-- Contact + account facts. Name, role, member-since and the counts already sit in the header and tiles above. --}}
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 border-t border-[#E2E4EC] pt-6">
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
                            <dt class="text-[12px] font-semibold text-[#5B6A8E]">Identity</dt>
                            <dd class="mt-1 text-[14px] font-semibold {{ $isVerified ? 'text-[#15803D]' : 'text-[#060D26]' }}">
                                {{ $isVerified ? 'Verified landlord' : ($verification ? ucfirst(strtolower($verification->verification_status)) : 'Not verified') }}
                            </dd>
                            @if($isVerified && $verification->reviewed_at)
                                <p class="text-[12px] text-[#5B6A8E]">Checked {{ $verification->reviewed_at->format('M d, Y') }}</p>
                            @endif
                        </div>
                        @if($isOwner)
                            <div class="min-w-0">
                                <dt class="text-[12px] font-semibold text-[#5B6A8E]">Profile visibility</dt>
                                <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $visibilityTitle }}</dd>
                                <p class="text-[12px] text-[#5B6A8E]">{{ $visibilitySub }}</p>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[12px] font-semibold text-[#5B6A8E]">Payout (GCash)</dt>
                                @if($hasPayout)
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26] tabular-nums">&bull;&bull;&bull;&bull;{{ substr(preg_replace('/\D/', '', $user->gcash_number), -4) }}</dd>
                                    <p class="text-[12px] text-[#5B6A8E] truncate">{{ $user->gcash_account_name }}</p>
                                @else
                                    <dd class="mt-1 text-[14px] font-semibold text-[#B45309]">Not set</dd>
                                    <p class="text-[12px] text-[#5B6A8E]"><a href="{{ route('landlord.profile.edit') }}" class="font-semibold text-[#B35A3D] hover:underline">Add GCash details</a> to get paid</p>
                                @endif
                            </div>
                        @endif
                    </dl>
                    </x-card>
                  </div>

                  {{-- Right rail: two glanceable summaries that hand off to their full tabs. --}}
                  <aside class="space-y-5">
                    <x-card>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Listings</h2>
                            @if($propertyCount)
                                <button type="button" @click="go('properties')" class="text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">View all</button>
                            @endif
                        </div>
                        @if($propertyCount)
                            @php $availPct = $totalUnits > 0 ? round($availableUnits / $totalUnits * 100) : 0; @endphp
                            <div class="mt-4">
                                <div class="flex items-baseline justify-between text-[12.5px]">
                                    <span class="text-[#5B6A8E]">Units available</span>
                                    <span class="font-semibold text-[#060D26] tabular-nums">{{ $availableUnits }} of {{ $totalUnits }}</span>
                                </div>
                                <div class="mt-1.5 h-2 rounded-full bg-[#E2E4EC] overflow-hidden" role="img" aria-label="{{ $availPct }}% of units available">
                                    <div class="h-full rounded-full bg-[#15803D]" style="width: {{ $availPct }}%"></div>
                                </div>
                            </div>
                            @if($propertySummary['rentLow'])
                                <dl class="mt-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Rent from</dt>
                                        <dd class="mt-0.5 text-[15px] font-bold text-[#060D26] tabular-nums">&#8369;{{ number_format($propertySummary['rentLow']) }}<span class="text-[12px] font-normal text-[#5B6A8E]">/mo</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Up to</dt>
                                        <dd class="mt-0.5 text-[15px] font-bold text-[#060D26] tabular-nums">&#8369;{{ number_format($propertySummary['rentHigh']) }}<span class="text-[12px] font-normal text-[#5B6A8E]">/mo</span></dd>
                                    </div>
                                </dl>
                            @endif
                            @if($propertySummary['areas']->isNotEmpty())
                                <div class="mt-4">
                                    <p class="text-[12px] font-semibold text-[#5B6A8E]">Areas</p>
                                    <p class="mt-0.5 text-[13.5px] text-[#060D26]">
                                        {{ $propertySummary['areas']->take(4)->join(', ') }}@if($propertySummary['areas']->count() > 4) <span class="text-[#5B6A8E]">+{{ $propertySummary['areas']->count() - 4 }} more</span>@endif
                                    </p>
                                </div>
                            @endif
                            @if($propertySummary['types']->isNotEmpty())
                                <div class="mt-4 flex flex-wrap gap-1.5">
                                    @foreach($propertySummary['types'] as $type => $count)
                                        <span class="rounded-full bg-[#ECEEF6] px-2.5 py-1 text-[12px] font-medium text-[#060D26]">{{ $type }} <span class="text-[#5B6A8E] tabular-nums">{{ $count }}</span></span>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <p class="mt-3 text-[13.5px] text-[#5B6A8E]">No approved properties yet.</p>
                        @endif
                    </x-card>

                    <x-card>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Reviews</h2>
                            @if($ratingCount > 0)
                                <button type="button" @click="go('reviews')" class="text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">See all</button>
                            @endif
                        </div>
                        @if($ratingCount > 0)
                            @php $latest = $reviews->first(fn ($r) => $r->property && filled($r->review_comment)); @endphp
                            <div class="mt-4 flex items-center gap-4">
                                <div class="shrink-0">
                                    <p class="text-[32px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ number_format($averageRating, 1) }}</p>
                                    <div class="mt-1.5 flex gap-0.5" aria-label="{{ number_format($averageRating, 1) }} out of 5 stars">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $i <= round($averageRating) ? '#FBBF24' : '#E2E4EC' }}" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                                        @endfor
                                    </div>
                                    <p class="mt-1.5 text-[12px] text-[#5B6A8E]">{{ $ratingCount }} {{ Str::plural('review', $ratingCount) }}</p>
                                </div>
                                <div class="flex-1 space-y-1">
                                    @for($star = 5; $star >= 1; $star--)
                                        <div class="flex items-center gap-1.5 text-[11px] text-[#5B6A8E]">
                                            <span class="w-5 shrink-0 tabular-nums">{{ $star }}&#9733;</span>
                                            <div class="flex-1 h-1.5 rounded-full bg-[#E2E4EC] overflow-hidden">
                                                <div class="h-full bg-[#FBBF24] rounded-full" style="width: {{ round((($ratingDistribution[$star] ?? 0) / $ratingCount) * 100) }}%"></div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            @if($latest)
                                <figure class="mt-4 rounded-xl bg-[#F7F8FC] p-3.5">
                                    <blockquote class="text-[13px] leading-relaxed text-[#060D26] line-clamp-3">&ldquo;{{ $latest->review_comment }}&rdquo;</blockquote>
                                    <figcaption class="mt-2 text-[12px] text-[#5B6A8E]">{{ $latest->tenant->first_name }} {{ $latest->tenant->last_name }} &middot; {{ $latest->property->title }}</figcaption>
                                </figure>
                            @endif
                        @else
                            <p class="mt-3 text-[13.5px] text-[#5B6A8E]">No reviews received yet.</p>
                        @endif
                    </x-card>
                  </aside>
                </div>

                {{-- ── Properties ── --}}
                <div id="panel-properties" role="tabpanel" aria-labelledby="tab-properties" x-show="tab === 'properties'" x-cloak>
                    <div class="flex items-baseline justify-between mb-4">
                        <h2 class="text-[16px] font-semibold text-[#060D26]">Properties</h2>
                        <span class="text-[13px] text-[#5B6A8E]">{{ $propertyCount }} total</span>
                    </div>
                    @if($propertyCount)
                        {{-- Only the first page is rendered; "Show more" fetches the rest in batches so a landlord with hundreds of listings stays fast. --}}
                        <div x-data="{
                                remaining: {{ max(0, $propertyCount - $properties->count()) }},
                                offset: {{ $properties->count() }},
                                loading: false,
                                failed: false,
                                async more() {
                                    if (this.loading) return;
                                    this.loading = true; this.failed = false;
                                    try {
                                        const res = await fetch(@js(route('landlord.profile.properties', $user)) + '?offset=' + this.offset, { headers: { 'Accept': 'application/json' } });
                                        if (!res.ok) throw new Error();
                                        const data = await res.json();
                                        const before = this.$refs.list.children.length;
                                        this.$refs.list.insertAdjacentHTML('beforeend', data.html);
                                        this.offset = this.$refs.list.children.length;
                                        this.remaining = data.remaining;
                                        this.$refs.list.children[before]?.querySelector('a')?.focus();
                                    } catch (e) { this.failed = true; }
                                    this.loading = false;
                                }
                            }">
                            <ul x-ref="list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                                @foreach($properties as $property)
                                    @include('landlord.profile._property-card')
                                @endforeach
                            </ul>

                            <div x-show="remaining > 0" @if($propertyCount <= $properties->count()) x-cloak @endif class="text-center mt-5">
                                <button type="button" @click="more()" :disabled="loading" :aria-busy="loading"
                                    class="inline-flex h-10 items-center gap-2 px-5 rounded-xl border border-[#060D26]/25 text-[13.5px] font-semibold text-[#060D26] hover:border-[#060D26] hover:bg-[#F7F8FC] disabled:opacity-60 disabled:cursor-wait transition-colors duration-200 cursor-pointer">
                                    <span x-text="loading ? 'Loading…' : 'Show more'"></span>
                                </button>
                                <p x-show="failed" x-cloak class="mt-2 text-[12.5px] text-[#DC2626]">Couldn't load more properties. Try again.</p>
                            </div>
                        </div>
                    @else
                        <div class="rounded-2xl border border-[#E2E4EC] bg-white py-10 text-center">
                            <p class="text-[14px] font-semibold text-[#060D26]">No approved properties yet</p>
                            @if($isOwner)
                                <p class="mt-1 text-[13px] text-[#5B6A8E]">Listings appear here once an admin approves them.</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- ── Reviews ── --}}
                <div id="panel-reviews" role="tabpanel" aria-labelledby="tab-reviews" x-show="tab === 'reviews'" x-cloak>
                  <x-card>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <h2 class="text-[16px] font-semibold text-[#060D26]">Reviews</h2>
                        @if($averageRating !== null)
                            <x-star-rating :rating="$averageRating" :count="$ratingCount" />
                        @endif
                    </div>

                    @if($ratingCount > 0)
                        <div class="space-y-1.5 mb-5">
                            @for($star = 5; $star >= 1; $star--)
                                @php $starCount = $ratingDistribution[$star] ?? 0; @endphp
                                <div class="flex items-center gap-2 text-[12px] text-[#5B6A8E]">
                                    <span class="w-6 shrink-0 tabular-nums">{{ $star }}&#9733;</span>
                                    <div class="flex-1 h-1.5 rounded-full bg-[#E2E4EC] overflow-hidden">
                                        <div class="h-full bg-[#FBBF24] rounded-full" style="width: {{ round(($starCount / $ratingCount) * 100) }}%"></div>
                                    </div>
                                    <span class="w-6 text-right tabular-nums">{{ $starCount }}</span>
                                </div>
                            @endfor
                        </div>
                    @endif

                    <div class="divide-y divide-[#5B6A8E]/10 border-t border-[#5B6A8E]/10">
                        @forelse($reviews as $review)
                            @continue(!$review->property)
                            <div class="py-4">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <p class="text-[14px] font-semibold text-[#060D26] truncate">{{ $review->tenant->first_name }} {{ $review->tenant->last_name }}</p>
                                    <div class="flex gap-0.5 shrink-0" aria-label="{{ $review->rating }} out of 5 stars">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E4EC' }}" stroke="none" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                                        @endfor
                                    </div>
                                </div>
                                <a href="{{ route('properties.show', $review->property) }}" class="text-[12.5px] text-[#5B6A8E] hover:underline">{{ $review->property->title }}</a>
                                <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed mt-1">{{ $review->review_comment }}</p>
                            </div>
                        @empty
                            <p class="py-8 text-center text-[13.5px] text-[#5B6A8E]">No reviews received yet</p>
                        @endforelse
                    </div>

                    @if($isOwner && $ratingCount > 0)
                        <a href="{{ route('landlord.reviews.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">
                            View all {{ $ratingCount }} {{ Str::plural('review', $ratingCount) }}
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    @endif
                  </x-card>
                </div>
            </div>
        </x-profile-banner>
    </div>
@endsection
