@extends('layouts.admin')

@section('page-title', 'Property Details')

@section('content')
@vite(['resources/js/maps/admin-property-map.js'])
@php
    $landlord = $property->landlord;
    $media = $property->media->values();
    $images = $media->where('media_type', 'Image')->values();
    $unpinned = (float) $property->latitude === 10.3157 && (float) $property->longitude === 123.8854;
    $mapsUrl = $unpinned ? null : 'https://www.google.com/maps?q='.$property->latitude.','.$property->longitude;
    $docsTotal = $property->documents->count();
    $docsVerified = $property->documents->where('status', 'Verified')->count();
    $isPending = $property->verification_status === 'Pending';
    $isSuspended = $property->publication_status === 'Suspended';
    $unitCount = $property->units->count();
    $unitStats = [
        'total' => $unitCount,
        'available' => $property->units->where('availability_status', 'Available')->count(),
        'reserved' => $property->units->where('availability_status', 'Reserved')->count(),
        'occupied' => $property->units->where('availability_status', 'Occupied')->count(),
    ];
    $fees = $property->units->pluck('rental_fee')->filter()->map(fn ($v) => (float) $v);
    $reviews = $property->reviews;
    $rating = $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null;
    $inProgress = $property->reservations->whereIn('rental_status', ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed'])->count();

    // What a listing needs before it goes live. Each is a pass or a warning, never a block: the admin decides.
    $checks = [
        'photos' => $images->isNotEmpty(),
        'location' => ! $unpinned,
        'units' => $unitCount > 0,
        'documents' => $docsTotal > 0 && $docsVerified === $docsTotal,
        'landlord' => (bool) $landlordVerified,
    ];
    $passing = count(array_filter($checks));
    $missing = collect(['photos' => 'Photos', 'location' => 'A map pin', 'units' => 'Units', 'documents' => 'Verified documents', 'landlord' => 'A verified landlord'])
        ->filter(fn ($label, $key) => ! $checks[$key])->values();
    $readiness = new \Illuminate\Support\HtmlString(view('admin.catalogue.properties.partials.readiness', compact('checks', 'passing', 'missing'))->render());

    // [label, value, small suffix, chip background, chip icon colour, icon path]
    $keyFacts = [
        ['Rent', $fees->isEmpty() ? '—' : ($fees->min() === $fees->max() ? '₱'.number_format($fees->min(), 0) : '₱'.number_format($fees->min(), 0).' – ₱'.number_format($fees->max(), 0)), $fees->isEmpty() ? '' : '/mo',
            'bg-[#FF8A66]/15', 'text-[#B35A3D]', 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
        ['Units', (string) $unitCount, $unitStats['available'].' free',
            'bg-[#22C55E]/10', 'text-[#15803D]', 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z'],
        ['Reservations', (string) $inProgress, 'in progress',
            'bg-[#5B6A8E]/10', 'text-[#5B6A8E]', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
        ['Rating', $rating === null ? '—' : number_format($rating, 1), $rating === null ? 'No reviews' : '('.$reviews->count().')',
            'bg-[#FBBF24]/15', 'text-[#B45309]', 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z'],
    ];
    $dotFor = fn (string $s) => match ($s) {
        'Approved', 'Published' => 'bg-[#22C55E]',
        'Pending' => 'bg-[#FBBF24]',
        'Rejected', 'Suspended' => 'bg-[#EF4444]',
        default => 'bg-[#5B6A8E]',
    };
    $utilityLabels = ['water_included' => 'Water', 'electricity_included' => 'Electricity', 'internet_included' => 'Internet', 'association_fees_included' => 'Association fees'];
    $utilitiesIn = collect($utilityLabels)->filter(fn ($l, $k) => $property->{$k})->values();
    $utilitiesOut = collect($utilityLabels)->reject(fn ($l, $k) => $property->{$k})->values();
    $latest = $activities->take(5);
@endphp

<div class="max-w-[1600px] mx-auto">

    <x-back-link href="{{ route('admin.catalogue.properties.index') }}" to="properties" class="mb-4" />

    {{-- Hero: cover photo, title, address. The same frame as the landlord's property page. --}}
    <div class="relative rounded-t-2xl bg-[#ECEEF6] h-64 sm:h-72 lg:h-80">
        <div class="absolute inset-0 rounded-t-2xl overflow-hidden">
            @if($images->isNotEmpty())
                <a href="{{ $images->first()->media_url }}" target="_blank" rel="noopener" aria-label="Open the cover photo full size" class="absolute inset-0 block">
                    <img src="{{ $images->first()->media_url }}" alt="{{ $property->title }}" class="absolute inset-0 h-full w-full object-cover">
                </a>
            @else
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 pb-16 text-[#5B6A8E]">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v13.5A1.5 1.5 0 0 0 3.75 21z"/></svg>
                    <span class="text-sm font-medium">No cover photo</span>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#060D26]/90 via-[#060D26]/20 to-transparent"></div>
        </div>

        <div class="absolute inset-x-0 bottom-0 flex flex-col gap-3 p-4 sm:flex-row sm:items-end sm:justify-between sm:p-6">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold leading-tight text-white sm:text-[26px]">{{ $property->title }}</h1>
                <p class="mt-1.5 flex items-center gap-1.5 text-sm text-white/85">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/></svg>
                    <span class="truncate">{{ $property->address }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                @if($property->publication_status === 'Published' && $property->verification_status === 'Approved')
                    <a href="{{ route('properties.show', $property) }}" target="_blank" rel="noopener"
                        class="inline-flex h-9 items-center rounded-lg bg-white/20 px-4 text-[13px] font-semibold text-white backdrop-blur-sm transition-colors duration-200 hover:bg-white/30">View as tenant</a>
                @endif
            </div>
        </div>
    </div>

    {{-- Key facts strip, attached to the hero --}}
    <dl class="mb-6 grid grid-cols-2 rounded-b-2xl border border-t-0 border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] sm:grid-cols-4">
        @foreach($keyFacts as [$label, $value, $suffix, $chipBg, $chipFg, $icon])
            <div class="flex min-w-0 items-center gap-3 border-[#E2E4EC] px-5 py-3.5 max-sm:[&:nth-child(n+3)]:border-t max-sm:even:border-l sm:[&:not(:first-child)]:border-l">
                <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:flex {{ $chipBg }} {{ $chipFg }}" aria-hidden="true">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                </span>
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">{{ $label }}</dt>
                    <dd class="mt-0.5 truncate tabular-nums text-[#060D26]"><span class="text-xl font-semibold">{{ $value }}</span>@if($suffix)<span class="ml-1 text-xs font-normal text-[#5B6A8E]">{{ $suffix }}</span>@endif</dd>
                </div>
            </div>
        @endforeach
    </dl>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">

        {{-- Status rail (below the tabs on phones) --}}
        <aside class="order-2 space-y-4 lg:order-1">

            @if($isPending)
                <x-admin.decision
                    :since="$property->created_at?->format('M j, Y')"
                    :approve-action="route('admin.listings.approve', $property->property_id)"
                    approve-label="Approve listing"
                    approve-confirm="Approve this listing?"
                    approve-message="It becomes visible to tenants and the landlord is told. Check the photos and documents first."
                    :reject-action="route('admin.listings.reject', $property->property_id)"
                    reject-label="Reject listing"
                    reject-confirm="Reject this listing?" stacked>
                    <x-slot:intro>{{ $readiness }}</x-slot:intro>
                </x-admin.decision>
            @endif

            <x-card flush class="p-5">
                <h3 class="text-[14px] font-semibold text-[#060D26]">Listing status</h3>
                <dl class="mt-3 space-y-2.5 text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#5B6A8E]">Verification</dt>
                        <dd class="inline-flex items-center gap-1.5 font-semibold text-[#060D26]"><span class="h-1.5 w-1.5 rounded-full {{ $dotFor($property->verification_status) }}" aria-hidden="true"></span>{{ $property->verification_status }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#5B6A8E]">Visibility</dt>
                        <dd><x-publication-status-badge :status="$property->publication_status" class="text-[13px]" /></dd>
                    </div>
                </dl>

                @if($isSuspended)
                    <p class="mt-4 rounded-xl bg-[#EF4444]/[0.06] px-3 py-2 text-[12px] leading-relaxed text-[#DC2626]">Suspended. Tenants cannot see this listing.</p>
                    <form method="POST" action="{{ route('admin.listings.unsuspend', $property->property_id) }}" class="mt-3"
                        data-confirm="Reinstate this listing?" data-confirm-message="It will become visible to tenants again immediately.">
                        @csrf
                        <button type="submit" class="h-9 w-full rounded-lg bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Unsuspend</button>
                    </form>
                @endif

                @if(! $isPending)
                    <div class="mt-4 border-t border-[#E2E4EC] pt-3">{{ $readiness }}</div>
                @endif

                <p class="mt-3 text-[12px] text-[#5B6A8E]"><span class="font-mono">PRP-{{ str_pad($property->property_id, 4, '0', STR_PAD_LEFT) }}</span> &middot; Created {{ $property->created_at->format('M d, Y') }}</p>
            </x-card>

            <x-card flush class="p-5">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-[14px] font-semibold text-[#060D26]">Unit status</h3>
                    <button type="button" @click="$dispatch('open-tab', 'units')" class="text-[12px] font-semibold text-[#B35A3D] hover:underline cursor-pointer">Manage</button>
                </div>
                <ul class="mt-3 space-y-3">
                    @foreach([['Available', 'available', 'bg-[#22C55E]'], ['Reserved', 'reserved', 'bg-[#FBBF24]'], ['Occupied', 'occupied', 'bg-[#EF4444]']] as [$statLabel, $statKey, $statBar])
                        <li>
                            <div class="flex items-center justify-between text-[13px]">
                                <span class="inline-flex items-center gap-2 {{ $unitStats[$statKey] > 0 ? 'font-semibold text-[#060D26]' : 'font-medium text-[#5B6A8E]' }}"><span class="h-1.5 w-1.5 rounded-full {{ $statBar }}" aria-hidden="true"></span>{{ $statLabel }}</span>
                                <span class="tabular-nums {{ $unitStats[$statKey] > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $unitStats[$statKey] }}</span>
                            </div>
                            <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-[#ECEEF6]"><div class="h-full rounded-full {{ $statBar }}" style="width: {{ $unitStats['total'] > 0 ? round($unitStats[$statKey] / $unitStats['total'] * 100) : 0 }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card flush class="p-5">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-[14px] font-semibold text-[#060D26]">Landlord</h3>
                    @if($landlordVerified)<x-admin.status status="Verified" />@endif
                </div>
                @if($landlord)
                    <a href="{{ route('admin.users.show', $landlord) }}" class="mt-2 block text-[14px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ trim($landlord->first_name.' '.$landlord->last_name) }}</a>
                    <p class="break-words text-[12.5px] text-[#5B6A8E]">{{ $landlord->email ?: '—' }}</p>
                @else
                    <p class="mt-2 text-[13px] text-[#5B6A8E]">—</p>
                @endif
            </x-card>
        </aside>

        {{-- Tabs --}}
        <div x-data="{
                tabs: ['overview', 'units', 'photos', 'documents', 'reservations', 'reviews', 'activity'],
                tab: 'overview',
                init() { this.syncTab(false); },
                syncTab(scroll = true) {
                    const wanted = location.hash.slice(1);
                    if (! this.tabs.includes(wanted)) return;
                    this.show(wanted, false);
                    if (scroll) this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
                // The map is drawn while its tab may be hidden, so it is told to measure itself again once shown.
                show(key, remember = true) {
                    this.tab = key;
                    if (remember) history.replaceState(null, '', '#' + key);
                    this.$nextTick(() => window.dispatchEvent(new Event('admin-tab-shown')));
                },
            }"
            x-on:hashchange.window="syncTab()"
            x-on:open-tab.window="show($event.detail)"
            class="order-1 min-w-0 lg:order-2">

            <div class="mb-6 flex items-center gap-1 overflow-x-auto border-b border-[#5B6A8E]/20" role="tablist">
                @foreach([
                    ['overview', 'Overview'],
                    ['units', 'Units ('.$unitCount.')'],
                    ['photos', 'Photos ('.$media->count().')'],
                    ['documents', 'Documents ('.$docsVerified.'/'.$docsTotal.')'],
                    ['reservations', 'Reservations ('.$property->reservations->count().')'],
                    ['reviews', 'Reviews ('.$reviews->count().')'],
                    ['activity', 'Activity ('.$activityTotal.')'],
                ] as [$key, $label])
                    <button type="button" role="tab" @click="show('{{ $key }}')" :aria-selected="tab === '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-b-2 border-[#FF8A66] text-[#060D26] font-semibold' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="shrink-0 px-4 pb-3 text-sm transition-colors duration-200 cursor-pointer">{{ $label }}</button>
                @endforeach
            </div>

            {{-- Overview --}}
            <div x-show="tab === 'overview'" class="space-y-5">
                <x-card flush class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-[#060D26]">About</h3>
                    @if($property->description)
                        <p class="mt-3 text-[14px] leading-relaxed text-[#060D26]/80">{{ $property->description }}</p>
                    @else
                        <p class="mt-3 text-[14px] text-[#5B6A8E]">The landlord has not written a description.</p>
                    @endif
                    <dl class="mt-5 flex flex-wrap gap-x-10 gap-y-4">
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Type</dt><dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $property->property_type ?: '—' }}</dd></div>
                        @if($property->living_arrangement)
                            <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Living arrangement</dt><dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $property->living_arrangement }}</dd></div>
                        @endif
                        @if($property->occupancy_preference && $property->occupancy_preference !== 'No Preference')
                            <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Occupancy</dt><dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $property->occupancy_preference }}</dd></div>
                        @endif
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Included in rent</dt><dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $utilitiesIn->isNotEmpty() ? $utilitiesIn->implode(', ') : 'Nothing' }}</dd></div>
                    </dl>
                    @if(! empty($property->house_rules))
                        <div class="mt-5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">House rules</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($property->house_rules as $rule)
                                    @php $permits = preg_match('/\ballowed\b/i', $rule) && ! preg_match('/^no\b/i', $rule); @endphp
                                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-medium {{ $permits ? 'bg-[#22C55E]/10 text-[#15803D]' : 'bg-[#ECEEF6] text-[#060D26]' }}">{{ $rule }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($utilitiesOut->isNotEmpty())
                        <p class="mt-4 text-[12.5px] text-[#5B6A8E]">Billed separately: {{ $utilitiesOut->map(fn ($l) => Str::lower($l))->implode(', ') }}@if($property->utilities_separately_metered) &mdash; separately metered @endif</p>
                    @endif
                    @if($property->rejection_reason && $property->verification_status === 'Rejected')
                        <p class="mt-4 rounded-xl bg-[#EF4444]/[0.06] px-3 py-2 text-[13px] leading-relaxed text-[#DC2626]"><span class="font-semibold">Rejection reason:</span> {{ $property->rejection_reason }}</p>
                    @endif
                </x-card>

                <x-card flush class="p-5 sm:p-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="text-[15px] font-semibold text-[#060D26]">Location</h3>
                        <span class="text-[12.5px] {{ $unpinned ? 'font-semibold text-[#B45309]' : 'text-[#5B6A8E]' }}">{{ $unpinned ? 'Never pinned: this is the Cebu City default, not the property' : 'Check that the pin sits on the street in the address' }}</span>
                    </div>
                    <div class="relative mb-3 h-56 overflow-hidden rounded-xl border border-[#5B6A8E]/20 bg-[#ECEEF6] sm:h-64">
                        <div id="admin-property-map" class="h-full w-full" data-lat="{{ $property->latitude }}" data-lng="{{ $property->longitude }}" data-pinned="{{ $unpinned ? '0' : '1' }}"></div>
                        <button type="button" id="admin-property-map-satellite" aria-pressed="false"
                            class="absolute right-2 top-2 z-[500] inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-2.5 text-[12.5px] font-semibold text-[#060D26] shadow-sm transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer"><span>Satellite</span></button>
                    </div>
                    @if($mapsUrl)
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                            class="flex h-9 w-full items-center justify-center rounded-xl border border-[#5B6A8E]/30 text-sm font-medium text-[#060D26] transition-colors duration-200 hover:bg-[#ECEEF6]">Open in Google Maps</a>
                    @endif
                </x-card>

                <x-card flush class="p-5">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-[#060D26]">Recent activity</h3>
                        @if($activityTotal > 0)
                            <button type="button" @click="show('activity')" class="flex items-center gap-1 text-xs font-semibold text-[#B35A3D] hover:underline cursor-pointer">View all
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg></button>
                        @endif
                    </div>
                    @include('admin.catalogue.properties.partials.activity-list', ['lines' => $latest])
                </x-card>
            </div>

            {{-- Units --}}
            <div x-show="tab === 'units'" x-cloak>
                <x-card flush>
                    @if($unitCount === 0)
                        <x-admin.empty title="No units yet" note="The landlord has not added any units to this property." />
                    @else
                        <div class="overflow-x-auto scrollbar-thin-light">
                            <table class="w-full min-w-[560px] text-left">
                                <thead><tr class="border-b border-[#E2E4EC]">
                                    <th class="px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Unit</th>
                                    <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Monthly rent</th>
                                    <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Status</th>
                                    <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Verification</th>
                                    <th class="px-5 py-3.5"><span class="sr-only">Open</span></th>
                                </tr></thead>
                                <tbody class="divide-y divide-[#E2E4EC]">
                                    @foreach($property->units as $unit)
                                        <tr class="transition-colors duration-200 hover:bg-[#F7F8FC]/70">
                                            <td class="px-5 py-3.5 text-[13px] font-bold text-[#060D26]">{{ $unit->unit_label }}</td>
                                            <td class="px-4 py-3.5"><p class="whitespace-nowrap text-[13px] font-bold tabular-nums text-[#060D26]">₱{{ number_format($unit->rental_fee, 0) }}</p><p class="text-[11px] text-[#5B6A8E]">per month</p></td>
                                            <td class="px-4 py-3.5"><x-admin.status :status="$unit->availability_status" kind="unit" /></td>
                                            <td class="px-4 py-3.5"><x-admin.status :status="$unit->verification_status" /></td>
                                            <td class="px-5 py-3.5 text-right"><a href="{{ route('admin.units.show', ['property' => $property->property_id, 'unit' => $unit->unit_id]) }}" class="text-[13px] font-semibold text-[#B35A3D] hover:underline">View</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- Photos --}}
            <div x-show="tab === 'photos'" x-cloak>
                @if($media->isEmpty())
                    <x-card flush><x-admin.empty title="No photos uploaded" note="Tenants will see an empty listing." /></x-card>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach($media as $item)
                            <div class="aspect-[4/3] overflow-hidden rounded-xl bg-[#ECEEF6]">
                                @if($item->media_type === 'Image')
                                    <a href="{{ $item->media_url }}" target="_blank" rel="noopener" class="block h-full w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50" aria-label="Open photo {{ $loop->iteration }} full size">
                                        <img src="{{ $item->media_url }}" alt="{{ $property->title }}" loading="lazy" class="h-full w-full object-cover transition-opacity duration-200 hover:opacity-90">
                                    </a>
                                @else
                                    <video src="{{ $item->media_url }}" class="h-full w-full object-cover" muted controls></video>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Documents: a scoped summary plus the request action, which needs this property's context. Full review lives in Property Documents. --}}
            <div x-show="tab === 'documents'" x-cloak x-data="{ requesting: false }">
                <x-card flush>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#E2E4EC] px-5 py-3.5">
                        <p class="text-[13px] text-[#5B6A8E]">{{ $docsTotal > 0 ? $docsVerified.' of '.$docsTotal.' verified' : 'Nothing has been uploaded or requested yet.' }}</p>
                        <button type="button" @click="requesting = !requesting" :aria-expanded="requesting" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Request a document</button>
                    </div>
                    <form x-show="requesting" x-cloak method="POST" action="{{ route('admin.properties.documents.request', $property) }}" class="flex flex-wrap items-end gap-2 border-b border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3">
                        @csrf
                        <div class="min-w-[200px] flex-1">
                            @php $requestDocumentTypeOptions = ['' => 'Select a document type'] + array_combine(\App\Models\PropertyDocument::TYPES, \App\Models\PropertyDocument::TYPES); @endphp
                            <x-styled-select name="document_type" required :options="$requestDocumentTypeOptions" :selected="''" class="h-9 w-full rounded-lg border border-[#E2E4EC] bg-white px-3 text-[13px] text-[#060D26]" />
                        </div>
                        <button type="submit" class="h-9 rounded-lg bg-[#FF8A66] px-4 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Send request</button>
                    </form>
                    @if($docsTotal > 0)
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($property->documents->sortByDesc('updated_at') as $document)
                                <li><a href="{{ route('admin.documents.show', $document) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                    <span class="min-w-0"><span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $document->document_type }}</span>
                                        <span class="block text-[12px] text-[#5B6A8E]">Updated {{ $document->updated_at?->format('M j, Y') }}@if($document->expiry_date) · expires {{ $document->expiry_date->format('M j, Y') }}@endif</span></span>
                                    <x-admin.status :status="$document->isRequested() ? 'Requested' : $document->display_status" class="shrink-0" />
                                </a></li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            {{-- Reservations --}}
            <div x-show="tab === 'reservations'" x-cloak>
                <x-card flush>
                    @if($property->reservations->isEmpty())
                        <x-admin.empty title="No reservations yet" />
                    @else
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($property->reservations->sortByDesc('created_at') as $reservation)
                                <li><a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                    <span class="min-w-0"><span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ trim(($reservation->tenant->first_name ?? '').' '.($reservation->tenant->last_name ?? '')) ?: '—' }}</span>
                                        <span class="block text-[12px] text-[#5B6A8E]">{{ $reservation->created_at?->format('M j, Y') }}</span></span>
                                    <x-admin.status :status="$reservation->rental_status" class="shrink-0" />
                                </a></li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            {{-- Reviews --}}
            <div x-show="tab === 'reviews'" x-cloak>
                <x-card flush>
                    @if($reviews->isEmpty())
                        <x-admin.empty title="No reviews yet" />
                    @else
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($reviews as $review)
                                <li class="px-5 py-3.5">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ trim(($review->tenant->first_name ?? '').' '.($review->tenant->last_name ?? '')) ?: '—' }}</p>
                                        <p class="text-[12px] tabular-nums text-[#5B6A8E]"><span class="font-semibold text-[#B45309]">{{ $review->rating }}</span> / 5 · {{ $review->created_at?->format('M j, Y') }}</p>
                                    </div>
                                    <p class="mt-1 max-w-[80ch] text-[13px] leading-relaxed text-[#060D26]/80">{{ $review->review_comment ?: 'No comment.' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            {{-- Activity --}}
            <div x-show="tab === 'activity'" x-cloak>
                <x-card flush class="p-5">
                    @if($activityTotal > $activities->count())
                        <p class="mb-2 text-[12px] tabular-nums text-[#5B6A8E]">Latest {{ $activities->count() }} of {{ $activityTotal }}</p>
                    @endif
                    @include('admin.catalogue.properties.partials.activity-list', ['lines' => $activities])
                </x-card>
            </div>
        </div>
    </div>
</div>
@endsection
