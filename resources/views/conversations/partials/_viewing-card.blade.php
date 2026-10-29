{{--
    Unit viewing, inside the chat action bar. See plans/unit-viewing-scheduling.md.

    One card, four shapes depending on the newest viewing and who is looking:
      - nothing yet          → tenant: "Schedule a viewing"; landlord: nothing
      - waiting on me        → decision card: Approve / Suggest another time / Decline
      - waiting on them      → "Waiting for … to confirm" + change / cancel
      - confirmed            → the agreed time + reschedule / cancel
    Past times read as Completed or Expired (derived, see ViewingRequest::displayStatus).

    @param Reservation $reservation
    @param bool        $isLandlord
    @param User        $otherParty
--}}
@php
    $viewing = $reservation->latestViewing();
    $me = auth()->id();
    $canRequest = ! $isLandlord && $reservation->canRequestViewing();
    $open = $viewing?->isOpen() ? $viewing : null;
    $state = $viewing?->displayStatus();

    $awaitingMe = $open && $open->awaitsResponseFrom($me);
    $awaitingThem = $open && $open->status === 'Pending' && ! $awaitingMe;

    // Nothing to show a landlord until the tenant asks, and nothing at all
    // once the rental has moved past the stages where viewing makes sense
    // and there is no open viewing to finish.
    $show = $open || ($viewing && $reservation->canRequestViewing()) || $canRequest;

    $slotLong = $viewing?->scheduled_at->format('l, M j \a\t g:i A');
    $them = $otherParty->first_name;
@endphp

@if ($show)
<div class="mt-3 pt-3 border-t border-[#E2E4EC]" x-data="{ picking: false, declining: false }">

    @if ($awaitingMe)
        {{-- Decision card: the slot is the fact being decided, so it's the largest thing here. --}}
        <div class="rounded-xl border border-[#FF8A66]/30 bg-[#FF8A66]/[0.06] px-3.5 py-3">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#B35A3D]">
                {{ $isLandlord ? 'Viewing request' : 'New viewing time suggested' }}
            </p>
            <p class="mt-1 text-[15px] font-bold text-[#060D26]">{{ $slotLong }}</p>
            @if ($open->note)
                <p class="mt-1 text-[12px] text-[#5B6A8E]">“{{ $open->note }}”</p>
            @endif

            <div class="mt-3 flex flex-col sm:flex-row gap-2">
                <form action="{{ route('viewings.confirm', $open) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13px] font-bold cursor-pointer transition-all duration-200">
                        {{ $isLandlord ? 'Approve' : 'Confirm' }} {{ $open->scheduled_at->format('D, M j, g A') }}
                    </button>
                </form>
                <button type="button" @click="picking = true"
                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] cursor-pointer transition-colors">
                    Suggest another time
                </button>
                <button type="button" @click="declining = !declining"
                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-[13px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/5 cursor-pointer transition-colors">
                    Decline
                </button>
            </div>

            <form x-show="declining" x-cloak action="{{ route('viewings.decline', $open) }}" method="POST" class="mt-2 flex gap-2">
                @csrf
                <label for="decline_reason_{{ $open->viewing_id }}" class="sr-only">Reason (optional)</label>
                <input id="decline_reason_{{ $open->viewing_id }}" name="decline_reason" maxlength="255" placeholder="Reason (optional)"
                    class="flex-1 min-w-0 text-[12px] border border-[#E2E4EC] rounded-lg px-3 py-2 text-[#060D26] placeholder-[#5B6A8E] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]/10 outline-none">
                <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg cursor-pointer transition-all duration-200">Decline</button>
            </form>
        </div>

    @elseif ($open)
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-4 h-4 text-[#060D26]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[11px] font-bold {{ $awaitingThem ? 'text-[#B45309]' : 'text-[#15803D]' }}">
                    {{ $awaitingThem ? "Waiting for {$them} to confirm" : 'Viewing confirmed' }}
                </p>
                <p class="text-[13px] font-bold text-[#060D26]">{{ $slotLong }}</p>
            </div>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" @click="picking = true"
                class="px-3.5 py-1.5 rounded-lg bg-white border border-[#E2E4EC] text-[#060D26] text-[11.5px] font-semibold hover:bg-[#F7F8FC] cursor-pointer transition-all duration-200">
                {{ $awaitingThem ? 'Change the time' : 'Reschedule' }}
            </button>
            <form action="{{ route('viewings.cancel', $open) }}" method="POST"
                data-confirm="Cancel this viewing?"
                data-confirm-type="danger"
                data-confirm-message="{{ $them }} will be told the viewing on {{ $slotLong }} is off."
                data-confirm-button="Cancel viewing"
                data-confirm-cancel="Keep it">
                @csrf
                <button type="submit"
                    class="px-3.5 py-1.5 rounded-lg text-[#DC2626] text-[11.5px] font-semibold hover:bg-[#EF4444]/5 cursor-pointer transition-all duration-200">
                    Cancel viewing
                </button>
            </form>
        </div>

    @else
        {{-- No open viewing: say what happened to the last one, and let the tenant ask. --}}
        <div class="flex items-center gap-3 flex-wrap">
            <p class="flex-1 min-w-0 text-[12px] text-[#5B6A8E]">
                @switch($state)
                    @case('Completed') Viewing done on {{ $viewing->scheduled_at->format('M j') }}. @break
                    @case('Declined') The viewing on {{ $viewing->scheduled_at->format('M j') }} was declined{{ $viewing->decline_reason ? ': ' . $viewing->decline_reason : '.' }} @break
                    @case('Cancelled') The viewing on {{ $viewing->scheduled_at->format('M j') }} was cancelled. @break
                    @case('Expired') The viewing request for {{ $viewing->scheduled_at->format('M j') }} wasn't answered in time. @break
                    @default Want to see the unit before you sign?
                @endswitch
            </p>
            @if ($canRequest)
                <button type="button" @click="picking = true"
                    class="shrink-0 px-4 py-2 rounded-xl bg-white border border-[#060D26] text-[#060D26] text-[12px] font-bold hover:bg-[#ECEEF6] cursor-pointer transition-all duration-200">
                    {{ $viewing ? 'Schedule another viewing' : 'Schedule a viewing' }}
                </button>
            @endif
        </div>
    @endif

    {{-- Picker modal, teleported for the same reason as the handover one
         (_move-in-clock): inline it would crush the message list. --}}
    @if ($open || $canRequest)
        @php
            $hours = \App\Models\LandlordViewingHour::summaryFor($reservation->property->landlord_id);
        @endphp
        <x-picker-modal show="picking"
            :title="$open ? 'Suggest a different viewing time' : 'Schedule a viewing'"
            :subtitle="$them . ' confirms it before it\'s set. Viewing hours: ' . $hours . '.'">
            @if ($isLandlord)
                @include('viewings._slot-form', [
                    'action'      => $open ? route('viewings.reschedule', $open) : route('viewings.store', $reservation),
                    'value'       => $open?->scheduled_at,
                    'landlordId'  => $reservation->property->landlord_id,
                    'heading'     => $isLandlord ? 'When can they view the unit?' : 'When do you want to view the unit?',
                    'submitLabel' => $open ? 'Send new time' : 'Request viewing',
                    'closeExpr'   => 'picking = false',
                    'noteId'      => $open ? null : 'viewing_note_' . $reservation->reservation_id,
                ])
            @else
                {{-- Tenant: day tabs + open-time chips, loaded when the picker first opens. The calendar form
                     stays behind "Pick another time" for anyone who wants a day outside the first few. --}}
                <div x-data="{
                        days: [], day: 0, at: null, loaded: false, loading: false, fallback: false, failed: false,
                        async load() {
                            if (this.loaded || this.loading) return;
                            this.loading = true;
                            try {
                                const r = await fetch(@js(route('viewings.slots', $reservation)), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                                if (!r.ok) throw new Error();
                                this.days = (await r.json()).days;
                                this.loaded = true;
                                if (!this.days.length) this.fallback = true;
                            } catch (e) {
                                this.failed = true;
                                this.fallback = true;
                            }
                            this.loading = false;
                        },
                        get slots() { return this.days[this.day]?.slots ?? []; },
                        get picked() {
                            for (const d of this.days) for (const s of d.slots) if (s.at === this.at) return d.label + ' at ' + s.label;
                            return null;
                        },
                    }" x-effect="if (picking) load()">

                    <div x-show="!fallback" class="p-5 sm:p-6">
                        <p class="text-[13px] font-bold text-[#060D26]">When do you want to view the unit?</p>

                        <p x-show="loading" class="mt-4 text-[12.5px] text-[#5B6A8E]">Finding open times…</p>

                        <div x-show="loaded && days.length" x-cloak>
                            <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Day">
                                <template x-for="(d, i) in days" :key="d.date">
                                    <button type="button" role="tab" :aria-selected="day === i" @click="day = i; at = null"
                                        class="shrink-0 rounded-xl border px-3 py-2 text-[12px] font-bold leading-tight cursor-pointer transition-colors"
                                        :class="day === i ? 'border-[#060D26] bg-[#060D26] text-white' : 'border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#ECEEF6]'">
                                        <span x-text="d.label"></span>
                                    </button>
                                </template>
                            </div>

                            <div class="mt-3 grid grid-cols-3 sm:grid-cols-5 gap-2" role="listbox" aria-label="Time">
                                <template x-for="s in slots" :key="s.at">
                                    <button type="button" role="option" :disabled="!s.open" :aria-selected="at === s.at" @click="at = s.at"
                                        class="rounded-xl border px-2 py-2.5 text-[12.5px] font-semibold transition-colors"
                                        :class="at === s.at ? 'border-[#FF8A66] bg-[#FF8A66]/10 font-bold ring-2 ring-[#FF8A66]/25 text-[#060D26] cursor-pointer'
                                            : (s.open ? 'border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#ECEEF6] cursor-pointer' : 'border-[#E2E4EC] bg-[#F7F8FC] text-[#A3ADC5] line-through cursor-not-allowed')"
                                        x-text="s.label"></button>
                                </template>
                            </div>

                            <form action="{{ $open ? route('viewings.reschedule', $open) : route('viewings.store', $reservation) }}" method="POST" class="mt-4 flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="scheduled_at" :value="at">
                                @unless ($open)
                                    <div>
                                        <label for="viewing_note_chips_{{ $reservation->reservation_id }}" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Note for the landlord (optional)</label>
                                        <input id="viewing_note_chips_{{ $reservation->reservation_id }}" name="note" maxlength="500" placeholder="e.g. I'll bring my sister"
                                            class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[13px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
                                    </div>
                                @endunless
                                <button type="submit" :disabled="!at"
                                    class="w-full rounded-xl bg-[#FF8A66] px-5 py-3 text-[14px] font-bold text-[#060D26] hover:bg-[#E96F4F] disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all duration-200">
                                    <span x-text="picked ? '{{ $open ? 'Send' : 'Request' }} ' + picked : '{{ $open ? 'Pick a new time' : 'Pick a time' }}'"></span>
                                </button>
                            </form>

                            <p class="mt-3 text-[11.5px] text-[#5B6A8E]">Based on {{ $them }}'s viewing hours. Struck-through times are taken.</p>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <button type="button" x-show="loaded && days.length" x-cloak @click="fallback = true"
                                class="text-[12px] font-bold text-[#060D26] underline underline-offset-2 cursor-pointer">Pick another time</button>
                            <button type="button" @click="picking = false"
                                class="text-[12px] font-semibold text-[#5B6A8E] hover:text-[#060D26] cursor-pointer">Cancel</button>
                        </div>
                    </div>

                    <div x-show="fallback" x-cloak>
                        <p x-show="failed" class="px-5 sm:px-6 pt-4 text-[12px] text-[#B45309]">We couldn't load open times. Pick a date below instead.</p>
                        <p x-show="loaded && !days.length" class="px-5 sm:px-6 pt-4 text-[12px] text-[#B45309]">No open times in the next few days. Pick a date below instead.</p>
                @include('viewings._slot-form', [
                    'action'      => $open ? route('viewings.reschedule', $open) : route('viewings.store', $reservation),
                    'value'       => $open?->scheduled_at,
                    'landlordId'  => $reservation->property->landlord_id,
                    'heading'     => $isLandlord ? 'When can they view the unit?' : 'When do you want to view the unit?',
                    'submitLabel' => $open ? 'Send new time' : 'Request viewing',
                    'closeExpr'   => 'picking = false',
                    'noteId'      => $open ? null : 'viewing_note_' . $reservation->reservation_id,
                ])
                        <div x-show="loaded && days.length" class="px-5 sm:px-6 pb-4">
                            <button type="button" @click="fallback = false" class="text-[12px] font-bold text-[#060D26] underline underline-offset-2 cursor-pointer">Back to open times</button>
                        </div>
                    </div>
                </div>
            @endif
        </x-picker-modal>
    @endif
</div>
@endif
