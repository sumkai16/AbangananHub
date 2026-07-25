{{--
    The six-node rental progress stepper.

    Extracted so the participants' thread and the admin's read-only view render
    the same thing from the same rules. "Paid" is derived — the reservation
    sits on 'Rental Agreement Signed' the whole time money is escrowed and only
    moves to 'Occupied' on move-in confirmation — so a second copy of this
    match() elsewhere would eventually disagree about which stage a reservation
    is in, on the money path.

    @param $reservation  Reservation|null
--}}

@php
    $stageLabels = ['Inquiry', 'Negotiation', 'Agreement', 'Signed', 'Paid', 'Occupied'];

    $settled = $reservation?->payments->contains(
        fn ($p) => in_array($p->status, ['Held', 'Released'], true)
    );

    $currentStageIndex = match ($reservation?->rental_status) {
        'Inquiry' => 0,
        'Under Negotiation' => 1,
        'Pending Rental Agreement' => 2,
        'Rental Agreement Signed' => $settled ? 4 : 3,
        'Occupied' => 5,
        default => false,
    };
@endphp

<ol class="flex items-start" aria-label="Rental progress">
    @foreach ($stageLabels as $i => $stage)
        @php
            $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
            $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
            $isLast = $i === count($stageLabels) - 1;
        @endphp
        <li class="flex items-start {{ !$isLast ? 'flex-1' : '' }} min-w-0" @if ($isCurrent) aria-current="step" @endif>
            <div class="flex flex-col items-center gap-1.5 {{ !$isLast ? 'w-[22px]' : '' }} flex-shrink-0">
                @if ($isDone)
                    <div class="w-[18px] h-[18px] rounded-full bg-[#2AA7A1] flex items-center justify-center">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                @elseif ($isCurrent)
                    <div class="w-[18px] h-[18px] rounded-full bg-[#2AA7A1] flex items-center justify-center ring-4 ring-[#2AA7A1]/15">
                        <div class="w-1.5 h-1.5 rounded-full bg-white"></div>
                    </div>
                @else
                    <div class="w-[18px] h-[18px] rounded-full border-2 border-[#E2E8F0] bg-white"></div>
                @endif
            </div>

            @if (!$isLast)
                <div class="flex-1 h-0.5 rounded-full mt-[8px] mx-1 {{ $isDone ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]' }}"></div>
            @endif
        </li>
    @endforeach
</ol>

<div class="flex items-start mt-1.5">
    @foreach ($stageLabels as $i => $label)
        @php
            $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
            $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
            $isLast = $i === count($stageLabels) - 1;
        @endphp
        <p class="{{ !$isLast ? 'flex-1' : '' }} text-[9.5px] leading-tight tracking-wide {{ $isLast ? 'text-right' : '' }} {{ $isCurrent ? 'font-bold text-[#156F8C]' : ($isDone ? 'font-semibold text-[#1F2937]' : 'text-[#64748B]') }}">
            {{ $label }}
        </p>
    @endforeach
</div>
