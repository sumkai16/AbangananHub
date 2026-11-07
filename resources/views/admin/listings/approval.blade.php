@extends('layouts.admin')

@section('page-title', 'Property Verifications')

@section('content')
@vite(['resources/js/maps/admin-property-map.js'])
@php
    $empty = [
        'Pending' => ['check', 'No listings waiting', 'New listings show here once a landlord submits them.'],
        'Approved' => ['inbox', 'No approved listings yet', 'Listings you approve show here.'],
        'Rejected' => ['inbox', 'No rejected listings', 'Listings you reject show here, with your reason.'],
        'Suspended' => ['inbox', 'No suspended listings', 'Listings taken down after a report show here.'],
        'All' => ['inbox', 'No listings yet', 'Listings landlords submit show here.'],
    ][$status] ?? ['inbox', 'No properties here', null];
    $plural = fn ($n, $word) => $n.' '.\Illuminate\Support\Str::plural($word, $n);
@endphp
<div class="admin-compact admin-fit">

    <x-admin.header title="Property Verifications" subtitle="Listings waiting to go live, and what is missing from each.">
        <x-slot:actions>
            <x-admin.stat-line :cells="[
                ['label' => 'Waiting', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
                ['label' => 'Not ready', 'value' => number_format($summary['incomplete']), 'note' => 'No photo or no unit yet'],
                ['label' => 'Approved', 'value' => number_format($summary['approved']), 'note' => 'Last 30 days'],
                ['label' => 'Rejected', 'value' => number_format($summary['rejected']), 'note' => 'Last 30 days'],
            ]" />
        </x-slot:actions>
    </x-admin.header>

    <x-admin.tabs class="mb-3" :tabs="collect(['All', 'Pending', 'Approved', 'Rejected', 'Suspended'])->map(fn ($tab) => [
        'label' => $tab,
        'count' => $counts[$tab],
        'href' => route('admin.listings.approval', ['status' => $tab]),
        'active' => $status === $tab,
    ])->all()" />

    @if (! $selected)
        <x-card flush fill>
            <x-empty-state flat fill :symbol="$empty[0]" :title="$empty[1]" :note="$empty[2]">
                @if ($status !== 'All')
                    <a href="{{ route('admin.listings.approval', ['status' => 'All']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">See all listings</a>
                @endif
            </x-empty-state>
        </x-card>
    @else
        @php
            $p = $selected;
            $photos = $p->media->where('media_type', 'Image')->values();
            $fees = $p->units->pluck('rental_fee')->filter();
            $rentLine = $fees->isEmpty() ? 'No units yet' : ($fees->min() == $fees->max() ? '₱'.number_format($fees->min()) : '₱'.number_format($fees->min()).' to ₱'.number_format($fees->max()));
            $place = collect([$p->barangay, $p->city_municipality])->filter()->implode(', ') ?: $p->address;
            $docsDone = $p->documents->where('status', 'Verified')->count();
            $docsAll = $p->documents->count();
            $landlordName = $p->landlord->name ?? '—';
            // Never pinned: the default Cebu City point, not the property.
            $unpinned = (float) $p->latitude === 10.3157 && (float) $p->longitude === 123.8854;
            // The same five checks as the full property page. A warning, never a block: the admin decides.
            $checks = [
                'photos' => ['Photos', $photos->isNotEmpty(), $photos->count() ? $plural($photos->count(), 'photo').' uploaded' : 'None uploaded yet'],
                'location' => ['Map pin', ! $unpinned, $unpinned ? 'Never pinned: the map shows the Cebu City default' : 'Pinned. Check it sits on the street below'],
                'units' => ['Units', $p->units->count() > 0, $p->units->count() ? $plural($p->units->count(), 'unit').', '.$rentLine : 'No unit added yet'],
                'documents' => ['Documents', $docsAll > 0 && $docsDone === $docsAll, $docsAll ? $docsDone.' of '.$docsAll.' verified' : 'None uploaded'],
                'landlord' => ['Landlord', (bool) $landlordVerified, $landlordVerified ? 'Application approved' : 'Application not approved'],
            ];
            $passing = collect($checks)->filter(fn ($c) => $c[1])->count();
            $utilityLabels = ['water_included' => 'Water', 'electricity_included' => 'Electricity', 'internet_included' => 'Internet', 'association_fees_included' => 'Association fees'];
            $utilitiesIn = collect($utilityLabels)->filter(fn ($l, $k) => $p->{$k})->values();
            $utilitiesOut = collect($utilityLabels)->reject(fn ($l, $k) => $p->{$k})->values();
        @endphp

        <div class="flex min-h-0 flex-1 flex-col gap-3 lg:flex-row">

            {{-- Queue --}}
            <x-card flush fill class="lg:w-[21rem] lg:flex-none">
                <div class="flex items-center justify-between border-b border-[#E2E4EC] px-4 py-2.5">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">{{ number_format($pendingListings->total()) }} {{ $status === 'Pending' ? 'waiting' : 'listings' }}</h2>
                    <span class="text-[12px] text-[#5B6A8E]">{{ match ($status) { 'Pending' => 'Oldest first', 'All' => 'Waiting first', default => 'Newest first' } }}</span>
                </div>
                <ul class="min-h-0 flex-1 overflow-y-auto">
                    @foreach ($pendingListings as $row)
                        @php
                            $isOpen = $row->property_id === $p->property_id;
                            $rowThumb = \App\Support\Images::src(optional($row->media->firstWhere('media_type', 'Image'))->media_url, 'thumb');
                            $rowReady = $row->photos_count > 0 && $row->units->count() > 0;
                        @endphp
                        <li class="border-b border-[#E2E4EC] last:border-b-0">
                            <a href="{{ route('admin.listings.approval', array_filter(['status' => $status, 'open' => $row->property_id, 'page' => $pendingListings->currentPage() > 1 ? $pendingListings->currentPage() : null])) }}"
                                @if ($isOpen) aria-current="true" @endif
                                class="flex gap-2.5 px-3.5 py-2 transition-colors duration-200 {{ $isOpen ? 'bg-[#FFF3EE] ring-2 ring-inset ring-[#FF8A66]' : 'hover:bg-[#F7F8FC]' }}">
                                <span class="h-10 w-12 shrink-0 overflow-hidden rounded-lg {{ $rowThumb ? 'bg-[#ECEEF6]' : 'border-[1.5px] border-dashed border-[#F3B4B4] bg-white' }}">
                                    @if ($rowThumb)<img src="{{ $rowThumb }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] font-semibold text-[#060D26]">{{ $row->title }}</span>
                                    <span class="block truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $row->landlord->name ?? '—' }} · {{ $row->created_at->diffForHumans() }}</span>
                                    <span class="block truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $plural($row->photos_count, 'photo') }} · {{ $plural($row->units->count(), 'unit') }} · docs {{ $row->verified_documents_count }}/{{ $row->documents_count }}</span>
                                </span>
                                @if ($row->verification_status === 'Pending')
                                    <x-admin.status :status="$rowReady ? 'Ready' : 'Not ready'" :tone="$rowReady ? 'green' : 'red'" class="self-start" />
                                @else
                                    <x-admin.status :status="$row->verification_status" class="self-start" />
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-auto border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2.5 text-[13px] text-[#5B6A8E]">
                    @if ($pendingListings->hasPages())
                        {{ $pendingListings->links() }}
                    @else
                        Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $pendingListings->firstItem() }}–{{ $pendingListings->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($pendingListings->total()) }}</span>
                    @endif
                </div>
            </x-card>

            {{-- The selected listing --}}
            <x-card flush fill class="min-w-0 flex-1">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#E2E4EC] px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-[17px] font-semibold leading-6 tracking-tight text-[#060D26]">{{ $p->title }}</h2>
                            <x-admin.status :status="$p->verification_status" />
                            @if ($p->publication_status !== 'Published')<x-admin.status :status="$p->publication_status" />@endif
                        </div>
                        <p class="text-[13px] leading-5 text-[#5B6A8E]">{{ $landlordName }} @if ($landlordVerified)· Verified landlord @endif · Sent {{ $p->created_at->format('M j, Y') }}, {{ $p->created_at->diffForHumans() }}</p>
                    </div>
                    <a href="{{ route('admin.catalogue.properties.show', $p) }}" class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Open full page</a>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    <div class="grid items-start gap-5 xl:grid-cols-2">
                        <div>
                            @if ($photos->isEmpty())
                                <div class="grid h-64 place-items-center rounded-xl border-[1.5px] border-dashed border-[#F3B4B4] bg-white text-[13px] font-semibold text-[#B91C1C]">No photos uploaded</div>
                            @else
                                {{-- One photo at a time; Next and Previous (or the arrow keys) move through them. The image opens full size in a new tab. --}}
                                <div x-data="{ i: 0, n: {{ $photos->count() }}, next() { this.i = (this.i + 1) % this.n }, prev() { this.i = (this.i - 1 + this.n) % this.n } }"
                                    @keydown.right="next()" @keydown.left="prev()" tabindex="0" role="group" aria-roledescription="carousel" aria-label="Photos of {{ $p->title }}"
                                    class="relative h-64 overflow-hidden rounded-xl bg-[#ECEEF6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                                    @foreach ($photos as $i => $photo)
                                        <a href="{{ \App\Support\Images::src($photo->media_url, 'full') }}" target="_blank" rel="noopener" x-show="i === {{ $i }}" @if ($i > 0) x-cloak @endif class="absolute inset-0" aria-label="Open photo {{ $i + 1 }} full size">
                                            <img src="{{ \App\Support\Images::src($photo->media_url, 'gallery') }}" alt="Photo {{ $i + 1 }} of {{ $p->title }}" @if ($i > 0) loading="lazy" @endif decoding="async" class="h-full w-full object-cover">
                                        </a>
                                    @endforeach
                                    @if ($photos->count() > 1)
                                        <button type="button" @click="prev()" aria-label="Previous photo" class="absolute left-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-[#060D26] shadow-sm transition-colors duration-200 hover:bg-white cursor-pointer">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                        </button>
                                        <button type="button" @click="next()" aria-label="Next photo" class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-[#060D26] shadow-sm transition-colors duration-200 hover:bg-white cursor-pointer">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                        </button>
                                    @endif
                                    <span class="pointer-events-none absolute bottom-2 right-2 rounded-full bg-[#060D26]/70 px-2.5 py-0.5 text-[12px] font-semibold tabular-nums text-white" aria-live="polite"><span x-text="i + 1"></span> / {{ $photos->count() }}</span>
                                </div>
                            @endif

                        </div>
                        {{-- Sized to sit level with the photo beside it: rent first, then the details, then who is behind the listing. --}}
                        <div class="flex flex-col gap-3 rounded-xl border border-[#E2E4EC] bg-white p-4 xl:h-64">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Rent</p>
                                <p class="text-[22px] font-semibold leading-7 tracking-tight tabular-nums text-[#060D26]">{{ $rentLine }}@if ($fees->isNotEmpty())<span class="ml-1 text-[13px] font-normal tracking-normal text-[#5B6A8E]">a month</span>@endif</p>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-5 gap-y-2.5 border-t border-[#E2E4EC] pt-3">
                                <div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Type</dt><dd class="truncate text-[13px] font-semibold text-[#060D26]" title="{{ $p->property_type }}@if ($p->living_arrangement) · {{ $p->living_arrangement }}@endif">{{ $p->property_type }}@if ($p->living_arrangement) · {{ $p->living_arrangement }}@endif</dd></div>
                                <div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Included in rent</dt><dd class="truncate text-[13px] font-semibold text-[#060D26]">{{ $utilitiesIn->isNotEmpty() ? $utilitiesIn->implode(', ') : 'Nothing' }}</dd></div>
                            </dl>
                            <p class="flex items-start gap-2 text-[13px] leading-5 text-[#060D26]">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="mt-[3px] shrink-0 text-[#5B6A8E]" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                <span class="min-w-0"><span class="sr-only">Address: </span>{{ $p->address ?: ($place ?: 'No address given') }}</span>
                            </p>
                            <div class="mt-auto flex items-center gap-2.5 border-t border-[#E2E4EC] pt-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#ECEEF6] text-[11px] font-bold text-[#060D26]" aria-hidden="true">{{ strtoupper(collect(explode(' ', $landlordName))->filter()->take(2)->map(fn ($w) => substr($w, 0, 1))->implode('')) }}</span>
                                <div class="min-w-0 flex-1"><p class="truncate text-[13px] font-semibold leading-4 text-[#060D26]">{{ $landlordName }}</p><p class="truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $p->landlord->email ?? '' }}</p></div>
                                <x-admin.status :status="$landlordVerified ? 'Verified' : 'Not verified'" :tone="$landlordVerified ? 'green' : 'amber'" />
                            </div>
                        </div>
                    <section aria-label="About" class="xl:col-span-2">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">About</h3>
                        <p class="mt-1.5 max-w-[75ch] text-[13px] leading-5 {{ $p->description ? 'text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $p->description ?: 'The landlord has not written a description.' }}</p>
                        @if (! empty($p->house_rules))
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($p->house_rules as $rule)<span class="inline-flex h-6 items-center rounded-full bg-[#ECEEF6] px-2.5 text-[12px] font-medium text-[#060D26]">{{ $rule }}</span>@endforeach
                            </div>
                        @endif
                        @if ($utilitiesOut->isNotEmpty())
                            <p class="mt-2 text-[12.5px] text-[#5B6A8E]">Billed separately: {{ $utilitiesOut->map(fn ($l) => \Illuminate\Support\Str::lower($l))->implode(', ') }}@if ($p->utilities_separately_metered), separately metered @endif</p>
                        @endif
                    </section>
                    {{-- The five checks, the thing an approval turns on, so it gets the tinted panel. --}}
                    <section aria-labelledby="ready-{{ $p->property_id }}" class="rounded-xl border {{ $passing === count($checks) ? 'border-[#BFE3CC] bg-[#F3FAF5]' : 'border-[#F4D58A] bg-[#FFFBEF]' }} p-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                            <h3 id="ready-{{ $p->property_id }}" class="text-[13px] font-semibold text-[#060D26]">{{ $passing }} of {{ count($checks) }} checks pass</h3>
                            <p class="text-[12.5px] text-[#5B6A8E]">{{ $passing === count($checks) ? 'Nothing is missing.' : 'A warning, not a block: you can still decide.' }}</p>
                        </div>
                        <div class="mt-2 flex h-1.5 gap-0.5" role="img" aria-label="{{ $passing }} of {{ count($checks) }} checks pass">
                            @foreach ($checks as $c)<span class="flex-1 rounded-full {{ $c[1] ? 'bg-[#22C55E]' : 'bg-[#FBBF24]' }}"></span>@endforeach
                        </div>
                        <ul class="mt-2 divide-y divide-[#060D26]/[0.06]">
                            @foreach ($checks as $c)
                                @php $tone = $c[1] ? 'bg-[#E6F4EA] text-[#166534]' : 'bg-[#FEF3D6] text-[#92400E]'; @endphp
                                <li class="flex items-center gap-3 py-1.5 text-[13px]">
                                    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full {{ $tone }}" aria-hidden="true"><svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c[1] ? 'M5 13l4 4L19 7' : 'M12 7v6m0 4h.01' }}" /></svg></span>
                                    <span class="w-24 shrink-0 font-semibold text-[#060D26]">{{ $c[0] }}<span class="sr-only">: {{ $c[1] ? 'in place' : 'needs attention' }}</span></span>
                                    <span class="text-[#5B6A8E]">{{ $c[2] }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($docsAll)
                            <ul class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($p->documents as $doc)
                                    <li><a href="{{ route('admin.documents.show', $doc) }}" class="inline-flex"><x-admin.status :status="$doc->document_type.': '.($doc->isRequested() ? 'Requested' : $doc->display_status)" /></a></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                    {{-- Check 2 of the checklist, "the address and the map pin agree", needs the map in view. Collapsible: it opens by
                         default (the pin is worth a look) and the map measures itself again when it is shown. --}}
                    <section aria-label="Location" x-data="{ mapOpen: true }">
                        <button type="button" @click="mapOpen = ! mapOpen; if (mapOpen) $nextTick(() => window.dispatchEvent(new Event('admin-tab-shown')))" :aria-expanded="mapOpen ? 'true' : 'false'" aria-controls="map-{{ $p->property_id }}"
                            class="mb-1.5 flex w-full items-center justify-between gap-3 text-left cursor-pointer">
                            <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true" class="transition-transform duration-200" :class="mapOpen || '-rotate-90'"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                Location
                            </span>
                            <span class="min-w-0 truncate text-[12px] {{ $unpinned ? 'font-semibold text-[#B45309]' : 'text-[#5B6A8E]' }}"><span x-show="mapOpen">{{ $unpinned ? 'Never pinned: the map shows the Cebu City default' : 'Is the pin on the street in the address?' }}</span><span x-show="! mapOpen" x-cloak>{{ $unpinned ? 'Never pinned' : 'Pinned' }} · {{ $p->address ?: 'No address given' }}</span></span>
                        </button>
                        <div id="map-{{ $p->property_id }}" x-show="mapOpen">
                            <div class="relative isolate h-56 overflow-hidden rounded-xl border border-[#E2E4EC] bg-[#ECEEF6]">
                                <div id="admin-property-map" class="h-full w-full" data-lat="{{ $p->latitude }}" data-lng="{{ $p->longitude }}" data-pinned="{{ $unpinned ? '0' : '1' }}"></div>
                                <button type="button" id="admin-property-map-satellite" aria-pressed="false"
                                    class="absolute right-2 top-2 z-[500] inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-2.5 text-[12.5px] font-semibold text-[#060D26] shadow-sm transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer"><span>Satellite</span></button>
                            </div>
                            <p class="mt-1.5 truncate text-[12.5px] text-[#5B6A8E]" title="{{ $p->address }}">{{ $p->address ?: 'No address given' }}</p>
                        </div>
                    </section>
                    </div>


                    {{-- The units, as the landlord sees them on their Units tab, only smaller. --}}
                    <section aria-label="Units">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">{{ $plural($p->units->count(), 'unit') }}</h3>
                            @if ($p->units->count() > 8)<span class="text-[12px] text-[#5B6A8E]">First 8 of {{ $p->units->count() }}; the rest are on the full page</span>@endif
                        </div>
                        @if ($p->units->isEmpty())
                            <p class="rounded-xl border border-dashed border-[#F3B4B4] px-4 py-6 text-center text-[13px] font-semibold text-[#B91C1C]">No unit added yet</p>
                        @else
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-4">
                                @foreach ($p->units->sortBy('unit_label')->take(8) as $unit)
                                    @php
                                        $thumb = $unit->media->firstWhere('media_type', 'Image');
                                        $avTone = match ($unit->availability_status) {
                                            'Available' => 'bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25',
                                            'Reserved' => 'bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35',
                                            'Occupied' => 'bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25',
                                            default => 'bg-[#ECEEF6] text-[#5B6A8E] ring-[#5B6A8E]/20',
                                        };
                                        $vrTone = match ($unit->verification_status) {
                                            'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
                                            'Pending' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                                            'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626]',
                                            default => 'bg-[#ECEEF6] text-[#5B6A8E]',
                                        };
                                    @endphp
                                    <article class="group flex flex-col overflow-hidden rounded-xl bg-white ring-1 ring-[#5B6A8E]/15 transition-shadow duration-300 hover:shadow-md">
                                        <a href="{{ route('admin.units.show', [$p->property_id, $unit->unit_id]) }}" aria-label="Open {{ $unit->unit_label }}" class="relative block aspect-[16/10] overflow-hidden {{ $thumb ? 'bg-[#ECEEF6]' : 'border-b-[1.5px] border-dashed border-[#F3B4B4] bg-white' }}">
                                            @if ($thumb)
                                                <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]">
                                            @else
                                                <span class="absolute inset-0 grid place-items-center text-[12px] font-semibold text-[#B91C1C]">No photos</span>
                                            @endif
                                            <span class="absolute left-2 top-2 rounded-full bg-white"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $avTone }}">{{ $unit->availability_status }}</span></span>
                                        </a>
                                        <div class="flex flex-1 flex-col gap-1.5 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="truncate text-[13.5px] font-bold leading-snug text-[#060D26]">{{ $unit->unit_label }}</p>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $vrTone }}">{{ $unit->verification_status }}</span>
                                            </div>
                                            <p class="text-[13.5px] font-semibold tabular-nums text-[#060D26]">₱{{ number_format($unit->rental_fee, 0) }}<span class="text-[12px] font-normal text-[#5B6A8E]">/mo</span></p>
                                            <p class="text-[12px] text-[#5B6A8E]">Up to {{ $plural($unit->occupancy_limit, 'person') }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($p->verification_status === 'Rejected' && $p->rejection_reason)
                        <p class="rounded-lg bg-[#EF4444]/[0.07] px-3 py-2 text-[13px] leading-5 text-[#060D26]"><span class="font-semibold text-[#DC2626]">Rejected:</span> {{ $p->rejection_reason }}</p>
                    @endif

                    @if ($p->verification_status === 'Pending')
                        <div>
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Before you approve</h3>
                            <p class="mt-0.5 text-[12px] text-[#5B6A8E]">A memory aid only; nothing is saved or blocked.</p>
                            <div class="mt-2 space-y-1.5">
                                @foreach (['The photos show the property and look like the listing.', 'The address and the map pin agree.', 'There is at least one unit with a realistic rent.', 'The landlord is verified and the documents are in order.'] as $line)
                                    <label class="flex items-start gap-2.5 text-[13px] leading-5 text-[#060D26]"><input type="checkbox" class="mt-[3px] h-4 w-4 accent-[#15803D]">{{ $line }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @if ($p->verification_status === 'Pending')
                    <x-admin.decision-bar
                        :approve-action="route('admin.listings.approve', $p->property_id)"
                        approve-label="Approve listing"
                        approve-confirm="Approve this listing?"
                        approve-message="It becomes visible to tenants, with its pending units, and the landlord is told. Check the photos and documents first."
                        :reject-action="route('admin.listings.reject', $p->property_id)"
                        reject-confirm="Reject this listing?"
                        reject-message="The landlord will see your reason and can resubmit."
                        placeholder="Explain what needs to change. The landlord will see this." />
                @elseif ($p->publication_status === 'Suspended')
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3">
                        <p class="text-[13px] text-[#5B6A8E]">This listing is suspended and hidden from tenants.</p>
                        <form method="POST" action="{{ route('admin.listings.unsuspend', $p->property_id) }}"
                            data-confirm="Reinstate this listing?" data-confirm-message="It will become visible to tenants again immediately.">
                            @csrf
                            <button type="submit" class="h-9 rounded-lg bg-[#FF8A66] px-4 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Unsuspend</button>
                        </form>
                    </div>
                @else
                    <div class="mt-auto border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3 text-[13px] text-[#5B6A8E]">Decided. Open the full page to see its history.</div>
                @endif
            </x-card>
        </div>
    @endif

</div>
@endsection
