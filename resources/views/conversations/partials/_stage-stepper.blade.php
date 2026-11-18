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
    // The stage rules live in ChatDealStep so this bar and the next-step row agree.
    $stageLabels = \App\Support\ChatDealStep::STAGES;
    $currentStageIndex = \App\Support\ChatDealStep::stageIndex($reservation);

    $fillPercent = $currentStageIndex !== false
        ? (($currentStageIndex + 1) / count($stageLabels)) * 100
        : 0;
@endphp

<div class="h-1.5 rounded-full bg-[#E2E4EC]" role="progressbar"
    aria-label="Rental progress" aria-valuenow="{{ $currentStageIndex !== false ? $currentStageIndex + 1 : 0 }}"
    aria-valuemin="0" aria-valuemax="{{ count($stageLabels) }}">
    <div class="h-full rounded-full bg-[#060D26] transition-all duration-300" style="width: {{ $fillPercent }}%"></div>
</div>

{{-- Phones: six labels do not fit in 375px (they ran together), so only the current stage is named. --}}
@if ($currentStageIndex !== false)
    <p class="mt-1.5 text-[9.5px] font-bold uppercase leading-tight tracking-wider text-[#060D26] sm:hidden">
        Step {{ $currentStageIndex + 1 }} of {{ count($stageLabels) }} &middot; {{ $stageLabels[$currentStageIndex] }}
    </p>
@endif

<div class="mt-1.5 hidden items-start sm:flex">
    @foreach ($stageLabels as $i => $label)
        @php
            $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
            $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
            $isLast = $i === count($stageLabels) - 1;
        @endphp
        <p class="{{ !$isLast ? 'flex-1' : '' }} text-[9.5px] font-bold uppercase leading-tight tracking-wider {{ $isLast ? 'text-right' : '' }} {{ $isCurrent ? 'text-[#060D26]' : ($isDone ? 'text-[#060D26]' : 'text-[#94A3B8]') }}">
            {{ $label }}
        </p>
    @endforeach
</div>
