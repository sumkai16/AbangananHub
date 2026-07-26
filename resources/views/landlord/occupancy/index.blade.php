@extends('layouts.landlord')

@section('content')
    @php
        $statusStyles = [
            'Available'   => ['tile' => 'border-[#22C55E]/25 bg-[#22C55E]/[0.07]', 'text' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]', 'chip' => 'bg-[#22C55E]/[0.12] text-[#15803D] border-[#22C55E]/45', 'verb' => 'was made available'],
            'Reserved'    => ['tile' => 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10]', 'text' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]', 'chip' => 'bg-[#FBBF24]/[0.18] text-[#B45309] border-[#FBBF24]/60', 'verb' => 'was reserved'],
            'Occupied'    => ['tile' => 'border-[#EF4444]/25 bg-[#EF4444]/[0.07]', 'text' => 'text-[#DC2626]', 'dot' => 'bg-[#EF4444]', 'chip' => 'bg-[#EF4444]/[0.12] text-[#DC2626] border-[#EF4444]/45', 'verb' => 'was occupied'],
            'Maintenance' => ['tile' => 'border-[#E2E8F0] bg-[#F7FCFC]',           'text' => 'text-[#64748B]', 'dot' => 'bg-[#94A3B8]', 'chip' => 'bg-[#94A3B8]/[0.15] text-[#64748B] border-[#94A3B8]/50', 'verb' => 'is now under maintenance'],
        ];
        $pct = fn ($n) => $totalUnits > 0 ? round($n / $totalUnits * 100) : 0;
        $availPctAll = $pct($availableUnits);
        $reservedPctAll = $pct($reservedUnits);
        $occupiedPctAll = $pct($occupiedUnits);
        $maintenancePctAll = $pct($maintenanceUnits);
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <x-page-header title="Occupancy Monitoring" subtitle="Monitor the occupancy status of all your rental units in real-time.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                {{-- Property filter --}}
                <div class="relative">
                    @php
                        $occupancyPropertyOptions = collect(['' => 'All Properties'])
                            ->merge($properties->pluck('title', 'property_id'))
                            ->all();
                    @endphp
                    <x-styled-select :options="$occupancyPropertyOptions"
                        :selected="$selectedPropertyId !== null ? (string) $selectedPropertyId : ''"
                        onSelect="window.location.href = '{{ route('landlord.occupancy.index') }}' + (val ? ('?property=' + val) : '')"
                        class="h-10 rounded-xl border border-[#64748B]/25 bg-white pl-3.5 pr-3.5 text-[13px] font-semibold text-[#1F2937]" />
                </div>

                {{-- Export --}}
                <a href="{{ route('landlord.occupancy.export', ['property' => $selectedPropertyId]) }}"
                    class="h-10 px-4 inline-flex items-center gap-1.5 rounded-xl bg-[#156F8C] text-white text-[13px] font-semibold hover:brightness-95 transition-all duration-200">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export Report
                </a>
            </x-slot:actions>
        </x-page-header>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            <x-stat-card label="Total Units" :value="$totalUnits" sub="All rental units">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Available" :value="$availableUnits" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)"
                :percent="$availPctAll" bar-color="#22C55E" :sub="$availPctAll.'% of total'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Reserved" :value="$reservedUnits" value-color="#B45309" icon-bg="rgba(251,191,36,0.10)"
                :percent="$reservedPctAll" bar-color="#FBBF24" :sub="$reservedPctAll.'% of total'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Occupied" :value="$occupiedUnits" value-color="#DC2626" icon-bg="rgba(239,68,68,0.07)"
                :percent="$occupiedPctAll" bar-color="#EF4444" :sub="$occupiedPctAll.'% of total'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Maintenance" :value="$maintenanceUnits" value-color="#64748B" icon-bg="rgba(148,163,184,0.15)"
                :percent="$maintenancePctAll" bar-color="#94A3B8" :sub="$maintenancePctAll.'% of total'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card class="col-span-2 sm:col-span-3 lg:col-span-1" label="Occupancy Rate" :value="$aggregateRate.'%'" value-color="#156F8C"
                :percent="$aggregateRate" bar-color="#2AA7A1" :sub="$occupiedUnits.' of '.$totalUnits.' units occupied'" />
        </div>

        {{-- Main grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">

            {{-- ── LEFT: Unit status overview + activities ─────────── --}}
            <div class="lg:col-span-3 space-y-4">

                {{-- Unit Status Overview --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5"
                    x-data="{
                        groups: @js($unitStatusOverview),
                        styles: @js(collect($statusStyles)->map(fn ($s) => ['tile' => $s['tile'], 'text' => $s['text'], 'dot' => $s['dot']])),
                        segs: [
                            { key: 'available',   color: '#22C55E', label: 'Available' },
                            { key: 'reserved',    color: '#FBBF24', label: 'Reserved' },
                            { key: 'occupied',    color: '#EF4444', label: 'Occupied' },
                            { key: 'maintenance', color: '#94A3B8', label: 'Maintenance' },
                        ],
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
                                <input type="text" x-model="q" placeholder="Search property or unit..." aria-label="Search property or unit"
                                    class="h-9 w-full rounded-xl border border-[#64748B]/25 bg-white pl-9 pr-3 text-[12.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                                <button type="button" x-on:click="status = ''"
                                    :class="status === '' ? 'bg-[#1F2937] text-white border-[#1F2937]' : 'bg-white text-[#64748B] border-[#64748B]/25 hover:border-[#64748B]/40'"
                                    class="h-8 px-3 rounded-full border text-[11.5px] font-semibold transition-colors duration-150 cursor-pointer">All</button>
                                @foreach($statusStyles as $name => $s)
                                    {{-- Active chip tints to its own status: colour is reserved for
                                         status (DESIGN §3), so four chips all going charcoal wastes it. --}}
                                    <button type="button" x-on:click="status = status === '{{ $name }}' ? '' : '{{ $name }}'"
                                        :class="status === '{{ $name }}' ? '{{ $s['chip'] }}' : 'bg-white text-[#64748B] border-[#64748B]/25 hover:border-[#64748B]/40'"
                                        class="h-8 px-3 rounded-full border text-[11.5px] font-semibold inline-flex items-center gap-1.5 transition-colors duration-150 cursor-pointer">
                                        <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>{{ $name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Scrollable property groups --}}
                        <div class="max-h-[560px] overflow-y-auto scrollbar-thin-light -mr-2 pr-2">
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

                                        {{-- Mini mix bar + counts (visible without expanding).
                                             Per-property only: the stat rail is portfolio-wide and
                                             says nothing about how any one property is doing. --}}
                                        <span class="flex items-center gap-2.5 ml-auto shrink-0 text-[11px] font-semibold text-[#1F2937]">
                                            <span class="hidden sm:flex h-2 w-24 rounded-full overflow-hidden bg-[#E2E8F0] gap-px" x-show="g.total > 0">
                                                <template x-for="s in segs" :key="s.key">
                                                    <span class="h-full" x-show="g[s.key] > 0"
                                                        :style="`width: ${g[s.key] / g.total * 100}%; background-color: ${s.color}`"
                                                        :title="`${s.label}: ${g[s.key]}`"></span>
                                                </template>
                                            </span>
                                            <template x-for="s in segs" :key="s.key">
                                                <span class="flex items-center gap-1" x-show="g[s.key] > 0">
                                                    <span class="w-2 h-2 rounded-full" :style="`background-color: ${s.color}`"></span><span x-text="g[s.key]"></span>
                                                </span>
                                            </template>
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
                         Teleported to <body> so the fixed-position overlay escapes the card's
                         stacking/overflow context and covers the full viewport. --}}
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

            </div>

            {{-- ── RIGHT: vacancy watch + activity ─────────────────── --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Vacancy Watch — replaced the 30-day trend chart. A landlord
                     can't act on what occupancy was three weeks ago; they can act
                     on which unit has sat empty longest and what it's costing. --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                    <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Vacancy Watch</h2>

                    @if($vacancy['count'] === 0)
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="w-11 h-11 rounded-xl bg-[#22C55E]/[0.10] flex items-center justify-center mb-3">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#15803D" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <p class="text-[13px] font-semibold text-[#1F2937]">No vacant units</p>
                            <p class="text-[11.5px] text-[#64748B] mt-1 max-w-[230px]">Every unit is reserved, occupied, or under maintenance.</p>
                        </div>
                    @else
                        {{-- The headline: rent the portfolio is not earning. This is
                             the one figure that makes an empty unit feel like a cost
                             rather than a status. --}}
                        <div class="rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] px-4 py-3.5 mb-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rent not being earned</p>
                            <p class="text-[22px] font-extrabold text-[#B45309] mt-1 leading-none">
                                ₱{{ number_format($vacancy['idle_rent']) }}<span class="text-[12px] font-semibold text-[#64748B]"> / month</span>
                            </p>
                            <p class="text-[11.5px] text-[#64748B] mt-1.5">
                                Across {{ $vacancy['count'] }} vacant {{ Str::plural('unit', $vacancy['count']) }}
                            </p>
                        </div>

                        <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-2.5">Empty longest</p>
                        <div class="space-y-2">
                            @foreach($vacancy['units'] as $unit)
                                @php
                                    // Same 60-day threshold the admin unit catalogue uses for
                                    // "stale", so one number defines a long vacancy app-wide.
                                    $days = $unit['days'];
                                    $tone = match (true) {
                                        $days >= 60 => ['bg-[#EF4444]/[0.07]', 'text-[#DC2626]'],
                                        $days >= 30 => ['bg-[#FBBF24]/[0.12]', 'text-[#B45309]'],
                                        default     => ['bg-[#F7FCFC]',        'text-[#64748B]'],
                                    };
                                @endphp
                                <a href="{{ $unit['edit_url'] }}"
                                    class="flex items-center gap-3 rounded-xl border border-[#E2E8F0] px-3 py-2.5 hover:bg-[#F7FCFC] transition-colors duration-150">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[12.5px] font-semibold text-[#1F2937] truncate">{{ $unit['label'] }}</p>
                                        <p class="text-[11px] text-[#64748B] truncate">{{ $unit['property'] }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone[0] }} {{ $tone[1] }}">
                                            {{ $days }}{{ $days === 1 ? ' day' : ' days' }}
                                        </span>
                                        <p class="text-[11px] text-[#64748B] mt-1">₱{{ number_format($unit['rent']) }}/mo</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        <p class="text-[11px] text-[#64748B] mt-3">
                            Counted from when the unit was last vacated, or from when it was listed if it has never been let.
                        </p>
                    @endif
                </div>

                {{-- Recent Activity — a timeline: the connecting rule ties the
                     status dots into one sequence rather than four loose rows. --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                    <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Recent Activity</h2>
                    @if($recentActivities->isEmpty())
                        <p class="text-[12px] text-[#64748B] py-4 text-center">No occupancy changes recorded yet.</p>
                    @else
                        <div class="relative">
                            {{-- Timeline rule, stopped short of the last dot's centre. --}}
                            <span class="absolute left-[13px] top-3 bottom-6 w-px bg-[#E2E8F0]" aria-hidden="true"></span>
                            <div class="space-y-3.5">
                                @foreach($recentActivities as $activity)
                                    @php
                                        $st = $statusStyles[$activity->to_status] ?? $statusStyles['Available'];
                                        $person = $activity->tenant?->first_name
                                            ? trim($activity->tenant->first_name . ' ' . $activity->tenant->last_name)
                                            : ($activity->actor?->first_name ? trim($activity->actor->first_name . ' ' . $activity->actor->last_name) : null);
                                    @endphp
                                    <div class="relative flex items-start gap-3">
                                        <span class="w-[27px] h-[27px] rounded-full border flex items-center justify-center shrink-0 bg-white z-10 {{ $st['tile'] }}">
                                            <span class="w-2 h-2 rounded-full {{ $st['dot'] }}"></span>
                                        </span>
                                        <div class="flex-1 min-w-0 pt-0.5">
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
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <p class="flex items-center justify-end gap-1.5 text-[11px] text-[#64748B] mt-4">
            <span class="w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span> Data reflects your latest unit statuses
        </p>

    </div>
@endsection

{{-- No @push('scripts') here any more. The page's only chart was the occupancy
     trend line; with that gone the Chart.js CDN tag went with it, so this view
     no longer pulls a ~200KB third-party bundle it has nothing to draw with. --}}
