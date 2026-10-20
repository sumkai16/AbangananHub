{{--
    A concern a tenant reported, shown in the tenancy chat. Needs $message (with ->concern),
    $conversation, $isLandlord, $isTenant, $running (tenancy still active).
    Shows the concern's CURRENT status, not the status when it was posted.
--}}
@php
    $concern = $message->concern;
    $mine = $message->sender_id === auth()->id();
    $statusStyle = match ($concern->status) {
        'Fixed' => 'bg-[#22C55E]/10 text-[#15803D]',
        'In Progress' => 'bg-[#ECEEF6] text-[#060D26]',
        default => 'bg-[#FBBF24]/20 text-[#060D26]',
    };
    $photos = $concern->photos ?? [];
@endphp
<div class="w-full max-w-[340px] {{ $mine ? 'self-end' : 'self-start' }} mb-1.5"
    data-concern-card="{{ $concern->concern_id }}" @if($concern->status !== 'Fixed') data-concern-open @endif>
    <div class="bg-white border border-[#E2E4EC] rounded-2xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-4 pt-3.5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Concern &middot; {{ $concern->category }}</p>
            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[10.5px] font-bold {{ $statusStyle }}">{{ $concern->status }}</span>
        </div>

        <p class="px-4 pt-2 text-[13px] leading-relaxed text-[#060D26] whitespace-pre-wrap break-words">{{ $concern->description }}</p>

        @if($photos)
            <div class="flex gap-2 px-4 pt-3">
                @foreach($photos as $photo)
                    <button type="button" @click="photo = '{{ $photo['url'] }}'" aria-label="View photo {{ $loop->iteration }}"
                        class="h-16 w-16 overflow-hidden rounded-lg border border-[#E2E4EC] hover:border-[#FF8A66] transition-colors">
                        <img src="{{ $photo['url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                    </button>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <p class="text-[11px] text-[#5B6A8E]">
                {{ $concern->tenant?->first_name ?? 'Tenant' }} &middot; {{ $concern->created_at->format('M j, g:i A') }}
                @if($concern->isFixed() && $concern->fixed_at) &middot; Fixed {{ $concern->fixed_at->format('M j') }} @endif
            </p>

            @if($running)
                <div class="flex items-center gap-1.5">
                    @if($isLandlord && $concern->isOpen())
                        <button type="button" onclick="setConcernStatus({{ $concern->concern_id }}, 'In Progress')"
                            class="h-8 rounded-lg border border-[#E2E4EC] px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Start work</button>
                    @endif
                    @if($isLandlord && ! $concern->isFixed())
                        <button type="button" onclick="setConcernStatus({{ $concern->concern_id }}, 'Fixed')"
                            class="h-8 rounded-lg bg-[#FF8A66] px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors">Mark fixed</button>
                    @endif
                    @if($isTenant && $concern->isFixed())
                        <button type="button" onclick="setConcernStatus({{ $concern->concern_id }}, 'Open')"
                            class="h-8 rounded-lg border border-[#E2E4EC] px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Not fixed yet</button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
