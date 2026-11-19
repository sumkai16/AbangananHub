@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    @php
        // 5,000 reads "5,000", 5,000.50 keeps its cents.
        $peso = fn (float $amount) => '₱'.number_format($amount, fmod($amount, 1) ? 2 : 0);

        // One line per current rental, from the ledger summary: what is owed, what is next, or that nothing is.
        $statusLine = function (array $summary, \App\Models\Reservation $r) use ($peso) {
            if ($summary['overdueCount'] > 0) {
                return ['text' => $peso($summary['overdueAmount']).' overdue', 'tone' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25'];
            }

            if ($summary['nextDue']) {
                return ['text' => $peso(max(0, (float) $summary['nextDue']['balance'])).' due '.$summary['nextDue']['due_on']->format('M j'), 'tone' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35'];
            }

            if ($summary['prepaidThrough']) {
                return ['text' => 'Paid through '.$summary['prepaidThrough']->format('M Y'), 'tone' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25'];
            }

            // The ledger lists the start month as a period even before it begins, so a lease that has not started is
            // told apart by its date, not by the period count.
            return $r->target_move_in_date?->isFuture()
                ? ['text' => 'Nothing due yet', 'tone' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]']
                : ['text' => 'All paid up', 'tone' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25'];
        };

        $placeholder = 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21';
        // The filter bar works on what is already on the page (a tenant has a handful of rentals, no paging), so each
        // rental gets one entry here: what the search reads, which property it is, and which status filter it falls
        // under. The pill's own wording and these keys come from the same ledger summary, so the two always agree.
        $statusKey = fn (array $summary) => $summary['overdueCount'] > 0 ? 'overdue' : ($summary['nextDue'] ? 'due' : 'paid');
        $hay = fn (\App\Models\Reservation $r) => mb_strtolower(trim(implode(' ', array_filter([
            $r->unit?->unit_label, $r->property?->title, $r->property?->barangay, $r->property?->landlord?->name,
        ]))));
        $items = [];
        $propertyOptions = ['' => 'All Properties'];
        foreach ($current as $row) {
            $r = $row['reservation'];
            $items[$r->reservation_id] = ['kind' => 'current', 'prop' => 'p'.$r->property_id, 'status' => $statusKey($row['summary']), 'hay' => $hay($r)];
            $propertyOptions['p'.$r->property_id] = $r->property?->title ?? 'Property';
        }
        foreach ($past as $r) {
            $items[$r->reservation_id] = ['kind' => 'past', 'prop' => 'p'.$r->property_id, 'status' => 'past', 'hay' => $hay($r)];
            $propertyOptions['p'.$r->property_id] = $r->property?->title ?? 'Property';
        }
        // "p" keeps the ids as text: a numeric key would jump ahead of 'All Properties' when the list is read back.
        $statusOptions = ['' => 'All Status', 'overdue' => 'Overdue', 'due' => 'Due soon', 'paid' => 'Paid up', 'past' => 'Past'];
        $hasRentals = $current->isNotEmpty() || $past->isNotEmpty();
        $filterSelect = 'h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26]';

    @endphp

    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-14 min-h-[calc(100vh-72px)]"
        x-data="{
            items: @js($items),
            q: '', property: '', status: '',
            view: 'grid',
            init() {
                try { const v = localStorage.getItem('rentalsView'); if (v === 'grid' || v === 'list') this.view = v; } catch (e) {}
            },
            setView(v) {
                this.view = v;
                try { localStorage.setItem('rentalsView', v); } catch (e) {}
            },
            ok(id) {
                const i = this.items[id];
                if (!i) return true;
                const q = this.q.trim().toLowerCase();
                return (!q || i.hay.includes(q)) && (!this.property || i.prop === this.property) && (!this.status || i.status === this.status);
            },
            count(kind) { return Object.keys(this.items).filter(id => this.items[id].kind === kind && this.ok(id)).length; },
            get shown() { return this.count('current') + this.count('past'); },
            get filtered() { return this.q.trim() !== '' || this.property !== '' || this.status !== ''; },
            clear() { this.q = ''; this.property = ''; this.status = ''; },
        }">

        <x-page-header title="My Rentals" subtitle="Every place you rent or have rented. Open one for its rent, payments and rental.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V20a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V9.5" />
                </svg>
            </x-slot:icon>
        </x-page-header>

        @unless($hasRentals)
            <x-empty-state title="No rentals yet"
                message="Once your landlord confirms your move-in, your home shows up here with its rent and payments."
                :href="route('properties.index')" cta="Browse rentals">
                <x-slot:icon>
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $placeholder }}" /></svg>
                </x-slot:icon>
            </x-empty-state>
        @else
            {{-- Filters: the same bar as Units. Works on the page as loaded, so it answers as you type. --}}
            <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative min-w-[200px] flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input type="search" x-model="q" placeholder="Search rentals by unit, property or landlord..." aria-label="Search rentals" autocomplete="off"
                        class="h-11 w-full rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] pl-10 pr-4 text-[16px] text-[#060D26] placeholder-[#94A3B8] transition-all duration-200 focus:border-[#FF8A66] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 sm:h-10 sm:text-[13.5px]">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <x-styled-select x-model="property" :options="$propertyOptions" selected="" class="{{ $filterSelect }} max-w-[180px]" />
                    <x-styled-select x-model="status" :options="$statusOptions" selected="" class="{{ $filterSelect }}" />

                    <button type="button" x-show="filtered" x-cloak @click="clear()"
                        class="inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-xl border border-[#5B6A8E]/25 px-4 text-[13.5px] text-[#5B6A8E] transition-colors duration-200 hover:bg-[#ECEEF6] hover:text-[#060D26]">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </button>

                    <div class="ml-auto flex h-11 items-center gap-0.5 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] p-1" role="group" aria-label="Layout">
                        <button type="button" @click="setView('grid')" aria-label="Card view" :aria-pressed="view === 'grid'"
                            :class="view === 'grid' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg transition-all duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" /></svg>
                        </button>
                        <button type="button" @click="setView('list')" aria-label="List view" :aria-pressed="view === 'list'"
                            :class="view === 'list' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg transition-all duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Nothing matches the filters --}}
            <div x-show="shown === 0" x-cloak class="rounded-2xl border border-[#E2E4EC] bg-white px-6 py-12 text-center">
                <p class="text-[15px] font-semibold text-[#060D26]">No rentals match</p>
                <p class="mt-1 text-[13px] text-[#5B6A8E]">Try a different search, property or status.</p>
                <button type="button" @click="clear()"
                    class="mt-4 inline-flex h-11 cursor-pointer items-center rounded-xl border border-[#060D26]/35 bg-white px-5 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#ECEEF6]">Clear filters</button>
            </div>
        @endunless

        {{-- Current rentals: a small card each (photo first, so a tenant with several tells them apart), or a row in list view. --}}
        @if($current->isNotEmpty())
            <section aria-labelledby="current-rentals-title" x-show="count('current') > 0">
                <h2 id="current-rentals-title" class="mb-3 text-[13px] font-bold uppercase tracking-wider text-[#5B6A8E]">Current rentals</h2>
                <div :class="view === 'grid' ? 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' : 'flex flex-col divide-y divide-[#E2E4EC] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white'">
                    @foreach($current as $row)
                        @php
                            $r = $row['reservation'];
                            $unit = $r->unit;
                            $property = $r->property;
                            $thumb = $unit?->media->firstWhere('media_type', 'Image');
                            $status = $statusLine($row['summary'], $r);
                        @endphp
                        <a href="{{ route('tenancy.show', $r) }}" data-rental-card x-show="ok({{ $r->reservation_id }})"
                            :class="view === 'grid'
                                ? 'flex-col overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] hover:shadow-[0_8px_24px_rgba(6,13,38,0.10)]'
                                : 'items-center gap-3 px-3 py-2.5 hover:bg-[#F7F8FC] sm:px-4'"
                            class="group flex transition-shadow duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50">
                            <div :class="view === 'grid' ? 'aspect-[3/2] w-full' : 'h-12 w-12 shrink-0 rounded-lg ring-1 ring-[#5B6A8E]/10 sm:h-14 sm:w-14'" class="overflow-hidden bg-[#F7F8FC]">
                                @if($thumb)
                                    <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="{{ $unit->unit_label }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-[#94A3B8]">
                                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $placeholder }}" /></svg>
                                    </div>
                                @endif
                            </div>
                            <div :class="view === 'grid' ? 'flex flex-1 flex-col gap-2 p-3' : 'flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:gap-4'">
                                <div class="min-w-0 sm:flex-1">
                                    <p class="truncate text-[14px] font-semibold text-[#060D26]">{{ $unit?->unit_label ?? 'Your rental' }}</p>
                                    <p class="truncate text-[12px] text-[#5B6A8E]">{{ $property?->title }}@if($property?->barangay) &middot; {{ $property->barangay }}@endif</p>
                                    <p :class="view === 'list' ? 'hidden sm:block' : ''" class="truncate text-[12px] text-[#5B6A8E]">Landlord: {{ $property?->landlord?->name }}</p>
                                </div>
                                <div :class="view === 'grid' ? 'mt-auto flex items-center justify-between gap-2' : 'flex shrink-0 items-center justify-between gap-3 sm:justify-end'">
                                    <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[11.5px] font-bold {{ $status['tone'] }}">{{ $status['text'] }}</span>
                                    <span class="shrink-0 text-[12.5px] font-semibold text-[#060D26]">{{ $peso($row['summary']['monthlyRent']) }}<span class="font-medium text-[#5B6A8E]"> / month</span></span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Past rentals: always a quiet list, still one tap from the receipts and the lease. --}}
        @if($past->isNotEmpty())
            <section aria-labelledby="past-rentals-title" x-show="count('past') > 0" class="{{ $current->isNotEmpty() ? 'mt-8' : '' }}">
                <h2 id="past-rentals-title" class="mb-3 text-[13px] font-bold uppercase tracking-wider text-[#5B6A8E]">Past rentals</h2>
                <div class="flex flex-col divide-y divide-[#E2E4EC] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white">
                    @foreach($past as $r)
                        @php $thumb = $r->unit?->media->firstWhere('media_type', 'Image'); @endphp
                        <a href="{{ route('tenancy.show', $r) }}" data-rental-card x-show="ok({{ $r->reservation_id }})"
                            class="flex items-center gap-3 px-3 py-2.5 transition-colors duration-200 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC] sm:px-4">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-[#F7F8FC] ring-1 ring-[#5B6A8E]/10 sm:h-14 sm:w-14">
                                @if($thumb)
                                    <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="{{ $r->unit->unit_label }}" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px] font-semibold text-[#060D26]">{{ $r->unit?->unit_label ?? 'Past rental' }}</p>
                                <p class="truncate text-[12px] text-[#5B6A8E]">{{ $r->property?->title }}</p>
                            </div>
                            <span class="shrink-0 text-right text-[12px] text-[#5B6A8E]">{{ $r->target_move_out_date ? 'Moved out '.$r->target_move_out_date->format('M j, Y') : 'Ended' }}</span>
                            <svg class="h-4 w-4 shrink-0 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
