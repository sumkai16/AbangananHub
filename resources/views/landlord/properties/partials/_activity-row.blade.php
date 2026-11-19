{{--
    One line of a property's history: who (role chip), what, when. Needs $activity (a PropertyActivity).

    A payment, tenancy or review line links to where it lives; a line that is only about a unit still on the
    property opens that unit's details; anything else is plain text. Lives inside the property page's tabs root
    (it calls `openUnit(id)` from there). Tenant names appear in the sentence, never contact details.
--}}
@php
    $chip = match ($activity->actor_role) {
        'landlord' => 'bg-[#ECEEF6] text-[#060D26]',
        'tenant'   => 'bg-[#FF8A66]/15 text-[#B35A3D]',
        'admin'    => 'bg-[#060D26] text-white',
        default    => 'bg-[#F7F8FC] text-[#5B6A8E] ring-1 ring-inset ring-[#E2E4EC]',
    };
    // Where the line lives (lease, ledger, reviews) wins; a line that is only about a unit opens that unit.
    $url = $activity->targetUrl();
    $opensUnit = ! $url && $activity->unit_id && isset($unitPayloads[$activity->unit_id]);
    $when = $activity->created_at;
@endphp
<li>
    @if($opensUnit)
        <button type="button" @click="openUnit({{ $activity->unit_id }})" class="w-full flex items-start gap-3 py-3.5 text-left hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
    @elseif($url)
        <a href="{{ $url }}" class="flex items-start gap-3 py-3.5 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC] transition-colors duration-200">
    @else
        <div class="flex items-start gap-3 py-3.5">
    @endif
            <span class="mt-0.5 inline-flex h-6 min-w-[3.25rem] shrink-0 items-center justify-center rounded-full px-2.5 text-[11px] font-semibold {{ $chip }}">{{ $activity->roleLabel() }}</span>
            <span class="min-w-0 flex-1">
                <span class="block text-[13.5px] leading-snug text-[#060D26]">{{ $activity->summary }}</span>
                <time datetime="{{ $when->toIso8601String() }}" title="{{ $when->format('M j, Y \a\t g:i A') }}" class="mt-0.5 block text-[12px] text-[#5B6A8E]">
                    {{ $when->isToday() ? $when->format('g:i A') : ($when->isCurrentYear() ? $when->format('M j · g:i A') : $when->format('M j, Y')) }}
                </time>
            </span>
            @if($opensUnit || $url)
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="mt-1 shrink-0 text-[#94A3B8]" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            @endif
    @if($opensUnit)
        </button>
    @elseif($url)
        </a>
    @else
        </div>
    @endif
</li>
