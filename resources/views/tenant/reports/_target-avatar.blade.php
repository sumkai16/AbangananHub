{{-- The small tile beside a reported listing (house) or person (initials), as on My Reservations. --}}
@if($target['listing'] === true)
    <div class="w-11 h-11 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0" aria-hidden="true">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
    </div>
@elseif($target['listing'] === false)
    <div class="w-11 h-11 rounded-full bg-[#060D26] flex items-center justify-center shrink-0 text-[13px] font-bold text-white" aria-hidden="true">{{ strtoupper(mb_substr($target['name'], 0, 1)) }}</div>
@else
    <div class="w-11 h-11 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0 text-[#5B6A8E]" aria-hidden="true">—</div>
@endif
