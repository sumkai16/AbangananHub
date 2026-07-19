@extends('layouts.landlord')

@section('content')
    @php
        $statusStyles = [
            'Available'   => ['tile' => 'border-emerald-200 bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'verb' => 'was made available'],
            'Reserved'    => ['tile' => 'border-amber-200 bg-amber-50',    'text' => 'text-amber-700',   'dot' => 'bg-amber-500',   'verb' => 'was reserved'],
            'Occupied'    => ['tile' => 'border-red-200 bg-red-50',        'text' => 'text-red-600',     'dot' => 'bg-red-500',     'verb' => 'was occupied'],
            'Maintenance' => ['tile' => 'border-slate-200 bg-slate-50',    'text' => 'text-slate-500',   'dot' => 'bg-slate-400',   'verb' => 'is now under maintenance'],
        ];
        $availPctAll = $totalUnits > 0 ? round($availableUnits / $totalUnits * 100) : 0;
        $reservedPctAll = $totalUnits > 0 ? round($reservedUnits / $totalUnits * 100) : 0;
        $occupiedPctAll = $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100) : 0;
    @endphp

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-6 pb-10">

        {{-- Header --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Occupancy Monitoring</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Monitor the occupancy status of all your rental units in real-time.</p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                {{-- Property filter --}}
                <div class="relative">
                    <select onchange="window.location.href = '{{ route('landlord.occupancy.index') }}' + (this.value ? ('?property=' + this.value) : '')"
                        class="h-10 appearance-none rounded-xl border border-[#64748B]/25 bg-white pl-3.5 pr-9 text-[13px] font-semibold text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition cursor-pointer">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->property_id }}" {{ $selectedPropertyId === $property->property_id ? 'selected' : '' }}>
                                {{ $property->title }}
                            </option>
                        @endforeach
                    </select>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2"
                        class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>

                {{-- Export --}}
                <a href="{{ route('landlord.occupancy.export', ['property' => $selectedPropertyId]) }}"
                    class="h-10 px-4 inline-flex items-center gap-1.5 rounded-xl bg-[#156F8C] text-white text-[13px] font-semibold hover:brightness-95 transition-all duration-200">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export Report
                </a>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            {{-- Total --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Units</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF2F5] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ $totalUnits }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All rental units</p>
            </div>

            {{-- Available --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Available</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-emerald-600">{{ $availableUnits }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $availPctAll }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $availPctAll }}% of total</p>
            </div>

            {{-- Reserved --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reserved</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-amber-500">{{ $reservedUnits }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-amber-500" style="width: {{ $reservedPctAll }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $reservedPctAll }}% of total</p>
            </div>

            {{-- Occupied --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-red-500">{{ $occupiedUnits }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-red-500" style="width: {{ $occupiedPctAll }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $occupiedPctAll }}% of total</p>
            </div>

            {{-- Occupancy rate --}}
            <div class="col-span-2 lg:col-span-1 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupancy Rate</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#156F8C]">{{ $aggregateRate }}%</span>
                <p class="text-[11px] text-[#64748B] mt-1">{{ $occupiedUnits }} of {{ $totalUnits }} units occupied</p>
            </div>
        </div>

        {{-- Main grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">

            {{-- ── LEFT: Unit status overview + activities ─────────── --}}
            <div class="lg:col-span-3 space-y-4">

                {{-- Unit Status Overview --}}
                <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5"
                    x-data="{
                        groups: @js($unitStatusOverview),
                        styles: @js(collect($statusStyles)->map(fn ($s) => ['tile' => $s['tile'], 'text' => $s['text'], 'dot' => $s['dot']])),
                        q: '',
                        status: '',
                        open: {},
                        modal: null,
                        get filtering() { return this.q.trim() !== '' || this.status !== '' },
                        unitsFor(g) {
                            const q = this.q.trim().toLowerCase();
                            return g.units.filter(u =>
                                (!this.status || u.status === this.status) &&
                                (!q || u.label.toLowerCase().includes(q) || g.title.toLowerCase().includes(q))
                            );
                        },
                        groupVisible(g) { return this.filtering ? this.unitsFor(g).length > 0 : true },
                        get noResults() { return this.filtering && this.groups.every(g => this.unitsFor(g).length === 0) },
                        isOpen(g) { return this.filtering ? true : !!this.open[g.property_id] },
                        toggle(g) { this.open[g.property_id] = !this.isOpen(g) },
                        show: false,
                        openUnit(u, g) { this.modal = { ...u, property: g.title, units_url: g.units_url }; this.$nextTick(() => this.show = true) },
                        closeModal() { this.show = false; setTimeout(() => this.modal = null, 200) },
                        peso(v) { return v ? '₱' + Number(v).toLocaleString('en-PH') : null },
                    }"
                    x-on:keydown.escape.window="closeModal()">

                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="text-[14px] font-bold text-[#1F2937]">Unit Status Overview</h2>
                        <span class="text-[11px] text-[#64748B]">{{ $unitStatusOverview->count() }} {{ Str::plural('property', $unitStatusOverview->count()) }} &middot; {{ $totalUnits }} {{ Str::plural('unit', $totalUnits) }}</span>
                    </div>

                    @if($unitStatusOverview->isEmpty())
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center mb-3">
                                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                                </svg>
                            </div>
                            <p class="text-[13px] font-semibold text-[#1F2937]">No properties to show</p>
                            <p class="text-[12px] text-[#64748B] mt-1">Units will appear here once you add properties.</p>
                        </div>
                    @else
                        {{-- Search + status filter chips --}}
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 mb-3">
                            <div class="relative flex-1 min-w-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <input type="text" x-model="q" placeholder="Search property or unit..."
                                    class="h-9 w-full rounded-xl border border-[#64748B]/25 bg-white pl-9 pr-3 text-[12.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                                <button type="button" x-on:click="status = ''"
                                    :class="status === '' ? 'bg-[#1F2937] text-white border-[#1F2937]' : 'bg-white text-[#64748B] border-[#64748B]/25 hover:border-[#64748B]/40'"
                                    class="h-8 px-3 rounded-full border text-[11.5px] font-semibold transition-colors duration-150 cursor-pointer">All</button>
                                @foreach($statusStyles as $name => $s)
                                    <button type="button" x-on:click="status = status === '{{ $name }}' ? '' : '{{ $name }}'"
                                        :class="status === '{{ $name }}' ? 'bg-[#1F2937] text-white border-[#1F2937]' : 'bg-white text-[#64748B] border-[#64748B]/25 hover:border-[#64748B]/40'"
                                        class="h-8 px-3 rounded-full border text-[11.5px] font-semibold inline-flex items-center gap-1.5 transition-colors duration-150 cursor-pointer">
                                        <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>{{ $name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Scrollable property groups --}}
                        <div class="max-h-[440px] overflow-y-auto scrollbar-thin-light -mr-2 pr-2">
                            <template x-for="g in groups" :key="g.property_id">
                                <div x-show="groupVisible(g)" class="border-t border-[#64748B]/10 first:border-t-0 py-3">
                                    <button type="button" x-on:click="toggle(g)" class="w-full flex items-center gap-2.5 text-left group">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2.5"
                                            class="shrink-0 transition-transform duration-200" :class="isOpen(g) ? 'rotate-90' : ''">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2" class="shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                                        </svg>
                                        <span class="text-[13px] font-bold text-[#1F2937] group-hover:text-[#156F8C] transition-colors truncate" x-text="g.title"></span>

                                        {{-- Mini status counts (visible without expanding) --}}
                                        <span class="flex items-center gap-2 ml-auto shrink-0 text-[11px] font-semibold text-[#1F2937]">
                                            <span class="flex items-center gap-1" x-show="g.available > 0"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><span x-text="g.available"></span></span>
                                            <span class="flex items-center gap-1" x-show="g.reserved > 0"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span x-text="g.reserved"></span></span>
                                            <span class="flex items-center gap-1" x-show="g.occupied > 0"><span class="w-2 h-2 rounded-full bg-red-500"></span><span x-text="g.occupied"></span></span>
                                            <span class="flex items-center gap-1" x-show="g.maintenance > 0"><span class="w-2 h-2 rounded-full bg-slate-400"></span><span x-text="g.maintenance"></span></span>
                                            <span class="text-[#64748B] font-normal" x-text="g.total + (g.total === 1 ? ' unit' : ' units')"></span>
                                        </span>
                                    </button>

                                    <div x-show="isOpen(g)" x-cloak class="mt-3">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 pl-6">
                                            <template x-for="u in unitsFor(g)" :key="u.unit_id">
                                                <button type="button" x-on:click="openUnit(u, g)"
                                                    class="rounded-xl border px-3 py-2.5 text-center cursor-pointer hover:brightness-95 transition-all duration-150"
                                                    :class="styles[u.status].tile">
                                                    <p class="text-[15px] font-extrabold text-[#1F2937] leading-tight truncate" x-text="u.label"></p>
                                                    <p class="text-[11px] font-semibold mt-0.5" :class="styles[u.status].text" x-text="u.status"></p>
                                                    <p class="text-[11px] text-[#64748B] mt-0.5 truncate" x-show="u.tenant" x-text="u.tenant"></p>
                                                </button>
                                            </template>
                                        </div>
                                        <p class="text-[12px] text-[#64748B] pl-6" x-show="g.units.length === 0">No units yet.</p>
                                    </div>
                                </div>
                            </template>

                            {{-- No filter matches --}}
                            <div x-show="noResults" x-cloak class="py-10 text-center">
                                <p class="text-[13px] font-semibold text-[#1F2937]">No units match</p>
                                <p class="text-[12px] text-[#64748B] mt-1">Try a different search or status filter.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Unit detail modal (styled like the unit form Live Preview card).
                         Teleported to <body>: backdrop-blur on the card creates a containing
                         block that would trap position:fixed inside the card. --}}
                    <template x-teleport="body">
                        <div x-show="modal" x-cloak>
                        <template x-if="modal">
                        <div class="fixed inset-0 z-30 flex items-center justify-center p-4">
                            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm motion-reduce:transition-none" x-on:click="closeModal()"
                                x-show="show"
                                x-transition:enter="transition-opacity ease-out duration-250"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition-opacity ease-in duration-200"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"></div>
                            <div class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden motion-reduce:transition-none"
                                x-show="show"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 translate-y-4 scale-95">

                                {{-- Image area --}}
                                <div class="relative aspect-[4/3] bg-[#EEF8F8] border-b border-[#E2E8F0]/70">
                                    <template x-if="modal.photo">
                                        <img :src="modal.photo" :alt="modal.label" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!modal.photo">
                                        <div class="w-full h-full flex flex-col items-center justify-center text-[#64748B]">
                                            <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                            <p class="text-[11px] mt-1.5">No photos on this unit</p>
                                        </div>
                                    </template>
                                    <button type="button" x-on:click="closeModal()" aria-label="Close"
                                        class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 hover:brightness-95 flex items-center justify-center text-[#1F2937] shadow-sm transition-all cursor-pointer">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Body (mirrors Live Preview) --}}
                                <div class="p-5 space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-[15px] font-bold text-[#1F2937] truncate" x-text="modal.label"></p>
                                            <p class="text-[12px] text-[#64748B] mt-0.5 truncate" x-text="modal.property"></p>
                                            <p class="text-[12px] text-[#64748B] mt-0.5" x-show="modal.type || modal.floor"
                                                x-text="[modal.type, modal.floor].filter(Boolean).join(' · ')"></p>
                                        </div>
                                        <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                            :class="styles[modal.status].tile + ' ' + styles[modal.status].text">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="styles[modal.status].dot"></span>
                                            <span x-text="modal.status"></span>
                                        </span>
                                    </div>

                                    {{-- Rent --}}
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-[20px] font-bold text-[#156F8C]" x-text="peso(modal.rent) || '₱—'"></span>
                                        <span class="text-[12px] text-[#64748B]">/ month</span>
                                    </div>

                                    {{-- Tenant --}}
                                    <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2" x-show="modal.tenant">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Tenant</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5" x-text="modal.tenant"></p>
                                    </div>

                                    {{-- Meta tiles --}}
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                            <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Capacity</p>
                                            <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5"
                                                x-text="modal.capacity ? modal.capacity + (modal.capacity == 1 ? ' person' : ' persons') : '—'"></p>
                                        </div>
                                        <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                            <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Deposit</p>
                                            <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5" x-text="peso(modal.deposit) || '—'"></p>
                                        </div>
                                    </div>

                                    {{-- Amenities --}}
                                    <div x-show="modal.amenities && modal.amenities.length" class="pt-1">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B] mb-1.5">Amenities</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="a in modal.amenities" :key="a">
                                                <span class="inline-flex items-center rounded-full bg-[#EEF8F8] border border-[#2AA7A1]/20 px-2 py-0.5 text-[11px] text-[#1F2937]" x-text="a"></span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex items-center gap-2.5 pt-1">
                                        <a :href="modal.units_url"
                                            class="flex-1 h-10 inline-flex items-center justify-center rounded-full border border-[#64748B]/30 text-[#1F2937] text-[12.5px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                                            View all units
                                        </a>
                                        <a :href="modal.edit_url"
                                            class="flex-1 h-10 inline-flex items-center justify-center rounded-full bg-[#2AA7A1] text-white text-[12.5px] font-semibold hover:brightness-95 transition-all duration-200">
                                            Edit unit
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </template>
                        </div>
                    </template>
                </div>

                {{-- Recent Activities --}}
                <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5">
                    <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Recent Activities</h2>
                    @if($recentActivities->isEmpty())
                        <p class="text-[12px] text-[#64748B] py-4 text-center">No occupancy changes recorded yet.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($recentActivities as $activity)
                                @php
                                    $st = $statusStyles[$activity->to_status] ?? $statusStyles['Available'];
                                    $person = $activity->tenant?->first_name
                                        ? trim($activity->tenant->first_name . ' ' . $activity->tenant->last_name)
                                        : ($activity->actor?->first_name ? trim($activity->actor->first_name . ' ' . $activity->actor->last_name) : null);
                                @endphp
                                <div class="flex items-start gap-3">
                                    <span class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 mt-0.5 {{ $st['tile'] }}">
                                        <span class="w-2 h-2 rounded-full {{ $st['dot'] }}"></span>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[12.5px] text-[#1F2937] leading-snug">
                                            <span class="font-semibold">{{ $activity->unit?->unit_label ?? 'A unit' }}</span>
                                            in {{ $activity->property?->title ?? 'a property' }} {{ $st['verb'] }}
                                        </p>
                                        <p class="text-[11px] text-[#64748B] mt-0.5">
                                            @if($person)by {{ $person }} &middot; @endif{{ $activity->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── RIGHT: donut + trend ────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Occupancy Summary donut --}}
                <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5">
                    <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Occupancy Summary</h2>
                    <div class="relative h-44">
                        <canvas id="occupancyStatusChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <p class="text-[22px] font-bold text-[#156F8C] leading-none">{{ $aggregateRate }}%</p>
                            <p class="text-[10px] text-[#64748B] mt-1">Occupied</p>
                        </div>
                    </div>
                    <div class="space-y-2.5 text-[12.5px] mt-5">
                        @php
                            $legend = [
                                ['Occupied', $occupiedUnits, 'bg-red-500'],
                                ['Available', $availableUnits, 'bg-emerald-500'],
                                ['Reserved', $reservedUnits, 'bg-amber-500'],
                                ['Maintenance', $maintenanceUnits, 'bg-slate-400'],
                            ];
                        @endphp
                        @foreach($legend as [$label, $count, $dot])
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-[#64748B]"><span class="w-2.5 h-2.5 rounded-full {{ $dot }}"></span>{{ $label }}</span>
                                <span class="font-bold text-[#1F2937]">{{ $count }} <span class="font-normal text-[#64748B]">({{ $totalUnits > 0 ? round($count / $totalUnits * 100) : 0 }}%)</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Occupancy Trend --}}
                <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5">
                    <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Occupancy Trend (Last 30 Days)</h2>
                    @if(count($trend['data']) < 2)
                        <div class="flex flex-col items-center justify-center h-40 text-center">
                            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="1.5" class="mb-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                            </svg>
                            <p class="text-[12.5px] font-semibold text-[#1F2937]">Trend is building</p>
                            <p class="text-[11px] text-[#64748B] mt-1 max-w-[220px]">A daily snapshot is recorded automatically. The trend line appears once there are at least two days of data.</p>
                        </div>
                    @else
                        <div class="relative h-40">
                            <canvas id="occupancyTrendChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <p class="flex items-center justify-end gap-1.5 text-[11px] text-[#64748B] mt-4">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Data reflects your latest unit statuses
        </p>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusCtx = document.getElementById('occupancyStatusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Occupied', 'Available', 'Reserved', 'Maintenance'],
                        datasets: [{
                            data: [{{ $occupiedUnits }}, {{ $availableUnits }}, {{ $reservedUnits }}, {{ $maintenanceUnits }}],
                            backgroundColor: ['#EF4444', '#10b981', '#f59e0b', '#94a3b8'],
                            borderWidth: 0,
                            borderRadius: 6,
                            hoverOffset: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '74%',
                        plugins: { legend: { display: false } },
                    },
                });
            }

            const trendCtx = document.getElementById('occupancyTrendChart');
            if (trendCtx) {
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: @js($trend['labels']),
                        datasets: [{
                            data: @js($trend['data']),
                            borderColor: '#156F8C',
                            backgroundColor: 'rgba(42, 167, 161, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointBackgroundColor: '#156F8C',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%', font: { size: 10 } }, grid: { color: '#E2E8F0' } },
                            x: { ticks: { font: { size: 10 }, maxTicksLimit: 8 }, grid: { display: false } },
                        },
                    },
                });
            }
        });
    </script>
@endpush
