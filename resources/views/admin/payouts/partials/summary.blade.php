{{--
    Where the owed money sits, in one strip: what goes out on the next run (or has to be paid by hand), what needs an
    admin, and what is waiting on someone else. The three always add up to everything owed. Built in
    PayoutController::summary().
--}}
@php
    $owed = $summary['owed'];
    $segments = [
        ['label' => $byHand ? 'Pay by hand' : ($nextRun ? 'Goes out at '.$nextRun->format('g:i A') : 'Goes out on the next run'), 'amount' => $summary['ready'], 'color' => 'bg-[#060D26]'],
        ['label' => 'Needs you', 'amount' => $summary['needs'], 'color' => 'bg-[#FF8A66]'],
        ['label' => 'Waiting on someone else', 'amount' => $summary['waiting'], 'color' => 'bg-[#AEB6D0]'],
    ];
    $held = $summary['needs'] + $summary['waiting'];
@endphp
<x-card flush class="mb-5 px-5 py-4">
    @if ($owed <= 0)
        <p class="text-[14px] text-[#5B6A8E]">Nothing is owed to landlords right now. Every released and paid rent payment has been sent.</p>
    @else
        <p class="text-[14px] leading-6 text-[#060D26]">
            <strong class="text-[17px] font-bold tabular-nums tracking-tight">₱{{ number_format($owed, 2) }}</strong>
            <span class="text-[#5B6A8E]">is owed to {{ $summary['landlords'] }} {{ Str::plural('landlord', $summary['landlords']) }}.</span>
            <strong class="tabular-nums">₱{{ number_format($summary['ready'], 2) }}</strong>
            {{ $byHand ? 'is waiting for you to pay by hand' : ($nextRun ? 'goes out at '.$nextRun->format('g:i A') : 'goes out on the next run') }}
            and <strong class="tabular-nums">₱{{ number_format($held, 2) }}</strong> is held back.
        </p>
        <div class="mt-2.5 flex h-2 gap-[3px]" role="img"
            aria-label="{{ collect($segments)->map(fn ($s) => $s['label'].' ₱'.number_format($s['amount'], 2))->implode(', ') }}">
            @foreach ($segments as $segment)
                @if ($segment['amount'] > 0)
                    <span class="{{ $segment['color'] }} first:rounded-l-full last:rounded-r-full rounded-[2px]" style="flex: {{ round($segment['amount'] / $owed * 1000) }} 1 0"></span>
                @endif
            @endforeach
        </div>
        <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-[12px] text-[#060D26]">
            @foreach ($segments as $segment)
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-[2px] {{ $segment['color'] }}" aria-hidden="true"></span>{{ $segment['label'] }} <strong class="tabular-nums">₱{{ number_format($segment['amount'], 2) }}</strong></span>
            @endforeach
        </div>
    @endif
</x-card>
