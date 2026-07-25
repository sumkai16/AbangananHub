@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    @php
        $landlord = $reservation->property->landlord;
        $tenant = $reservation->tenant;
        // Stable, human-quotable identifier. A contract people are asked to
        // sign needs something to reference it by in a dispute or a message.
        $agreementRef = 'AGR-' . $reservation->created_at->format('Y') . '-' . str_pad($reservation->reservation_id, 5, '0', STR_PAD_LEFT);
        $heldPayment = $reservation->payments->where('status', 'Held')->first();
        $hasPayment = $reservation->payments->whereIn('status', ['Pending', 'Held', 'Paid', 'Released'])->isNotEmpty();
        $processingPayment = $hasPayment && !$heldPayment && !$reservation->isOccupied();
    @endphp

    {{-- 1200 = 768 document + 400 action rail + gap. The document keeps the
         max-w-3xl reading measure it has always had; the rail fills what used
         to be dead margin, and carries the signing controls so they are on
         screen from the moment the page loads instead of below the fold. --}}
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 py-8 min-h-[calc(100vh-72px)]">

        {{-- Page chrome — never printed --}}
        <div class="flex items-center justify-between gap-3 mb-6 print:hidden">
            <a href="{{ route('reservations.index') }}"
                class="inline-flex items-center text-sm font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors group">
                <svg class="w-4 h-4 mr-2 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Back to Reservations
            </a>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-[#E2E8F0] bg-white text-[12px] font-bold text-[#1F2937] hover:bg-[#F7FCFC] cursor-pointer transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                </svg>
                Print / Save PDF
            </button>
        </div>

        {{-- Page header — bare on the background per DESIGN.md §6b --}}
        <div class="mb-6">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h1 class="text-2xl font-bold text-[#1F2937]">Rental Agreement</h1>
                <p class="text-[11px] font-bold text-[#64748B] tracking-wider">{{ $agreementRef }}</p>
            </div>
            <p class="text-sm text-[#64748B] mt-1 print:hidden">Please read the terms below carefully before signing.</p>
        </div>

        {{-- print:block — on paper there is no rail, so the document must not
             be squeezed into a grid column that no longer has a sibling. --}}
        <div class="grid lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-8 lg:items-start print:block">

        {{-- flush: the card's default p-5 sm:p-6 would collide with the wider
             padding this document wants, and with print:p-0. --}}
        <x-card flush class="p-5 sm:p-8 print:border-none print:shadow-none print:p-0">

            {{-- ===== Parties ===== --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] p-4">
                    <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-1.5">Landlord</p>
                    <p class="text-[14px] font-bold text-[#1F2937]">{{ $landlord->first_name }} {{ $landlord->last_name }}</p>
                    <p class="text-[11.5px] text-[#64748B] mt-0.5 break-words">{{ $landlord->email }}</p>
                    @if($landlord->contact_number)
                        <p class="text-[11.5px] text-[#64748B]">{{ $landlord->contact_number }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] p-4">
                    <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-1.5">Tenant</p>
                    <p class="text-[14px] font-bold text-[#1F2937]">{{ $tenant->first_name }} {{ $tenant->last_name }}</p>
                    <p class="text-[11.5px] text-[#64748B] mt-0.5 break-words">{{ $tenant->email }}</p>
                    @if($tenant->contact_number)
                        <p class="text-[11.5px] text-[#64748B]">{{ $tenant->contact_number }}</p>
                    @endif
                </div>
            </div>

            {{-- ===== The agreement body =====
                 No border or tint: this is the document text itself, so it sits
                 directly on the sheet. Boxing it inside the card that already
                 frames it was a third nested border for no added meaning. --}}
            <div class="text-[#1F2937] leading-relaxed">
                <p class="text-[13.5px] leading-relaxed">
                    This Rental Agreement is entered into between
                    <strong>{{ $landlord->first_name }} {{ $landlord->last_name }}</strong> ("Landlord")
                    and <strong>{{ $tenant->first_name }} {{ $tenant->last_name }}</strong> ("Tenant"),
                    concerning the rental of the property located at:
                </p>

                <p class="font-bold text-[#1F2937] text-[14px] mt-3">{{ $reservation->property->address }}</p>
                <p class="text-[12px] text-[#64748B] mt-0.5">
                    {{ $reservation->property->title }} &middot; {{ $reservation->unit->unit_label }}
                    @if($reservation->unit->unit_type) &middot; {{ $reservation->unit->unit_type }} @endif
                </p>

                <dl class="mt-5 divide-y divide-[#E2E8F0] border-t border-[#E2E8F0]">
                    <div class="flex items-baseline justify-between gap-4 py-2.5">
                        <dt class="text-[13px] text-[#64748B]">Rental Fee</dt>
                        <dd class="text-[13px] font-bold text-[#1F2937] text-right">&#8369;{{ number_format($reservation->unit->rental_fee, 2) }} / month</dd>
                    </div>
                    @if($reservation->unit->security_deposit)
                        <div class="flex items-baseline justify-between gap-4 py-2.5">
                            <dt class="text-[13px] text-[#64748B]">Security Deposit</dt>
                            <dd class="text-[13px] font-bold text-[#1F2937] text-right">&#8369;{{ number_format($reservation->unit->security_deposit, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-4 py-2.5">
                        <dt class="text-[13px] text-[#64748B]">Reservation Date</dt>
                        <dd class="text-[13px] font-bold text-[#1F2937] text-right">{{ $reservation->reservation_date->format('F j, Y') }}</dd>
                    </div>
                    @if($reservation->target_move_in_date)
                        <div class="flex items-baseline justify-between gap-4 py-2.5">
                            <dt class="text-[13px] text-[#64748B]">Target Move-In</dt>
                            <dd class="text-[13px] font-bold text-[#1F2937] text-right">{{ $reservation->target_move_in_date->format('F j, Y') }}</dd>
                        </div>
                    @endif
                    @if($reservation->target_move_out_date)
                        <div class="flex items-baseline justify-between gap-4 py-2.5">
                            <dt class="text-[13px] text-[#64748B]">Target Move-Out</dt>
                            <dd class="text-[13px] font-bold text-[#1F2937] text-right">{{ $reservation->target_move_out_date->format('F j, Y') }}</dd>
                        </div>
                    @endif
                    @if($reservation->occupants_count)
                        <div class="flex items-baseline justify-between gap-4 py-2.5">
                            <dt class="text-[13px] text-[#64748B]">Occupants</dt>
                            <dd class="text-[13px] font-bold text-[#1F2937] text-right">{{ $reservation->occupants_count }}</dd>
                        </div>
                    @endif
                </dl>

                @if($reservation->agreement_terms_notes)
                    <div class="mt-5 pt-4 border-t border-[#E2E8F0]">
                        <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-1.5">Additional Terms</p>
                        <p class="whitespace-pre-wrap text-[13px] text-[#1F2937] leading-relaxed">{{ $reservation->agreement_terms_notes }}</p>
                    </div>
                @endif

                <p class="text-[11.5px] text-[#64748B] leading-relaxed mt-5 pt-4 border-t border-[#E2E8F0]">
                    By signing this agreement, both parties acknowledge the terms above as the basis for this rental
                    arrangement. AbangananHub facilitates this agreement as a record-keeping tool between Landlord and
                    Tenant and is not a party to, nor liable for, the terms herein.
                </p>
            </div>

            {{-- ===== Signature block — the evidentiary record ===== --}}
            @if($reservation->agreed_at || $reservation->landlord_tc_accepted_at)
                <div class="mt-6 rounded-xl border border-[#E2E8F0] p-5">
                    <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-3">Signatures</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-[13px] font-bold text-[#1F2937]">{{ $landlord->first_name }} {{ $landlord->last_name }}</p>
                            <p class="text-[11px] text-[#64748B]">Landlord</p>
                            @if($reservation->landlord_tc_accepted_at)
                                <p class="text-[11px] text-[#15803D] font-semibold mt-1.5">
                                    Accepted {{ $reservation->landlord_tc_accepted_at->format('F j, Y \a\t g:i A') }}
                                </p>
                            @else
                                <p class="text-[11px] text-[#64748B] mt-1.5">Awaiting acceptance</p>
                            @endif
                        </div>
                        <div class="sm:border-l sm:border-[#E2E8F0] sm:pl-4">
                            <p class="text-[13px] font-bold text-[#1F2937]">{{ $tenant->first_name }} {{ $tenant->last_name }}</p>
                            <p class="text-[11px] text-[#64748B]">Tenant</p>
                            @if($reservation->agreed_at)
                                <p class="text-[11px] text-[#15803D] font-semibold mt-1.5">
                                    Signed {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}
                                </p>
                                @if($reservation->agreed_ip)
                                    <p class="text-[10.5px] text-[#64748B] mt-0.5">Recorded from {{ $reservation->agreed_ip }}</p>
                                @endif
                            @else
                                <p class="text-[11px] text-[#64748B] mt-1.5">Not yet signed</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        </x-card>

        {{-- ===== Action rail — never printed ===== --}}
        <aside class="mt-6 lg:mt-0 lg:sticky lg:top-8 print:hidden">

            {{-- What you are committing to, restated so the figures are in
                 view while the controls are. Reading the document is still the
                 point; this stops the money being off-screen at the moment of
                 signing. --}}
            <div class="rounded-2xl border border-[#E2E8F0] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 mb-4">
                <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider">At a glance</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937]">
                    &#8369;{{ number_format($reservation->unit->rental_fee, 2) }}
                    <span class="text-sm font-medium text-[#64748B]">/ month</span>
                </p>
                <dl class="mt-4 space-y-2 text-[13px]">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-[#64748B]">Property</dt>
                        <dd class="font-medium text-[#1F2937] text-right truncate">{{ $reservation->unit->unit_label }}</dd>
                    </div>
                    @if($reservation->target_move_in_date)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[#64748B]">Target move-in</dt>
                            <dd class="font-medium text-[#1F2937]">{{ $reservation->target_move_in_date->format('M j, Y') }}</dd>
                        </div>
                    @endif
                    @if($reservation->unit->security_deposit)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[#64748B]">Security deposit</dt>
                            <dd class="font-medium text-[#1F2937]">&#8369;{{ number_format($reservation->unit->security_deposit, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-[#64748B]">Reference</dt>
                        <dd class="font-medium text-[#1F2937]">{{ $agreementRef }}</dd>
                    </div>
                </dl>
            </div>

            <div>
                @if($reservation->rental_status === 'Pending Rental Agreement')
                    <form action="{{ route('agreements.sign', $reservation) }}" method="POST"
                        class="rounded-2xl border border-[#E2E8F0] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                        @csrf

                        <div class="flex items-start gap-3 p-4 bg-[#EF4444]/10 border border-[#EF4444]/25 rounded-xl mb-5">
                            <svg class="w-5 h-5 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                            </svg>
                            <p class="text-[12px] text-[#EF4444] leading-relaxed">
                                Please read the entire agreement above before proceeding. Signing is a binding acknowledgment
                                of the terms stated.
                            </p>
                        </div>

                        <label for="agree" class="flex items-start gap-3 mb-4 cursor-pointer">
                            <input type="checkbox" name="agree" id="agree" required
                                class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                            <span class="text-[13px] text-[#1F2937] leading-relaxed">
                                I have read and agree to the terms of this Rental Agreement.
                            </span>
                        </label>
                        @error('agree')
                            <p class="text-[11px] font-semibold text-[#EF4444] -mt-2 mb-4 ml-7">{{ $message }}</p>
                        @enderror

                        <label for="accept_tc" class="flex items-start gap-3 mb-4 cursor-pointer">
                            <input type="checkbox" name="accept_tc" id="accept_tc" required
                                class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                            <span class="text-[13px] text-[#1F2937] leading-relaxed">
                                I understand that my payment will be held by AbangananHub until I confirm move-in. Funds will only
                                be released to the landlord after I verify that the unit matches the listing.
                            </span>
                        </label>
                        @error('accept_tc')
                            <p class="text-[11px] font-semibold text-[#EF4444] -mt-2 mb-4 ml-7">{{ $message }}</p>
                        @enderror

                        <button type="submit"
                            class="w-full bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm cursor-pointer transition-all duration-200">
                            Sign Agreement
                        </button>
                    </form>

                @elseif($reservation->isAgreementSigned())
                    <div class="p-4 bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-xl flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#22C55E]/15 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#15803D]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#1F2937]">Agreement Signed</p>
                            <p class="text-xs text-[#64748B] mt-0.5">
                                Signed on {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}.
                            </p>
                        </div>
                    </div>

                    @if(!$hasPayment)
                        <form action="{{ route('payments.checkout', $reservation) }}" method="POST" class="mt-4">
                            @csrf
                            <div class="rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] p-4 flex flex-wrap items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-[#1F2937]">Initial Payment</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        &#8369;{{ number_format($reservation->unit->rental_fee, 2) }} via GCash — you will be redirected to complete payment.
                                    </p>
                                </div>
                                <button type="submit"
                                    class="shrink-0 px-5 py-2.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white font-bold text-sm shadow-sm cursor-pointer transition-all duration-200">
                                    Pay Now
                                </button>
                            </div>
                        </form>

                    @elseif($heldPayment)
                        <div class="mt-4 rounded-xl border border-[#2AA7A1]/25 bg-[#EEF8F8]/60 p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-[#2AA7A1]/15 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-[#156F8C]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-[#1F2937]">Payment received &mdash; held by AbangananHub</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Your payment of &#8369;{{ number_format($heldPayment->amount, 2) }} is held until you confirm move-in.
                                    </p>
                                </div>
                            </div>

                            @php
                                $daysLeft = $reservation->daysUntilMoveInDeadline();
                            @endphp

                            @if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->move_in_disputed_at)
                                @if ($reservation->isTurnoverClock())
                                    {{-- Clock 1: nothing for the tenant to do yet. No countdown — showing one
                                         here would imply a deadline the tenant can miss, and this one is the
                                         landlord's. --}}
                                    <div class="rounded-lg border border-[#2AA7A1]/25 bg-[#EEF8F8] p-4 mb-4">
                                        <p class="text-sm font-medium text-[#156F8C]">Payment secured</p>
                                        <p class="mt-1 text-sm text-[#1F2937]">
                                            Your deposit is held safely and is not released until you confirm your move-in.
                                            Your landlord will contact you to turn over the keys.
                                        </p>
                                    </div>
                                @elseif ($daysLeft !== null)
                                    {{-- Clock 2: live countdown. --}}
                                    <div class="rounded-lg border p-4 mb-4 {{ $daysLeft <= 1 ? 'border-[#EF4444]/25 bg-[#EF4444]/[0.07]' : 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10]' }}">
                                        <p class="text-sm font-semibold {{ $daysLeft <= 1 ? 'text-[#DC2626]' : 'text-[#B45309]' }}">
                                            @if ($daysLeft < 0)
                                                Your move-in confirmation window has passed
                                            @elseif ($daysLeft === 0)
                                                Today is your last day to confirm your move-in
                                            @else
                                                {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} left to confirm your move-in
                                            @endif
                                        </p>
                                        <p class="mt-1 text-sm {{ $daysLeft <= 1 ? 'text-[#DC2626]' : 'text-[#B45309]' }}">
                                            @if ($daysLeft < 0)
                                                Your deadline of {{ $reservation->move_in_deadline_at->format('M j, Y') }} has passed. Your deposit has not been released yet, but it will be released to the landlord automatically. You can still confirm your move-in now &mdash; if something isn't right, use "I haven't received the keys" below.
                                            @else
                                                Confirming releases your deposit to the landlord. If you don't confirm by
                                                {{ $reservation->move_in_deadline_at->format('M j, Y') }}, it is released automatically.
                                            @endif
                                        </p>
                                    </div>
                                @endif
                            @endif

                            @unless ($reservation->move_in_disputed_at)
                                <form action="{{ route('agreements.confirmMoveIn', $reservation) }}" method="POST"
                                    data-confirm="Confirm you have moved in?"
                                    data-confirm-type="warning"
                                    data-confirm-message="This releases your payment to the landlord and cannot be undone. Only confirm if you have physically moved in and verified the unit matches the listing."
                                    data-confirm-button="Yes, I have moved in">
                                    @csrf
                                    <div class="flex items-start gap-3 p-3 bg-[#EF4444]/10 border border-[#EF4444]/25 rounded-xl mb-4">
                                        <svg class="w-4 h-4 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                                        </svg>
                                        <p class="text-[12px] text-[#EF4444] leading-relaxed">
                                            Only confirm after you have physically moved into the unit and verified it matches the listing. Once confirmed, the payment will be released to the landlord.
                                        </p>
                                    </div>
                                    <button type="submit"
                                        class="w-full bg-[#FF8A65] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm cursor-pointer transition-all duration-200">
                                        I Have Moved In — Confirm Occupancy
                                    </button>
                                </form>
                            @endunless

                            @if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->move_in_disputed_at)
                                <div x-data="{ show: false }" class="mt-3 text-center">
                                    <button type="button" @click="show = true"
                                            class="text-xs text-[#64748B] underline hover:text-[#1F2937] cursor-pointer">
                                        I haven't received the keys
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="show" x-cloak
                                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-end="opacity-0">
                                            <div @click.outside="show = false"
                                                 class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl mx-4"
                                                 x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                                                 x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                                                 x-transition:leave="transition ease-in duration-200"
                                                 x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0">
                                                <h3 class="text-lg font-bold text-[#1F2937] text-left">Report a move-in issue</h3>
                                                <p class="mt-2 text-sm text-[#64748B] text-left">
                                                    Your deposit stays on hold and an administrator will review this. Your landlord will be notified.
                                                </p>

                                                <form action="{{ route('agreements.disputeMoveIn', $reservation) }}" method="POST" class="mt-4 text-left">
                                                    @csrf
                                                    <textarea name="reason" rows="4" required minlength="10"
                                                              class="w-full rounded-xl border-[#E2E8F0] text-sm"
                                                              placeholder="Tell us what happened — for example, the landlord hasn't turned over the keys."></textarea>
                                                    @error('reason')
                                                        <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p>
                                                    @enderror

                                                    <div class="mt-4 flex justify-end gap-2">
                                                        <button type="button" @click="show = false"
                                                                class="rounded-xl px-4 py-2 text-sm text-[#64748B] cursor-pointer">Cancel</button>
                                                        <button type="submit"
                                                                class="rounded-xl bg-[#FF8A65] hover:brightness-95 px-4 py-2 text-sm font-bold text-white cursor-pointer transition-all duration-200">
                                                            Submit report
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            @elseif ($reservation->move_in_disputed_at)
                                <div class="mt-3 rounded-xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4">
                                    <p class="text-sm font-bold text-[#B45309]">Move-in issue under review</p>
                                    <p class="mt-1 text-xs text-[#B45309]">
                                        Reported {{ $reservation->move_in_disputed_at->diffForHumans() }}. Your deposit is on hold
                                        and the countdown is paused while an administrator reviews this.
                                    </p>
                                </div>
                            @endif
                        </div>

                    @else
                        <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] p-4 flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#64748B] shrink-0 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-bold text-[#1F2937]">Payment processing</p>
                                <p class="text-xs text-[#64748B] mt-0.5">
                                    Your payment is being confirmed. This page updates automatically once it clears.
                                </p>
                            </div>
                        </div>
                    @endif

                @elseif($reservation->isOccupied())
                    <div class="p-4 bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-xl flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#22C55E]/15 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#15803D]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#1F2937]">You're all moved in</p>
                            <p class="text-xs text-[#64748B] mt-0.5">
                                Move-in confirmed on {{ $reservation->tenant_confirmed_move_in_at?->format('F j, Y \a\t g:i A') ?? 'N/A' }}.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </aside>

        </div>{{-- /document + rail --}}

        <p class="text-[10.5px] text-[#64748B] text-center mt-4 hidden print:block">
            {{ $agreementRef }} &middot; Generated {{ now()->format('F j, Y \a\t g:i A') }} &middot; AbangananHub
        </p>
    </div>

    @if($processingPayment)
        @push('scripts')
            <script>
                // The webhook lands out-of-band, so without this the tenant sits on
                // the spinner forever. A full reload is deliberate: this page has
                // five mutually exclusive server-rendered states and re-rendering
                // one of them in JS would duplicate the Blade.
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.Echo) return;
                    window.Echo.private('user.{{ auth()->id() }}')
                        .listen('.PaymentStatusUpdated', (e) => {
                            if (e.reservation_id === {{ $reservation->reservation_id }}) {
                                window.location.reload();
                            }
                        });
                });
            </script>
        @endpush
    @endif
@endsection
