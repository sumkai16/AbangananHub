{{--
    Top-level switch of the Reservations page: rental requests vs viewings.
    Separate from the status tabs below it, which only filter rental requests.

    @param string $active          'requests' | 'viewings'
    @param int    $pendingViewings viewing requests waiting on the landlord
--}}
<nav class="mb-5 inline-flex p-1 rounded-full bg-[#ECEEF6] w-full sm:w-auto" aria-label="Reservations sections">
    @foreach ([
        'requests' => ['Rental requests', route('landlord.reservations.index')],
        'viewings' => ['Viewings', route('landlord.viewings.index')],
    ] as $key => [$label, $href])
        <a href="{{ $href }}" @if ($active === $key) aria-current="page" @endif
            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 h-10 px-5 rounded-full text-[13px] font-semibold transition-colors
                {{ $active === $key ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]' }}">
            {{ $label }}
            @if ($key === 'viewings' && $pendingViewings > 0)
                <span class="min-w-[20px] h-5 px-1.5 rounded-full bg-[#060D26] text-white text-[11px] font-bold inline-flex items-center justify-center"
                    aria-label="{{ $pendingViewings }} waiting for you">{{ $pendingViewings }}</span>
            @endif
        </a>
    @endforeach
</nav>
