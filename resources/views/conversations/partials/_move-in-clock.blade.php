{{--
    The live move-in deadline and the key-handover schedule, worded for
    whoever is reading.

    There is one deadline column and two clocks; isTurnoverClock() is the
    switch (see Reservation). Clock 1 runs from payment and belongs to the
    landlord — turn over the keys or the reservation escalates to admin
    review. Clock 2 starts at turnover and belongs to the tenant — confirm
    within move_in_confirmation_days or the deposit releases automatically.

    Clock 1's deadline used to derive from target_move_in_date, which the
    tenant is required to pick at inquiry time before the landlord has even
    replied, and which nothing can edit afterwards. A handover slot the pair
    actually agree on replaces that guess — see Reservation::confirmHandover().

    @param $reservation
    @param $isLandlord  who is reading
    @param $otherParty  the counterparty (tenant for a landlord, and vice versa)
--}}

@php
    $disputed = $reservation->move_in_disputed_at !== null;
    $onTurnoverClock = $reservation->isTurnoverClock();

    // Within Clock 1, move_in_deadline_at is Clock 1's own deadline — written
    // by the nightly backfill, or by a confirmed handover slot. Falling back to
    // the computed value covers the window before the first nightly run.
    $deadlineAt = $onTurnoverClock
        ? ($reservation->move_in_deadline_at ?? $reservation->computeTurnoverDeadline())
        : $reservation->move_in_deadline_at;

    $daysLeft = $disputed || ! $deadlineAt
        ? null
        : (int) round(now()->startOfDay()->diffInDays($deadlineAt->copy()->startOfDay(), false));

    $urgent = ! $disputed && $daysLeft !== null && $daysLeft <= 1;

    $tone = match (true) {
        $disputed => ['bg' => 'bg-[#FBBF24]/[0.10]', 'text' => 'text-[#B45309]'],
        $urgent   => ['bg' => 'bg-[#EF4444]/[0.07]', 'text' => 'text-[#DC2626]'],
        default   => ['bg' => 'bg-[#EEF8F8]',        'text' => 'text-[#156F8C]'],
    };

    $them = $otherParty->first_name;
    $by = $deadlineAt?->format('M j');
    $overdue = $daysLeft !== null && $daysLeft < 0;

    // Handover state — only meaningful while Clock 1 is running.
    $slot = $reservation->handover_at?->format('M j, g:i A');
    $slotDay = $reservation->handover_at?->format('D, M j');
    $slotTime = $reservation->handover_at?->format('g:i A');
    $slotFull = $reservation->handover_at?->format('D, M j \a\t g:i A');
    $confirmedSlot = $onTurnoverClock && $reservation->hasConfirmedHandover();
    $proposedSlot = $onTurnoverClock && $reservation->hasProposedHandover();
    $iProposed = $reservation->handover_proposed_by === auth()->id();
    $canSchedule = $onTurnoverClock && ! $disputed && auth()->user()->can('scheduleHandover', $reservation);

    // A proposal from the other side isn't status, it's a request waiting on
    // this reader — so it gets a decision card rather than a line of text.
    $awaitingMyAnswer = $proposedSlot && ! $iProposed && ! $disputed;

    if ($disputed) {
        $headline = 'Move-in issue reported — an administrator is reviewing it.';
        $detail = 'The deposit stays on hold and the countdown is paused.';
    } elseif ($onTurnoverClock) {
        $headline = match (true) {
            $awaitingMyAnswer => "{$them} proposed a handover time",
            $confirmedSlot => "Key handover: {$slot}",
            $proposedSlot && $iProposed => "You proposed a handover on {$slot}",
            default => "Agree a key handover time with {$them}",
        };

        $detail = match (true) {
            $awaitingMyAnswer => 'Confirm it, or suggest a time that works better.',
            $overdue && $isLandlord => "Turnover is overdue — this reservation goes to admin review. Mark the keys turned over as soon as {$them} has them.",
            $overdue => "The keys were due by {$by}. Your deposit is still held, and an administrator steps in if this isn't resolved.",
            $proposedSlot && $iProposed => "Waiting for {$them} to confirm. Escalates to admin review if the keys aren't turned over by {$by}.",
            $isLandlord => "Escalates to admin review if the keys aren't turned over by {$by}.",
            default => "Your deposit is held until you confirm move-in. Escalates to admin review if the keys aren't turned over by {$by}.",
        };
    } else {
        $headline = match (true) {
            $overdue && $isLandlord => "{$them}'s confirmation window has closed",
            $overdue => 'Your confirmation window has closed',
            $isLandlord => "Waiting for {$them} to confirm move-in",
            $daysLeft === 0 => 'Today is your last day to confirm your move-in',
            default => 'Confirm your move-in',
        };

        $detail = match (true) {
            $overdue && $isLandlord => 'The deposit is released to you automatically.',
            $overdue => "It closed on {$by} — the deposit is released to the landlord automatically.",
            $isLandlord => 'Once they do, the deposit is released to you.',
            default => "Confirm by {$by} or the deposit is released to the landlord automatically.",
        };
    }
@endphp

{{-- Collapsed it's a one-line status strip; with a proposal waiting it expands
     into a panel whose tinted head is that same strip. Collapsible because the
     action bar is flex-shrink-0 inside a fixed-height column — an expanded card
     plus the escrow prompt leaves almost no room for the messages, and reading
     the thread is why anyone is on this screen. --}}
<div x-data="{ scheduling: false, expanded: {{ $awaitingMyAnswer ? 'true' : 'false' }} }"
    :class="expanded ? 'rounded-2xl border border-[#E2E8F0] overflow-hidden bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)]' : ''">

    <div class="{{ $tone['bg'] }}"
        :class="expanded ? 'px-4 sm:px-6 py-4' : 'px-3 py-2.5 rounded-xl'">
    <div class="flex items-start gap-2.5">
        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 {{ $tone['text'] }}" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" stroke-width="2" aria-hidden="true" x-show="!expanded">
            @if ($disputed)
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            @endif
        </svg>

        <div class="flex-1 min-w-0">
            <p class="font-bold leading-snug {{ $tone['text'] }}"
                :class="expanded ? 'text-[15px] sm:text-[16px]' : 'text-[12px]'">{{ $headline }}</p>
            <p class="leading-relaxed {{ $tone['text'] }} opacity-90"
                :class="expanded ? 'mt-1 text-[12.5px] sm:text-[13px]' : 'mt-0.5 text-[11px]'">{{ $detail }}</p>

            @if ($confirmedSlot && $reservation->handoverDeadlineWasCapped())
                {{-- Say so rather than showing a date nobody picked. --}}
                <p class="mt-1 text-[10.5px] leading-relaxed {{ $tone['text'] }} opacity-75">
                    The review deadline is capped at {{ config('rentals.handover_max_extension_days') }} days past the
                    original, so it doesn't move with the agreed time.
                </p>
            @endif
        </div>

        @if (! $disputed && $daysLeft !== null && $daysLeft >= 0)
            {{-- Pill while the panel is open, bare text in the collapsed strip.
                 Colour still tracks urgency rather than being decorative: teal
                 normally, red at a day or less. --}}
            <span class="shrink-0 font-bold whitespace-nowrap {{ $tone['text'] }}"
                :class="expanded
                    ? 'text-[12px] px-3 py-1 rounded-full border {{ $urgent ? 'border-[#EF4444]/30 bg-[#EF4444]/[0.07]' : 'border-[#2AA7A1]/30 bg-white' }}'
                    : 'text-[11px]'">
                {{ $daysLeft === 0 ? 'today' : $daysLeft . ' ' . Str::plural('day', $daysLeft) . ' left' }}
            </span>
        @endif

        @if ($awaitingMyAnswer && $canSchedule)
            <button type="button" @click="expanded = !expanded"
                :aria-expanded="expanded"
                class="shrink-0 -mr-1 w-7 h-7 rounded-lg flex items-center justify-center {{ $tone['text'] }} hover:bg-white/60 transition-colors">
                <span class="sr-only" x-text="expanded ? 'Hide the proposed handover time' : 'Show the proposed handover time'"></span>
                <svg class="w-4 h-4 transition-transform motion-reduce:transition-none" :class="expanded ? 'rotate-180' : ''"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        @endif
    </div>
    </div>

    {{-- ── Decision card: a proposal from the other side ─
         The slot is the largest thing on screen here because it is the fact
         being decided. As a line of 12px prose it was the smallest. --}}
    @if ($awaitingMyAnswer && $canSchedule)
        {{-- x-show, not x-collapse: @alpinejs/collapse isn't installed and an
             unregistered directive is silently ignored, which would have left
             dead markup that reads as an animation nobody gets. --}}
        <div class="px-4 sm:px-6 py-5" x-show="expanded" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1 motion-reduce:translate-y-0">
            <div class="flex items-center gap-4 rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] px-4 sm:px-5 py-4">
                <div
                    class="w-11 h-11 shrink-0 rounded-xl bg-white border border-[#E2E8F0] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[20px] font-bold tracking-tight text-[#1F2937] leading-tight">{{ $slotDay }}</p>
                    <p class="text-[15px] font-medium text-[#64748B]">{{ $slotTime }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                <form action="{{ route('handover.confirm', $reservation) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit"
                        class="w-full sm:w-auto px-6 py-3 rounded-xl bg-[#2AA7A1] text-white text-[13.5px] sm:text-[14px] font-bold hover:brightness-95 cursor-pointer transition-all duration-200">
                        Confirm {{ $slotFull }}
                    </button>
                </form>
                <button type="button" @click="scheduling = true"
                    class="w-full sm:w-auto px-4 py-3 rounded-xl text-[13.5px] sm:text-[14px] font-semibold text-[#1F2937] hover:bg-[#EEF8F8] cursor-pointer transition-colors">
                    Suggest another time
                </button>
            </div>
        </div>
    @endif

    {{-- ── Handover actions ───────────────────────────── --}}
    @if ($canSchedule && ! $awaitingMyAnswer)
        <div class="mt-2.5 flex flex-wrap gap-2 px-3 pb-0.5" x-show="!scheduling">
            <button type="button" @click="scheduling = true"
                class="px-3.5 py-1.5 rounded-lg bg-white border border-[#E2E8F0] text-[#1F2937] text-[11.5px] font-semibold hover:bg-[#F7FCFC] cursor-pointer transition-all duration-200">
                @if ($confirmedSlot)
                    Reschedule
                @elseif ($proposedSlot)
                    Change the time you proposed
                @else
                    Propose a time
                @endif
            </button>
        </div>
    @endif

    {{-- The picker is ~600px tall. Inline, it sat inside a flex-shrink-0 action
         bar in a fixed-height column: it crushed the message list, overflowed
         the panel, and on a short viewport its footer buttons were unreachable
         because the clipping container had no scroll of its own. Teleported to
         body it escapes that column entirely, and the backdrop scrolls when the
         content is taller than the screen. --}}
    @if ($canSchedule)
        <template x-teleport="body">
            <div x-show="scheduling" x-cloak
                @keydown.escape.window="scheduling = false"
                class="fixed inset-0 z-[200] overflow-y-auto bg-black/40 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0">
                <div class="min-h-full flex items-start sm:items-center justify-center p-3 sm:p-6">
                    <div @click.outside="scheduling = false" role="dialog" aria-modal="true"
                        aria-label="Choose a key handover time"
                        class="w-full max-w-3xl rounded-2xl bg-white shadow-xl overflow-hidden"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95 motion-reduce:scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-end="opacity-0 scale-95 motion-reduce:scale-100">

                        <div class="{{ $tone['bg'] }} px-5 sm:px-6 py-4 flex items-start gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-[15px] sm:text-[16px] font-bold {{ $tone['text'] }}">
                                    {{ $confirmedSlot || $proposedSlot ? 'Suggest a different handover time' : 'Propose a handover time' }}
                                </p>
                                <p class="mt-1 text-[12.5px] {{ $tone['text'] }} opacity-90">
                                    {{ $them }} confirms it before it becomes the agreed time.
                                </p>
                            </div>
                            <button type="button" @click="scheduling = false"
                                class="shrink-0 -mr-1 w-8 h-8 rounded-lg flex items-center justify-center {{ $tone['text'] }} hover:bg-white/60 transition-colors">
                                <span class="sr-only">Close</span>
                                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form action="{{ route('handover.propose', $reservation) }}" method="POST">
                            @csrf
                            <x-datetime-picker name="handover_at" :value="$reservation->handover_at" :min="now()"
                                :deadline="$deadlineAt">
                                <button type="submit" :disabled="!value"
                                    class="w-full sm:w-auto px-7 py-3 rounded-xl bg-[#2AA7A1] text-white text-[14px] font-bold hover:brightness-95 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:brightness-100 cursor-pointer transition-all duration-200">
                                    {{ $confirmedSlot || $proposedSlot ? 'Propose new time' : 'Propose time' }}
                                </button>
                                <button type="button" @click="scheduling = false"
                                    class="w-full sm:w-auto px-4 py-3 rounded-xl text-[14px] font-semibold text-[#1F2937] hover:bg-[#EEF8F8] cursor-pointer transition-colors">
                                    Cancel
                                </button>
                            </x-datetime-picker>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>

{{-- Turnover is the landlord's move and it starts the tenant's clock, so the
     button belongs next to the countdown that's waiting on it. Same route,
     Gate and confirm copy as the Reservations index — this is a second entry
     point, not a second implementation. --}}
@if ($isLandlord && $onTurnoverClock && ! $disputed && auth()->user()->can('markTurnedOver', $reservation))
    <form action="{{ route('landlord.reservations.markTurnedOver', $reservation) }}" method="POST" class="mt-2"
        data-confirm="Mark keys as turned over?"
        data-confirm-type="confirm"
        data-confirm-message="{{ $them }} will have {{ config('rentals.move_in_confirmation_days') }} days to confirm move-in, after which the held deposit is released to you automatically."
        data-confirm-button="Mark as turned over"
        data-confirm-cancel="Not yet">
        @csrf
        <button type="submit"
            class="w-full bg-[#2AA7A1] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl cursor-pointer transition-all duration-200">
            Mark keys turned over
        </button>
    </form>
@endif
