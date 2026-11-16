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
    @endphp

    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-14 min-h-[calc(100vh-72px)]">

        <x-page-header title="My Rentals" subtitle="Every place you rent or have rented. Open one for its rent, payments and rental.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V20a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V9.5" />
                </svg>
            </x-slot:icon>
        </x-page-header>

        @if($current->isEmpty() && $past->isEmpty())
            <x-empty-state title="No rentals yet"
                message="Once your landlord confirms your move-in, your home shows up here with its rent and payments."
                :href="route('properties.index')" cta="Browse rentals">
                <x-slot:icon>
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $placeholder }}" /></svg>
                </x-slot:icon>
            </x-empty-state>
        @endif

        {{-- Current rentals: one card each, photo first so a tenant with several tells them apart at a glance. --}}
        @if($current->isNotEmpty())
            <section aria-labelledby="current-rentals-title">
                <h2 id="current-rentals-title" class="mb-3 text-[13px] font-bold uppercase tracking-wider text-[#5B6A8E]">Current rentals</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($current as $row)
                        @php
                            $r = $row['reservation'];
                            $unit = $r->unit;
                            $property = $r->property;
                            $thumb = $unit?->media->firstWhere('media_type', 'Image');
                            $status = $statusLine($row['summary'], $r);
                        @endphp
                        <a href="{{ route('tenancy.show', $r) }}" data-rental-card
                            class="group flex flex-col overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] transition-shadow duration-200 hover:shadow-[0_8px_24px_rgba(6,13,38,0.10)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50">
                            <div class="aspect-[16/9] w-full bg-[#F7F8FC] overflow-hidden">
                                @if($thumb)
                                    <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="{{ $unit->unit_label }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-[#94A3B8]">
                                        <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $placeholder }}" /></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col gap-3 p-4">
                                <div class="min-w-0">
                                    <p class="truncate text-[16px] font-semibold text-[#060D26]">{{ $unit?->unit_label ?? 'Your rental' }}</p>
                                    <p class="truncate text-[13px] text-[#5B6A8E]">{{ $property?->title }}@if($property?->barangay) &middot; {{ $property->barangay }}@endif</p>
                                    <p class="truncate text-[12px] text-[#5B6A8E]">Landlord: {{ $property?->landlord?->name }}</p>
                                </div>
                                <div class="mt-auto flex items-center justify-between gap-2">
                                    <span class="inline-flex h-7 items-center rounded-full border px-2.5 text-[12px] font-bold {{ $status['tone'] }}">{{ $status['text'] }}</span>
                                    <span class="shrink-0 text-[13px] font-semibold text-[#060D26]">{{ $peso($row['summary']['monthlyRent']) }}<span class="font-medium text-[#5B6A8E]"> / month</span></span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Past rentals: a quiet list, still one tap from the receipts and the lease. --}}
        @if($past->isNotEmpty())
            <section aria-labelledby="past-rentals-title" class="{{ $current->isNotEmpty() ? 'mt-10' : '' }}">
                <h2 id="past-rentals-title" class="mb-3 text-[13px] font-bold uppercase tracking-wider text-[#5B6A8E]">Past rentals</h2>
                <ul class="divide-y divide-[#E2E4EC] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white">
                    @foreach($past as $r)
                        @php $thumb = $r->unit?->media->firstWhere('media_type', 'Image'); @endphp
                        <li>
                            <a href="{{ route('tenancy.show', $r) }}" data-rental-card
                                class="flex items-center gap-3 px-4 py-3 transition-colors duration-200 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC]">
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-[#F7F8FC] ring-1 ring-[#5B6A8E]/10">
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
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
