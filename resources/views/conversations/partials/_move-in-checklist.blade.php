{{--
    The steps between "agreement sent" and "moved in", as one checklist both sides can read. It only restates
    state the reservation already has; it moves nothing. Needs $reservation, $paid (move-in money confirmed or held),
    $isLandlord.
--}}
@php
    $tenantName = $reservation->tenant?->first_name ?? 'The tenant';
    $detailsOnFile = $reservation->tenant ? \App\Models\TenantDetail::completeFor($reservation->tenant) : false;
    $signed = $reservation->agreed_at !== null;
    $docs = $reservation->documents;
    $steps = [
        ['Agreement sent', true, null],
        ...($docs->isNotEmpty() ? [[$isLandlord ? 'Documents uploaded' : 'Documents uploaded', $docs->every(fn ($d) => ! $d->isOutstanding()), $isLandlord ? null : route('agreements.show', $reservation).'#documents']] : []),
        [$isLandlord ? $tenantName.' signed' : 'You signed', $signed, null],
        ['Emergency contact and ID on file', $detailsOnFile, $isLandlord ? null : route('profile.edit').'#emergency-contact'],
        ['Move-in payment', $paid, null],
        ['Keys handed over', $reservation->keys_turned_over_at !== null || $reservation->rental_status === 'Occupied', null],
        ['Move-in confirmed', $reservation->rental_status === 'Occupied', null],
    ];
    $done = collect($steps)->where(1, true)->count();
    $nextIndex = collect($steps)->search(fn ($s) => ! $s[1]);
@endphp
<div class="border-t border-[#E2E4EC] pt-3" aria-label="Move-in checklist">
    <div class="flex items-center justify-between gap-2">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[#5B6A8E]">Move-in checklist</p>
        <span class="text-[11px] font-semibold text-[#5B6A8E]">{{ $done }} of {{ count($steps) }}</span>
    </div>
    <ul class="mt-2 flex flex-col gap-1.5">
        @foreach($steps as $i => [$label, $isDone, $link])
            <li class="flex items-center gap-2.5 text-[12.5px] {{ $isDone ? 'text-[#060D26]' : ($i === $nextIndex ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]') }}">
                @if($isDone)
                    <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-[#16A34A] text-[9px] font-extrabold text-white" aria-hidden="true">&#10003;</span>
                    <span class="sr-only">Done:</span>
                @else
                    <span class="h-4 w-4 shrink-0 rounded-full border-[1.5px] {{ $i === $nextIndex ? 'border-[#FF8A66]' : 'border-dashed border-[#A3ADC5]' }}" aria-hidden="true"></span>
                    <span class="sr-only">{{ $i === $nextIndex ? 'Next:' : 'To do:' }}</span>
                @endif
                @if($link && ! $isDone)
                    <a href="{{ $link }}" class="underline underline-offset-2">{{ $label }}</a>
                @else
                    {{ $label }}
                @endif
            </li>
        @endforeach
    </ul>
</div>
