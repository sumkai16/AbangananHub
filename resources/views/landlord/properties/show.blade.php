@extends('layouts.landlord')    
@section('content')
@vite(['resources/js/maps/property-map.js'])

<div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

    {{-- Back --}}
    <a href="{{ route('landlord.properties.index') }}"
       class="inline-flex items-center gap-2 text-sm text-[#5B6A8E] hover:text-[#060D26] transition-colors duration-200 mb-6">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to Properties
    </a>

    @php
        $images   = $property->media->where('media_type', 'Image')->values();
        $verified = $property->landlord->rentalBusiness()->exists();
        $reviews = $property->reviews()->with('tenant')->latest()->get();
        $mapsUrl  = ($property->latitude && $property->longitude)
            ? 'https://www.google.com/maps?q=' . $property->latitude . ',' . $property->longitude
            : 'https://www.google.com/maps?q=' . urlencode($property->address);

        $documentNudgeCount = $property->documents->filter(fn ($d) => $d->status === 'Rejected' || $d->isRequested())->count();
        $fees         = $property->units->pluck('rental_fee')->filter()->map(fn ($v) => (float) $v);
        $heroReviews  = $reviews->where('is_hidden', false);
        $heroRating   = $heroReviews->count() > 0 ? round($heroReviews->avg('rating'), 1) : null;
        // Not shown anywhere else on this page: reservations still moving, and this month's rent in.
        $inProgress = $property->reservations()
            ->whereIn('rental_status', ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed'])
            ->count();
        $collected = \App\Models\Payment::whereHas('reservation', fn ($q) => $q->where('property_id', $property->property_id))
            ->where('payment_type', 'Monthly')
            ->whereIn('status', ['Paid', 'Released'])
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        // [label, value, small suffix, chip background, chip icon colour, icon path]
        $keyFacts = [
            ['Rent', $fees->isEmpty() ? '—' : ($fees->min() === $fees->max()
                ? '₱' . number_format($fees->min(), 0)
                : '₱' . number_format($fees->min(), 0) . ' – ₱' . number_format($fees->max(), 0)), $fees->isEmpty() ? '' : '/mo',
                'bg-[#FF8A66]/15', 'text-[#B35A3D]', 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
            ['Collected', '₱' . number_format((float) $collected, 0), 'this month',
                'bg-[#22C55E]/10', 'text-[#15803D]', 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['Reservations', (string) $inProgress, 'in progress',
                'bg-[#5B6A8E]/10', 'text-[#5B6A8E]', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
            ['Rating', $heroRating === null ? '—' : number_format($heroRating, 1), $heroRating === null ? 'No reviews' : '(' . $heroReviews->count() . ')',
                'bg-[#FBBF24]/15', 'text-[#B45309]', 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z'],
        ];
        $dotFor = fn (string $s) => match ($s) {
            'Approved', 'Published' => 'bg-[#22C55E]',
            'Pending' => 'bg-[#FBBF24]',
            'Rejected', 'Suspended' => 'bg-[#EF4444]',
            default => 'bg-[#5B6A8E]',
        };
    @endphp

    {{-- ═══════════════════════════════════════════════════════
         HERO: cover photo, title, actions — key facts strip attached underneath
    ══════════════════════════════════════════════════════════ --}}
    <div class="relative rounded-t-2xl bg-[#ECEEF6] h-64 sm:h-72 lg:h-80" x-data="{ moreOpen: false }">
        <div class="absolute inset-0 rounded-t-2xl overflow-hidden">
            @if($images->isNotEmpty())
                <img id="main-photo"
                     src="{{ $images->first()->media_url }}"
                     alt="{{ $property->title }}"
                     class="absolute inset-0 w-full h-full object-cover cursor-pointer"
                     onclick="openLightbox(0)">
            @else
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-[#5B6A8E] pb-16">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5z"/>
                    </svg>
                    <span class="text-sm font-medium">No cover photo</span>
                    <a href="{{ route('properties.edit', $property) }}" class="text-xs underline underline-offset-2 hover:text-[#060D26] transition-colors duration-200">Add photos</a>
                </div>
            @endif
            {{-- Scrim so the white title stays readable on any photo --}}
            <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/90 via-[#060D26]/20 to-transparent pointer-events-none"></div>
        </div>


        <div class="absolute inset-x-0 bottom-0 p-4 sm:p-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-[26px] font-semibold text-white leading-tight">{{ $property->title }}</h1>
                <p class="mt-1.5 text-sm text-white/85 flex items-center gap-1.5">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/>
                    </svg>
                    <span class="truncate">{{ $property->address }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('properties.edit', $property) }}"
                   class="inline-flex items-center h-9 px-4 rounded-lg bg-white/20 backdrop-blur-sm text-white text-[13px] font-semibold hover:bg-white/30 transition-colors duration-200">
                    Edit property
                </a>
                <a href="{{ route('landlord.properties.units.create', $property) }}"
                   class="inline-flex items-center gap-1 h-9 px-4 rounded-lg bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold hover:bg-[#E96F4F] transition-colors duration-200">
                    + Add unit
                </a>
                {{-- Everything the old toolbar held that the design has no slot for: documents, units, walk-in, delete --}}
                <div class="relative">
                    <button type="button" @click="moreOpen = !moreOpen" aria-label="More actions" :aria-expanded="moreOpen"
                            class="relative inline-flex items-center justify-center h-9 w-9 rounded-lg bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 transition-colors duration-200">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                        </svg>
                        @if($documentNudgeCount > 0)
                            <span class="absolute -top-1 -right-1 h-3 w-3 rounded-full bg-[#EF4444] ring-2 ring-[#060D26]/70"></span>
                        @endif
                    </button>
                    <div x-show="moreOpen" x-cloak @click.outside="moreOpen = false"
                         class="absolute right-0 bottom-11 w-52 bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] ring-1 ring-black/5 py-1 z-20">
                        <a href="{{ route('landlord.properties.documents.index', $property) }}"
                           class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm text-[#060D26] hover:bg-[#ECEEF6] transition-colors">
                            Documents
                            @if($documentNudgeCount > 0)
                                <span class="inline-flex items-center justify-center h-4 min-w-[16px] px-1 rounded-full bg-[#EF4444] text-white text-[11px] font-bold">{{ $documentNudgeCount }}</span>
                            @endif
                        </a>

                        @if($unitStats['available'] > 0)
                            <a href="{{ route('landlord.tenants.walkIn.create', ['property' => $property->property_id]) }}"
                               class="flex items-center px-4 py-2.5 text-sm text-[#060D26] hover:bg-[#ECEEF6] transition-colors">
                                Add Walk-in Tenant
                            </a>
                        @endif
                        <div class="h-px bg-[#5B6A8E]/10 mx-3 my-1"></div>
                        <form method="POST" action="{{ route('properties.destroy', $property) }}"
                              data-confirm="Delete this property?"
                              data-confirm-type="error"
                              data-confirm-message="The property and its units will be permanently deleted. This cannot be undone."
                              data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="w-full text-left px-4 py-2.5 text-sm text-[#DC2626] hover:bg-[#EF4444]/[0.07] transition-colors">
                                Delete Property
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Key facts strip, attached to the hero --}}
    <dl class="grid grid-cols-2 sm:grid-cols-4 mb-6 bg-white border border-t-0 border-[#E2E4EC] rounded-b-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
        @foreach($keyFacts as [$label, $value, $suffix, $chipBg, $chipFg, $icon])
            <div class="min-w-0 px-5 py-3.5 flex items-center gap-3 border-[#E2E4EC] max-sm:[&:nth-child(n+3)]:border-t max-sm:even:border-l sm:[&:not(:first-child)]:border-l">
                {{-- Icon chips hide on phones: the 2-up grid has no room for them next to a rent range. --}}
                <span class="hidden sm:flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $chipBg }} {{ $chipFg }}" aria-hidden="true">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                </span>
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">{{ $label }}</dt>
                    <dd class="mt-0.5 truncate text-[#060D26] tabular-nums">
                        <span class="text-xl font-semibold">{{ $value }}</span>
                        @if($suffix)<span class="ml-1 text-xs font-normal text-[#5B6A8E]">{{ $suffix }}</span>@endif
                    </dd>
                </div>
            </div>
        @endforeach
    </dl>

    {{-- ═══════════════════════════════════════════════════════
         NEEDS ATTENTION — only renders when something is actionable
    ══════════════════════════════════════════════════════════ --}}
    @php
        $attention = [];
        if ($unitStats['total'] === 0) {
            $attention[] = ['Add your first unit — tenants can\'t reserve until this property has one.', route('landlord.properties.units.create', $property), 'Add unit'];
        }
        if ($unitStats['reserved'] > 0) {
            $attention[] = [$unitStats['reserved'] . ' ' . Str::plural('unit', $unitStats['reserved']) . ' reserved — check the reservation is moving forward.', route('landlord.properties.units.index', $property), 'Manage units'];
        }
        if ($images->isEmpty()) {
            $attention[] = ['No photos yet — listings with photos get far more views.', route('properties.edit', $property), 'Add photos'];
        }
        if ($documentNudgeCount > 0) {
            $attention[] = [$documentNudgeCount . ' ' . Str::plural('document', $documentNudgeCount) . ' rejected or requested by admin.', route('landlord.properties.documents.index', $property), 'Open documents'];
        }
        if ($property->publication_status === 'Unpublished') {
            $attention[] = ['This listing is unpublished and hidden from tenants.', null, null];
        }
    @endphp
    @if($attention)
        <div class="mb-6 rounded-2xl border border-[#FBBF24]/40 bg-[#FBBF24]/[0.08] px-4 py-3" role="status">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#B45309] mb-1.5">Needs attention</p>
            <ul class="space-y-1.5">
                @foreach($attention as [$text, $url, $cta])
                    <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-3 text-[13px] text-[#060D26]">
                        <span>{{ $text }}</span>
                        @if($url)
                            <a href="{{ $url }}" class="shrink-0 font-semibold text-[#B35A3D] hover:underline">{{ $cta }} &rarr;</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════
         BODY: status sidebar (left on desktop, below the tabs on phones) | tabs
    ══════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-[240px_minmax(0,1fr)] gap-6 items-start">

    <aside class="order-2 lg:order-1 space-y-4">
        <x-card flush class="p-5">
            <h3 class="text-[14px] font-semibold text-[#060D26]">Listing status</h3>

            <dl class="mt-3 space-y-2.5 text-[13px]">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#5B6A8E]">Verification</dt>
                    <dd class="inline-flex items-center gap-1.5 font-semibold text-[#060D26]">
                        <span class="h-1.5 w-1.5 rounded-full {{ $dotFor($property->verification_status) }}" aria-hidden="true"></span>
                        {{ $property->verification_status }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#5B6A8E]">Visibility</dt>
                    <dd><x-publication-status-badge :status="$property->publication_status" class="text-[13px]" /></dd>
                </div>
            </dl>

            @if($property->publication_status === 'Suspended')
                <p class="mt-4 rounded-xl bg-[#EF4444]/[0.06] px-3 py-2 text-[12px] leading-relaxed text-[#DC2626]">
                    This listing was suspended by an admin and is hidden from tenants. Contact support if you believe this was a mistake.
                </p>
            @elseif($property->publication_status === 'Published')
                <form method="POST" action="{{ route('properties.unpublish', $property) }}" class="mt-4"
                    data-confirm="Unpublish this listing?"
                    data-confirm-message="It will be hidden from tenants until you publish it again. You can do this anytime.">
                    @csrf
                    <button type="submit"
                        class="w-full h-9 inline-flex items-center justify-center rounded-lg border border-[#E2E4EC] hover:border-[#060D26]/40 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 cursor-pointer">
                        Unpublish
                    </button>
                </form>
            @elseif($property->publication_status === 'Unpublished')
                <form method="POST" action="{{ route('properties.publish', $property) }}" class="mt-4">
                    @csrf
                    <button type="submit"
                        class="w-full h-9 rounded-lg bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13px] font-semibold transition-colors duration-200 cursor-pointer">
                        Publish
                    </button>
                </form>
            @endif

            <p class="mt-3 text-[12px] text-[#5B6A8E]">
                <span class="font-mono">PRP-{{ str_pad($property->property_id, 4, '0', STR_PAD_LEFT) }}</span>
                &middot; Created {{ $property->created_at->format('M d, Y') }}
            </p>
        </x-card>

        <x-card flush class="p-5">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-[14px] font-semibold text-[#060D26]">Unit status</h3>
                <a href="{{ route('landlord.properties.units.index', $property) }}"
                   class="text-[12px] font-semibold text-[#B35A3D] hover:underline">Manage</a>
            </div>
            <ul class="mt-3 space-y-3">
                @foreach([['Available', 'available', 'bg-[#22C55E]'], ['Reserved', 'reserved', 'bg-[#FBBF24]'], ['Occupied', 'occupied', 'bg-[#EF4444]']] as [$statLabel, $statKey, $statBar])
                    <li>
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="inline-flex items-center gap-2 {{ $unitStats[$statKey] > 0 ? 'font-semibold text-[#060D26]' : 'font-medium text-[#5B6A8E]' }}"><span class="h-1.5 w-1.5 rounded-full {{ $statBar }}" aria-hidden="true"></span>{{ $statLabel }}</span>
                            <span class="tabular-nums {{ $unitStats[$statKey] > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $unitStats[$statKey] }}</span>
                        </div>
                        <div class="mt-1.5 h-1 rounded-full bg-[#ECEEF6] overflow-hidden">
                            <div class="h-full rounded-full {{ $statBar }}" style="width: {{ $unitStats['total'] > 0 ? round($unitStats[$statKey] / $unitStats['total'] * 100) : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </aside>

    {{-- ═══════════════════════════════════════════════════════
         TABS
    ══════════════════════════════════════════════════════════ --}}
    <div x-data="{ tab: 'overview' }" class="order-1 lg:order-2 min-w-0">

        {{-- Tab nav --}}
        <div class="flex items-center gap-1 border-b border-[#5B6A8E]/20 mb-6 overflow-x-auto">
            @foreach([
                ['key' => 'overview',     'label' => 'Overview'],
                ['key' => 'units',        'label' => 'Units (' . $property->units->count() . ')'],
                ['key' => 'amenities',    'label' => 'Amenities'],
                ['key' => 'photos',       'label' => 'Photos (' . $images->count() . ')'],
                ['key' => 'reviews',      'label' => 'Reviews (' . $reviews->count() . ')'],
                ['key' => 'activity_log', 'label' => 'Activity Log'],
            ] as $t)
                <button @click="tab = '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}' ? 'border-b-2 border-[#FF8A66] text-[#060D26] font-semibold' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="shrink-0 pb-3 px-4 text-sm transition-colors duration-200">
                    {{ $t['label'] }}
                </button>
            @endforeach
        </div>

        {{-- ─── Overview tab ─────────────────────────────────── --}}
        <div x-show="tab === 'overview'" x-cloak>

            {{-- About + Location, stacked --}}
            @php
                $utilityLabels = [
                    'water_included' => 'Water',
                    'electricity_included' => 'Electricity',
                    'internet_included' => 'Internet',
                    'association_fees_included' => 'Association fees',
                ];
                $utilitiesIn = collect($utilityLabels)->filter(fn ($l, $k) => $property->{$k})->values();
                $utilitiesOut = collect($utilityLabels)->reject(fn ($l, $k) => $property->{$k})->values();
            @endphp
            <div class="space-y-5 mb-5">
                <x-card flush class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-[#060D26]">About</h3>
                    @if($property->description)
                        <p class="mt-3 text-[14px] leading-relaxed text-[#060D26]/80">{{ $property->description }}</p>
                    @else
                        <p class="mt-3 text-[14px] text-[#5B6A8E]">No description yet. <a href="{{ route('properties.edit', $property) }}" class="font-semibold text-[#B35A3D] hover:underline">Add one</a></p>
                    @endif

                    <dl class="mt-5 flex flex-wrap gap-x-10 gap-y-4">
                        @if($property->living_arrangement)
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Living arrangement</dt>
                                <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $property->living_arrangement }}</dd>
                            </div>
                        @endif
                        @if($property->occupancy_preference && $property->occupancy_preference !== 'No Preference')
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Occupancy</dt>
                                <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $property->occupancy_preference }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Included in rent</dt>
                            <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $utilitiesIn->isNotEmpty() ? $utilitiesIn->implode(', ') : 'Nothing' }}</dd>
                        </div>
                    </dl>

                    @if(! empty($property->house_rules))
                        <div class="mt-5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">House rules</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($property->house_rules as $rule)
                                    @php $permits = preg_match('/\ballowed\b/i', $rule) && ! preg_match('/^no\b/i', $rule); @endphp
                                    <span class="inline-flex items-center text-xs font-medium rounded-full px-3 py-1.5 {{ $permits ? 'bg-[#22C55E]/10 text-[#15803D]' : 'bg-[#ECEEF6] text-[#060D26]' }}">{{ $rule }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($utilitiesOut->isNotEmpty())
                        <p class="mt-4 text-[12.5px] text-[#5B6A8E]">
                            Billed separately: {{ $utilitiesOut->map(fn ($l) => Str::lower($l))->implode(', ') }}@if($property->utilities_separately_metered) &mdash; separately metered @endif
                        </p>
                    @endif
                </x-card>

                <x-card flush class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-[#060D26] mb-3">Location</h3>
                    <div id="property-map"
                         data-lat="{{ $property->latitude }}"
                         data-lng="{{ $property->longitude }}"
                         data-title="{{ $property->title }}"
                         class="w-full h-56 sm:h-64 rounded-xl overflow-hidden border border-[#5B6A8E]/20 bg-[#ECEEF6] mb-3">
                    </div>
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center w-full h-9 rounded-xl border border-[#5B6A8E]/30 text-sm text-[#060D26] font-medium hover:bg-[#ECEEF6] transition-colors duration-200">
                        Open in Google Maps
                    </a>
                </x-card>
            </div>

            {{-- Recent Activity --}}
            <x-card flush class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-[#060D26]">Recent Activity</h3>
                    <a href="{{ route('landlord.properties.units.index', $property) }}"
                       class="text-xs text-[#B35A3D] font-medium hover:underline flex items-center gap-1">
                        View All Activity
                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </a>
                </div>

                @php
                    $recentUnits = $property->units->sortByDesc('updated_at')->take(3);
                @endphp

                @if($recentUnits->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach($recentUnits as $recentUnit)
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-[#ECEEF6]/50">
                                <div class="w-8 h-8 rounded-full bg-[#ECEEF6] flex items-center justify-center shrink-0 mt-0.5">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#B35A3D]">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[12px] font-semibold text-[#060D26] leading-snug">Unit "{{ $recentUnit->unit_label }}" updated</p>
                                    <p class="text-[11px] text-[#5B6A8E] mt-0.5">{{ $recentUnit->updated_at->format('M d, Y \a\t h:i A') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-[#5B6A8E]/40 mb-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                        <p class="text-sm text-[#5B6A8E]">No recent activity</p>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- ─── Units tab ─────────────────────────────────────── --}}
        <div x-show="tab === 'units'" x-cloak
            x-data="{ unitsView: localStorage.getItem('propertyUnitsView') || 'grid', setUnitsView(v) { this.unitsView = v; localStorage.setItem('propertyUnitsView', v); } }">
            @if($property->units->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-[#ECEEF6]/40 flex flex-col items-center justify-center py-14 text-center">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/50 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z"/>
                    </svg>
                    <p class="text-sm font-semibold text-[#060D26]">No units added yet</p>
                    <p class="text-xs text-[#5B6A8E] mt-1 mb-4">Add units so tenants can reserve specific spaces.</p>
                    <a href="{{ route('landlord.properties.units.create', $property) }}"
                       class="inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-[#FF8A66] text-[#060D26] text-xs font-semibold hover:bg-[#E96F4F] transition-all duration-200">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add First Unit
                    </a>
                </div>
            @else
                <div class="flex justify-end mb-4">
                    <div class="flex items-center gap-0.5 p-1 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC]">
                        <button type="button" x-on:click="setUnitsView('grid')"
                            :class="unitsView === 'grid' ? 'bg-white shadow-sm text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors duration-200 cursor-pointer" title="Grid view">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z"/>
                            </svg>
                        </button>
                        <button type="button" x-on:click="setUnitsView('list')"
                            :class="unitsView === 'list' ? 'bg-white shadow-sm text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors duration-200 cursor-pointer" title="List view">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-show="unitsView === 'grid'">
                    @foreach($property->units as $unit)
                        @php
                            $thumb = $unit->media->first();
                            [$avBg] = match($unit->availability_status) {
                                'Available' => ['bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25'],
                                'Reserved'  => ['bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35'],
                                'Occupied'  => ['bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25'],
                                default     => ['bg-[#ECEEF6] text-[#5B6A8E] ring-[#5B6A8E]/20'],
                            };
                            [$vrBg] = match($unit->verification_status) {
                                'Approved' => ['bg-[#22C55E]/[0.07] text-[#15803D]'],
                                'Pending'  => ['bg-[#FBBF24]/[0.10] text-[#B45309]'],
                                'Rejected' => ['bg-[#EF4444]/[0.07] text-[#DC2626]'],
                                default    => ['bg-[#ECEEF6] text-[#5B6A8E]'],
                            };
                        @endphp
                        <div class="flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#5B6A8E]/15 hover:shadow-md transition-shadow duration-300">
                            <div class="relative h-36 bg-[#ECEEF6] overflow-hidden">
                                @if($thumb)
                                    <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/40">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5z"/>
                                        </svg>
                                    </div>
                                @endif
                                <span class="absolute top-2 right-2 text-[11px] font-semibold px-2 py-0.5 rounded-full ring-1 {{ $avBg }}">
                                    {{ $unit->availability_status }}
                                </span>
                            </div>
                            <div class="p-3 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-[13px] font-bold text-[#060D26]">{{ $unit->unit_label }}</p>
                                        <p class="text-[12px] text-[#EF4444] font-semibold mt-0.5">
                                            ₱{{ number_format($unit->rental_fee, 0) }}<span class="text-[#5B6A8E] font-normal">/mo</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-[11px] font-medium px-2 py-0.5 rounded-full {{ $vrBg }}">
                                        {{ $unit->verification_status }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-[#5B6A8E] flex items-center gap-1">
                                    <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    Up to {{ $unit->occupancy_limit }} {{ Str::plural('person', $unit->occupancy_limit) }}
                                </p>
                                <div class="flex items-center gap-2 pt-2 border-t border-[#5B6A8E]/10">
                                    <a href="{{ route('landlord.properties.units.edit', [$property, $unit]) }}"
                                       class="flex-1 h-8 flex items-center justify-center gap-1 rounded-full border border-[#5B6A8E]/30 text-[#060D26] text-[11px] font-medium hover:bg-[#ECEEF6] transition-colors duration-200">
                                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z"/>
                                        </svg>
                                        Edit
                                    </a>
                                    <form method="POST"
                                          action="{{ route('landlord.properties.units.destroy', [$property, $unit]) }}"
                                          data-confirm="Remove {{ $unit->unit_label }}?"
                                          data-confirm-type="error"
                                          data-confirm-message="The unit will be permanently removed. This cannot be undone."
                                          data-confirm-button="Remove unit">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="h-8 px-3 flex items-center gap-1 rounded-full border border-[#EF4444]/25 text-[#DC2626] text-[11px] font-medium hover:bg-[#EF4444]/[0.07] transition-colors duration-200">
                                            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="overflow-x-auto rounded-2xl border border-[#5B6A8E]/15 bg-white" x-show="unitsView === 'list'" x-cloak>
                    <table class="w-full min-w-[640px] text-left">
                        <thead>
                            <tr class="border-b border-[#E2E4EC]">
                                <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Rent</th>
                                <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Verification</th>
                                <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($property->units as $unit)
                                @php
                                    $thumb = $unit->media->first();
                                    [$avBg] = match($unit->availability_status) {
                                        'Available' => ['bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25'],
                                        'Reserved'  => ['bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35'],
                                        'Occupied'  => ['bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25'],
                                        default     => ['bg-[#ECEEF6] text-[#5B6A8E] ring-[#5B6A8E]/20'],
                                    };
                                    [$vrBg] = match($unit->verification_status) {
                                        'Approved' => ['bg-[#22C55E]/[0.07] text-[#15803D]'],
                                        'Pending'  => ['bg-[#FBBF24]/[0.10] text-[#B45309]'],
                                        'Rejected' => ['bg-[#EF4444]/[0.07] text-[#DC2626]'],
                                        default    => ['bg-[#ECEEF6] text-[#5B6A8E]'],
                                    };
                                @endphp
                                <tr class="hover:bg-[#ECEEF6] transition-colors duration-200">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-lg bg-[#ECEEF6] overflow-hidden shrink-0 ring-1 ring-[#5B6A8E]/10">
                                                @if($thumb)
                                                    <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                            <p class="text-[13px] font-bold text-[#060D26]">{{ $unit->unit_label }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-[13px] text-[#DC2626] font-semibold whitespace-nowrap">
                                        ₱{{ number_format($unit->rental_fee, 0) }}<span class="text-[#5B6A8E] font-normal">/mo</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full ring-1 {{ $avBg }}">
                                            {{ $unit->availability_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full {{ $vrBg }}">
                                            {{ $unit->verification_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('landlord.properties.units.edit', [$property, $unit]) }}"
                                                class="h-8 px-3 flex items-center gap-1 rounded-full border border-[#5B6A8E]/30 text-[#060D26] text-[11px] font-medium hover:bg-[#ECEEF6] transition-colors duration-200">
                                                Edit
                                            </a>
                                            <form method="POST"
                                                action="{{ route('landlord.properties.units.destroy', [$property, $unit]) }}"
                                                data-confirm="Remove {{ $unit->unit_label }}?"
                                                data-confirm-type="error"
                                                data-confirm-message="The unit will be permanently removed. This cannot be undone."
                                                data-confirm-button="Remove unit">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="h-8 px-3 flex items-center gap-1 rounded-full border border-[#EF4444]/25 text-[#DC2626] text-[11px] font-medium hover:bg-[#EF4444]/[0.07] transition-colors duration-200">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ─── Amenities tab ──────────────────────────────────── --}}
        <div x-show="tab === 'amenities'" x-cloak>
            @if($property->amenities->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-[#ECEEF6]/40 flex flex-col items-center justify-center py-14 text-center">
                    <p class="text-sm font-semibold text-[#060D26]">No amenities listed</p>
                    <p class="text-xs text-[#5B6A8E] mt-1">Edit this property to add amenities.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($property->amenities as $amenity)
                        <x-card flush class="flex items-center gap-3 px-4 py-3">
                            <div class="w-8 h-8 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0">
                                <x-amenity-icon :name="$amenity->amenity_name" class="w-3.5 h-3.5 text-[#B35A3D]" />
                            </div>
                            <span class="text-sm font-medium text-[#060D26]">{{ $amenity->amenity_name }}</span>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Photos tab ─────────────────────────────────────── --}}
        <div x-show="tab === 'photos'" x-cloak>
            @if($images->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-[#ECEEF6]/40 flex flex-col items-center justify-center py-14 text-center">
                    <p class="text-sm font-semibold text-[#060D26]">No photos uploaded</p>
                    <p class="text-xs text-[#5B6A8E] mt-1">Edit this property to add photos.</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($images as $i => $img)
                        <button onclick="openLightbox({{ $i }})"
                                class="aspect-square rounded-xl overflow-hidden bg-[#ECEEF6] hover:opacity-90 transition-opacity duration-200">
                            <img loading="lazy" decoding="async" src="{{ $img->media_url }}" alt="{{ $property->title }} photo {{ $i + 1 }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Reviews tab ─────────────────────────────────────── --}}
        <div x-show="tab === 'reviews'" x-cloak>
            @php
                $visibleReviews = $reviews->where('is_hidden', false);
                $reviewAvg = $visibleReviews->count() > 0 ? round($visibleReviews->avg('rating'), 1) : null;
            @endphp

            @if($visibleReviews->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-[#ECEEF6]/40 flex flex-col items-center justify-center py-14 text-center">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/50 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/>
                    </svg>
                    <p class="text-sm font-semibold text-[#060D26]">No reviews yet</p>
                    <p class="text-xs text-[#5B6A8E] mt-1">Reviews will appear here once tenants submit them.</p>
                </div>
            @else
                {{-- Summary bar --}}
                <div class="flex items-center gap-4 mb-5">
                    <div class="flex items-center gap-1.5 bg-[#ECEEF6] px-3 py-1.5 rounded-lg">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="none">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <span class="text-sm font-bold text-[#060D26]">{{ $reviewAvg }}</span>
                        <span class="text-xs text-[#5B6A8E]">/ 5</span>
                    </div>
                    <span class="text-sm text-[#5B6A8E]">{{ $visibleReviews->count() }} {{ Str::plural('review', $visibleReviews->count()) }}</span>
                </div>

                <div class="space-y-3">
                    @foreach($visibleReviews as $review)
                        <x-card flush class="p-5">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-[#ECEEF6] flex items-center justify-center shrink-0 text-sm font-bold text-[#060D26]">
                                        {{ strtoupper(substr($review->tenant->first_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-[#060D26]">{{ $review->tenant->first_name }} {{ $review->tenant->last_name }}</p>
                                        <p class="text-[11px] text-[#5B6A8E]">{{ $review->created_at->format('M d, Y') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5 shrink-0">
                                    @for($s = 1; $s <= 5; $s++)
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $s <= $review->rating ? '#f59e0b' : 'none' }}" stroke="#f59e0b" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>

                            @if($review->review_comment)
                                <p class="text-sm text-[#060D26]/75 leading-relaxed mb-3">{{ $review->review_comment }}</p>
                            @endif

                            {{-- Existing landlord reply --}}
                            @if($review->landlord_reply)
                                <div class="mt-3 bg-[#ECEEF6] rounded-xl p-3.5">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <div class="w-6 h-6 rounded-lg bg-[#060D26] text-white text-[11px] font-black flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}
                                        </div>
                                        <span class="text-xs font-semibold text-[#060D26]">You</span>
                                        <span class="text-[11px] text-[#5B6A8E]">{{ $review->landlord_replied_at->format('M d, Y') }}</span>
                                    </div>
                                    <p class="text-sm text-[#060D26]/75 leading-relaxed pl-8">{{ $review->landlord_reply }}</p>
                                </div>

                                {{-- Edit reply --}}
                                <div class="mt-2 pl-8" x-data="{ editing: false }">
                                    <button type="button" x-on:click="editing = !editing"
                                        class="text-xs font-medium text-[#5B6A8E] hover:text-[#060D26] transition-colors">
                                        Edit reply
                                    </button>
                                    <div x-show="editing" x-cloak class="mt-2">
                                        <form action="{{ route('landlord.reviews.reply', $review->review_id) }}" method="POST" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="landlord_reply" rows="2" maxlength="1000"
                                                class="w-full border border-[#5B6A8E]/20 rounded-xl px-3 py-2 text-sm text-[#060D26] bg-white focus:outline-none focus:ring-2 focus:ring-[#060D26]/20 focus:border-[#060D26] transition-all resize-none">{{ $review->landlord_reply }}</textarea>
                                            <div class="flex items-center gap-2">
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-lg bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-xs font-semibold transition-all">
                                                    Update Reply
                                                </button>
                                                <button type="button" x-on:click="editing = false"
                                                    class="px-4 py-2 rounded-lg bg-[#ECEEF6] text-[#5B6A8E] text-xs font-medium hover:bg-[#E2E4EC] transition-all">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- Reply form --}}
                                <div class="mt-3" x-data="{ showReply: false }">
                                    <button type="button" x-on:click="showReply = !showReply"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#060D26] hover:brightness-95 transition-all">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                        </svg>
                                        Reply to this review
                                    </button>
                                    <div x-show="showReply" x-cloak class="mt-2">
                                        <form action="{{ route('landlord.reviews.reply', $review->review_id) }}" method="POST" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="landlord_reply" rows="2" maxlength="1000"
                                                placeholder="Write your response..."
                                                class="w-full border border-[#5B6A8E]/20 rounded-xl px-3 py-2 text-sm text-[#060D26] bg-white focus:outline-none focus:ring-2 focus:ring-[#060D26]/20 focus:border-[#060D26] transition-all resize-none"></textarea>
                                            <div class="flex items-center gap-2">
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-lg bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-xs font-semibold transition-all">
                                                    Post Reply
                                                </button>
                                                <button type="button" x-on:click="showReply = false"
                                                    class="px-4 py-2 rounded-lg bg-[#ECEEF6] text-[#5B6A8E] text-xs font-medium hover:bg-[#E2E4EC] transition-all">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Activity Log tab ───────────────────────────────── --}}
        <div x-show="tab === 'activity_log'" x-cloak>
            <div class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-[#ECEEF6]/40 flex flex-col items-center justify-center py-14 text-center">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/50 mb-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                </svg>
                <p class="text-sm font-semibold text-[#060D26]">Activity log coming soon</p>
                <p class="text-xs text-[#5B6A8E] mt-1">A full history of actions on this property will appear here.</p>
            </div>
        </div>

    </div>

    </div>{{-- /body grid --}}
</div>

{{-- ═══════════════════════════════════════════════════════════
     Lightbox
══════════════════════════════════════════════════════════════ --}}
@if($images->isNotEmpty())
    <div id="lightbox"
         class="fixed inset-0 z-[999] bg-black/90 hidden items-center justify-center"
         onclick="closeLightbox()">
        <button type="button"
                class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="closeLightbox()">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
        <button type="button"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(-1)">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <img id="lb-img" src="" alt=""
             class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg select-none"
             onclick="event.stopPropagation()">
        <button type="button"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(1)">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        <div class="absolute bottom-5 left-1/2 -translate-x-1/2 text-white/70 text-[13px] font-medium">
            <span id="lb-counter"></span>
        </div>
    </div>
@endif

@push('scripts')
<script>
(function () {
    const mediaUrls = @json($images->pluck('media_url')->values());
    const total = mediaUrls.length;
    let currentIndex = 0;

    const lightbox  = document.getElementById('lightbox');
    const lbImg     = document.getElementById('lb-img');
    const lbCounter = document.getElementById('lb-counter');

    function updateLightbox() {
        if (lbImg)     lbImg.src = mediaUrls[currentIndex];
        if (lbCounter) lbCounter.textContent = (currentIndex + 1) + ' / ' + total;
    }

    window.openLightbox = function (index) {
        currentIndex = index;
        updateLightbox();
        if (lightbox) { lightbox.classList.remove('hidden'); lightbox.classList.add('flex'); }
        document.body.style.overflow = 'hidden';
    };

    window.closeLightbox = function () {
        if (lightbox) { lightbox.classList.add('hidden'); lightbox.classList.remove('flex'); }
        document.body.style.overflow = '';
    };

    window.shiftLightbox = function (dir) {
        currentIndex = (currentIndex + dir + total) % total;
        updateLightbox();
    };

    window.highlightThumb = function (index) {
        document.querySelectorAll('[id^="thumb-"]').forEach(function(el, i) {
            el.classList.toggle('border-[#060D26]', i === index);
            el.classList.toggle('border-transparent', i !== index);
        });
    };

    document.addEventListener('keydown', function (e) {
        if (!lightbox || lightbox.classList.contains('hidden')) return;
        if (e.key === 'ArrowRight') shiftLightbox(1);
        if (e.key === 'ArrowLeft')  shiftLightbox(-1);
        if (e.key === 'Escape')     closeLightbox();
    });
})();
</script>
@endpush

@endsection
