{{--
    One viewing in the Viewings tab list. Shown or hidden by the page's
    `day` filter: a picked calendar day, or (none picked) upcoming only.

    @param ViewingRequest $viewing      with is_upcoming set by the controller
    @param Collection     $properties   for the offline edit form
    @param array          $blockedIsos
--}}
@php
    $me = auth()->id();
    $iso = $viewing->scheduled_at->toDateString();
    $state = $viewing->displayStatus();
    $open = $viewing->isOpen();
    $awaitingMe = $open && $viewing->awaitsResponseFrom($me);
    $offline = $viewing->isOffline();
    $phone = $viewing->visitorPhone();
    $formId = 'offline-' . $viewing->viewing_id;

    [$pillText, $pillClass] = match (true) {
        $awaitingMe                => ['Needs your answer', 'bg-[#FBBF24]/15 text-[#B45309]'],
        $state === 'Pending'       => ['Waiting for tenant', 'bg-[#ECEEF6] text-[#5B6A8E]'],
        $state === 'Confirmed'     => ['Confirmed', 'bg-[#22C55E]/10 text-[#15803D]'],
        $state === 'Completed'     => ['Completed', 'bg-[#ECEEF6] text-[#5B6A8E]'],
        $state === 'Expired'       => ['Expired', 'bg-[#ECEEF6] text-[#94A3B8]'],
        default                    => [$state, 'bg-[#ECEEF6] text-[#5B6A8E]'],
    };
@endphp

<li x-show="day ? day === '{{ $iso }}' : {{ $viewing->is_upcoming ? 'true' : 'false' }}"
    x-data="{ picking: false, editing: {{ old('_form') === $formId ? 'true' : 'false' }}, declining: false }"
    class="px-4 sm:px-5 py-4 flex gap-3 sm:gap-4 {{ $awaitingMe ? 'bg-[#FBBF24]/[0.05]' : '' }}">

    {{-- When --}}
    <div class="w-14 shrink-0 text-center">
        <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#94A3B8]">{{ $viewing->scheduled_at->format('D') }}</p>
        <p class="text-[20px] leading-6 font-bold text-[#060D26]">{{ $viewing->scheduled_at->format('j') }}</p>
        <p class="text-[11.5px] font-semibold text-[#5B6A8E]">{{ $viewing->scheduled_at->format('g A') }}</p>
    </div>

    <div class="flex-1 min-w-0">
        <div class="flex items-start gap-2 flex-wrap">
            <p class="text-[14px] font-bold text-[#060D26] truncate">{{ $viewing->visitorName() }}</p>
            <span class="text-[10.5px] font-semibold px-2 py-0.5 rounded-full border {{ $offline ? 'border-[#5B6A8E]/30 text-[#5B6A8E]' : 'border-[#060D26]/20 text-[#060D26]' }}">
                {{ $offline ? 'Added by you' : 'In-app request' }}
            </span>
            <span class="text-[10.5px] font-semibold px-2 py-0.5 rounded-full {{ $pillClass }}">{{ $pillText }}</span>
        </div>
        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] truncate">
            {{ $viewing->property?->title }}{{ $viewing->unit ? ' · ' . $viewing->unit->unit_label : '' }}
        </p>
        @if ($phone)
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="mt-0.5 inline-block text-[12.5px] font-semibold text-[#060D26] underline decoration-[#E2E4EC] underline-offset-2 hover:decoration-[#060D26]">{{ $phone }}</a>
        @endif
        @if ($viewing->note)
            <p class="mt-1 text-[12px] text-[#5B6A8E]">“{{ $viewing->note }}”</p>
        @endif

        {{-- Actions --}}
        @if ($open)
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if ($awaitingMe)
                    <form action="{{ route('viewings.confirm', $viewing) }}" method="POST">
                        @csrf
                        <button type="submit" class="h-9 px-4 rounded-lg bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[12.5px] font-bold cursor-pointer transition-all duration-200">Approve</button>
                    </form>
                    <button type="button" @click="picking = true" class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] cursor-pointer transition-colors">Suggest another time</button>
                    <button type="button" @click="declining = !declining" class="h-9 px-3 rounded-lg text-[#DC2626] text-[12.5px] font-semibold hover:bg-[#EF4444]/5 cursor-pointer transition-colors">Decline</button>
                @elseif ($offline)
                    <button type="button" @click="editing = true" class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] cursor-pointer transition-colors">Edit / reschedule</button>
                    <form action="{{ route('landlord.viewings.destroy', $viewing) }}" method="POST"
                        data-confirm="Cancel this viewing?" data-confirm-type="danger"
                        data-confirm-message="{{ $viewing->visitorName() }}'s viewing on {{ $viewing->scheduled_at->format('M j \a\t g A') }} will be removed from your calendar. Let them know yourself — they don't have an account."
                        data-confirm-button="Cancel viewing" data-confirm-cancel="Keep it">
                        @csrf @method('DELETE')
                        <button type="submit" class="h-9 px-3 rounded-lg text-[#DC2626] text-[12.5px] font-semibold hover:bg-[#EF4444]/5 cursor-pointer transition-colors">Cancel</button>
                    </form>
                @else
                    <button type="button" @click="picking = true" class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] cursor-pointer transition-colors">Reschedule</button>
                    <form action="{{ route('viewings.cancel', $viewing) }}" method="POST"
                        data-confirm="Cancel this viewing?" data-confirm-type="danger"
                        data-confirm-message="{{ $viewing->visitorName() }} will be told the viewing is off."
                        data-confirm-button="Cancel viewing" data-confirm-cancel="Keep it">
                        @csrf
                        <button type="submit" class="h-9 px-3 rounded-lg text-[#DC2626] text-[12.5px] font-semibold hover:bg-[#EF4444]/5 cursor-pointer transition-colors">Cancel</button>
                    </form>
                @endif

                @if (! $offline && $viewing->reservation?->conversation_id)
                    <a href="{{ route('conversations.index', ['active' => $viewing->reservation->conversation_id]) }}"
                        class="h-9 px-3 rounded-lg inline-flex items-center text-[#060D26] text-[12.5px] font-semibold hover:bg-[#ECEEF6] transition-colors">Open chat</a>
                @endif
            </div>

            @if ($awaitingMe)
                <form x-show="declining" x-cloak action="{{ route('viewings.decline', $viewing) }}" method="POST" class="mt-2 flex gap-2">
                    @csrf
                    <label for="decline_{{ $viewing->viewing_id }}" class="sr-only">Reason (optional)</label>
                    <input id="decline_{{ $viewing->viewing_id }}" name="decline_reason" maxlength="255" placeholder="Reason (optional)"
                        class="flex-1 min-w-0 h-9 text-[12.5px] border border-[#E2E4EC] rounded-lg px-3 text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]/10 outline-none">
                    <button type="submit" class="h-9 bg-[#EF4444] hover:brightness-95 text-white text-[12.5px] font-bold px-4 rounded-lg cursor-pointer transition-all duration-200">Decline</button>
                </form>
            @endif
        @endif
    </div>

    @if ($open && ! $offline)
        <x-picker-modal show="picking" title="Suggest a different viewing time"
            :subtitle="$viewing->visitorName() . ' confirms it before it\'s set.'">
            @include('viewings._slot-form', [
                'action'      => route('viewings.reschedule', $viewing),
                'value'       => $viewing->scheduled_at,
                'landlordId'  => $viewing->landlord_id,
                'heading'     => 'When can they view the unit?',
                'submitLabel' => 'Send new time',
                'closeExpr'   => 'picking = false',
                'noteId'      => null,
            ])
        </x-picker-modal>
    @endif

    @if ($open && $offline)
        <x-picker-modal show="editing" title="Edit viewing" subtitle="Changes save straight away — let the visitor know yourself.">
            @include('landlord.viewings._offline-form', [
                'viewing'     => $viewing,
                'properties'  => $properties,
                'blockedIsos' => $blockedIsos,
                'closeExpr'   => 'editing = false',
            ])
        </x-picker-modal>
    @endif
</li>
