{{--
    The two tabs of the Rental policies page: Policies (assign a policy to a property or unit) and Generate agreement
    (a printable agreement from a name, a property and a unit). Real links, so each tab has its own URL and back button.
    $active: 'policies' | 'agreement'.
--}}
@php
    $tabs = [
        'policies' => ['Policies', route('landlord.leases.index')],
        'agreement' => ['Generate agreement', route('landlord.leases.agreement')],
    ];
@endphp
<nav class="mt-5 mb-6 flex gap-1 border-b border-[#E2E4EC] print:hidden" aria-label="Rental policies sections">
    @foreach($tabs as $key => [$text, $href])
        <a href="{{ $href }}" @if($active === $key) aria-current="page" @endif
            class="relative -mb-px inline-flex h-11 items-center px-4 text-[13.5px] font-semibold border-b-2 transition-colors duration-200 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 rounded-t-lg {{ $active === $key ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]' }}">{{ $text }}</a>
    @endforeach
</nav>
