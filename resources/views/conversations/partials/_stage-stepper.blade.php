{{--
    The slim rental progress bar.

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

    $fillPercent = $currentStageIndex !== false
        ? (($currentStageIndex + 1) / count($stageLabels)) * 100
        : 0;
@endphp

<div class="h-1.5 rounded-full bg-[#E2E8F0]" role="progressbar"
    aria-label="Rental progress" aria-valuenow="{{ $currentStageIndex !== false ? $currentStageIndex + 1 : 0 }}"
    aria-valuemin="0" aria-valuemax="{{ count($stageLabels) }}">
    <div class="h-full rounded-full bg-[#2AA7A1] transition-all duration-300" style="width: {{ $fillPercent }}%"></div>
</div>

<div class="flex items-start mt-1.5">
    @foreach ($stageLabels as $i => $label)
        @php
            $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
            $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
            $isLast = $i === count($stageLabels) - 1;
        @endphp
        <p class="{{ !$isLast ? 'flex-1' : '' }} text-[9.5px] font-bold uppercase leading-tight tracking-wider {{ $isLast ? 'text-right' : '' }} {{ $isCurrent ? 'text-[#156F8C]' : ($isDone ? 'text-[#1F2937]' : 'text-[#94A3B8]') }}">
            {{ $label }}
        </p>
    @endforeach
</div>
