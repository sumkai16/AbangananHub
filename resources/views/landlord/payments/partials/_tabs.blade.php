{{-- Payments page tabs: Rent (what tenants owe you) and Payouts (what AbangananHub owes you). Two URLs,
     one page to the landlord; the sidebar's Payments item is lit on both. Payouts only exists while
     showsPayoutTools() is true, so the bar is left out entirely when there is only one tab.
     $active: 'rent' | 'payouts'. $awaitingPayout: float|null, shown on the Payouts tab when above zero. --}}
<nav class="-mx-4 sm:mx-0 mb-4 sm:mb-5 border-b border-[#E2E4EC] px-4 sm:px-0" aria-label="Payments sections">
    <div class="flex gap-1 overflow-x-auto overflow-y-hidden">
        @foreach ([
            'rent' => ['label' => 'Rent', 'href' => route('landlord.payments.index')],
            'payouts' => ['label' => 'Payouts', 'href' => route('landlord.payouts.index')],
        ] as $key => $tab)
            <a href="{{ $tab['href'] }}" @if ($active === $key) aria-current="page" @endif
                class="inline-flex min-h-11 shrink-0 items-center gap-2 px-4 -mb-px border-b-2 text-[14px] sm:text-[13.5px] font-semibold whitespace-nowrap transition-colors duration-200
                    focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40 focus-visible:rounded-t-lg
                    {{ $active === $key ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]' }}">
                {{ $tab['label'] }}
                @if ($key === 'payouts' && $awaitingPayout > 0)
                    {{-- Money waiting to reach the landlord's GCash, so Rent shows it without a second banner. --}}
                    <span class="rounded-full bg-[#FBBF24]/15 px-2 py-0.5 text-[11.5px] font-semibold tabular-nums text-[#92600A]">
                        ₱{{ number_format($awaitingPayout, 2) }}<span class="sr-only"> waiting to be sent to your GCash</span>
                    </span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
