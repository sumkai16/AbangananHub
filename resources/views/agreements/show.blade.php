@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">

        <a href="{{ route('reservations.index') }}"
            class="inline-flex items-center text-sm font-semibold text-[#156F8C] hover:text-[#156F8C]/80 transition mb-6">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Reservations
        </a>

        @if(session('success'))
            <div
                class="mb-6 bg-[#EEF8F8]/50 border border-[#2AA7A1]/30 text-[#1F2937] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div
                class="mb-6 bg-[#EF4444]/8 border border-[#EF4444]/30 text-[#EF4444] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5 sm:p-8">
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight mb-1">Rental Agreement</h1>
            <p class="text-sm text-[#64748B] mb-8">Please read the terms below carefully before signing.</p>

            <!-- Populated Template -->
            <div
                class="prose prose-sm max-w-none text-[#1F2937] leading-relaxed space-y-4 border border-[#64748B]/15 rounded-xl p-6 bg-[#E2E8F0]/30">
                <p>
                    This Rental Agreement is entered into between
                    <strong>{{ $reservation->property->landlord->first_name }}
                        {{ $reservation->property->landlord->last_name }}</strong>
                    ("Landlord") and
                    <strong>{{ $reservation->tenant->first_name }} {{ $reservation->tenant->last_name }}</strong>
                    ("Tenant"), concerning the rental of the property located at:
                </p>

                <p class="font-semibold text-[#1F2937]">{{ $reservation->property->address }}</p>

                <table class="w-full text-sm mt-4">
                    <tbody>
                        <tr class="border-b border-[#64748B]/15">
                            <td class="py-2 text-[#64748B]">Rental Fee</td>
                            <td class="py-2 font-semibold text-[#1F2937]">
                                ₱{{ number_format($reservation->unit->rental_fee, 2) }} / month</td>
                        </tr>
                        <tr class="border-b border-[#64748B]/15">
                            <td class="py-2 text-[#64748B]">Reservation Date</td>
                            <td class="py-2 font-semibold text-[#1F2937]">
                                {{ $reservation->reservation_date->format('F j, Y') }}
                            </td>
                        </tr>
                        @if($reservation->target_move_in_date)
                            <tr class="border-b border-[#64748B]/15">
                                <td class="py-2 text-[#64748B]">Target Move-In</td>
                                <td class="py-2 font-semibold text-[#1F2937]">
                                    {{ $reservation->target_move_in_date->format('F j, Y') }}
                                </td>
                            </tr>
                        @endif
                        @if($reservation->target_move_out_date)
                            <tr class="border-b border-[#64748B]/15">
                                <td class="py-2 text-[#64748B]">Target Move-Out</td>
                                <td class="py-2 font-semibold text-[#1F2937]">
                                    {{ $reservation->target_move_out_date->format('F j, Y') }}
                                </td>
                            </tr>
                        @endif
                        @if($reservation->occupants_count)
                            <tr class="border-b border-[#64748B]/15">
                                <td class="py-2 text-[#64748B]">Occupants</td>
                                <td class="py-2 font-semibold text-[#1F2937]">{{ $reservation->occupants_count }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if($reservation->agreement_terms_notes)
                    <div class="mt-4">
                        <p class="text-[#64748B] text-xs font-bold uppercase tracking-wide mb-1">Additional Terms</p>
                        <p class="whitespace-pre-wrap text-[#1F2937]">{{ $reservation->agreement_terms_notes }}</p>
                    </div>
                @endif

                <p class="text-xs text-[#64748B] mt-6">
                    By signing this agreement, both parties acknowledge the terms above as the basis for this rental
                    arrangement. AbangananHub facilitates this agreement as a record-keeping tool between Landlord and
                    Tenant and is not a party to, nor liable for, the terms herein.
                </p>
            </div>

            <!-- Sign Section -->
            @if($reservation->rental_status === 'Pending Rental Agreement')
                <form action="{{ route('agreements.sign', $reservation) }}" method="POST" class="mt-8">
                    @csrf

                    <!-- Warning banner -->
                    <div class="flex items-start gap-3 p-4 bg-[#EF4444]/8 border border-[#EF4444]/25 rounded-xl mb-6">
                        <svg class="w-5 h-5 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                        </svg>
                        <p class="text-xs text-[#EF4444] leading-relaxed">
                            Please read the entire agreement above before proceeding. Signing is a binding acknowledgment
                            of the terms stated.
                        </p>
                    </div>

                    <!-- Checkbox consent -->
                    <label class="flex items-start gap-3 mb-6 cursor-pointer group">
                        <input type="checkbox" name="agree" required
                            class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                        <span class="text-sm text-[#1F2937] leading-relaxed group-hover:text-[#1F2937]/80 transition">
                            I have read and agree to the terms of this Rental Agreement.
                        </span>
                    </label>
                    <!-- Platform T&C consent -->
                    <label class="flex items-start gap-3 mb-6 cursor-pointer group">
                        <input type="checkbox" name="accept_tc" required
                            class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                        <span class="text-sm text-[#1F2937] leading-relaxed group-hover:text-[#1F2937]/80 transition">
                            I understand that my payment will be held by AbangananHub until I confirm move-in. Funds will only
                            be released to the landlord after I verify that the unit matches the listing.
                        </span>
                    </label>
                    <button type="submit"
                        class="w-full bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
                        Sign Agreement
                    </button>
                </form>

            @elseif($reservation->isAgreementSigned())
                <!-- Signed confirmation -->
                <div class="mt-8 p-4 bg-[#EEF8F8]/50 border border-[#2AA7A1]/25 rounded-xl flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#2AA7A1]/15 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-[#1F2937]">Agreement Signed</p>
                        <p class="text-xs text-[#1F2937]/70 mt-0.5">
                            Signed on {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}.
                        </p>
                    </div>
                </div>

@if($reservation->payments->whereIn('status', ['Pending', 'Held', 'Paid', 'Released'])->isEmpty())
                    <!-- Pay Now — no payment started yet -->
                    <form action="{{ route('payments.checkout', $reservation) }}" method="POST" class="mt-4">
                        @csrf
                        <div class="rounded-xl border border-[#64748B]/15 bg-[#E2E8F0]/40 p-4 flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-[#1F2937]">Initial Payment</p>
                                <p class="text-xs text-[#64748B] mt-0.5">
                                    ₱{{ number_format($reservation->unit->rental_fee, 2) }} via GCash — you will be redirected to complete payment.
                                </p>
                            </div>
                            <button type="submit"
                                class="shrink-0 px-5 py-2.5 rounded-xl bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-sm shadow-sm transition">
                                Pay Now
                            </button>
                        </div>
                    </form>
                @else
                    @php $heldPayment = $reservation->payments->where('status', 'Held')->first(); @endphp

                    @if($heldPayment)
                        <!-- Payment held — waiting for move-in confirmation -->
                        <div class="mt-4 rounded-xl border border-[#2AA7A1]/25 bg-[#EEF8F8]/50 p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-[#2AA7A1]/15 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-[#1F2937]">Payment Received &mdash; held by AbangananHub</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Your payment of ₱{{ number_format($heldPayment->amount, 2) }} is being held by AbangananHub until you confirm move-in.
                                    </p>
                                </div>
                            </div>

                            <form action="{{ route('agreements.confirmMoveIn', $reservation) }}" method="POST">
                                @csrf
                                <div class="flex items-start gap-3 p-3 bg-[#EF4444]/8 border border-[#EF4444]/25 rounded-xl mb-4">
                                    <svg class="w-4 h-4 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                                    </svg>
                                    <p class="text-xs text-[#EF4444] leading-relaxed">
                                        Only confirm after you have physically moved into the unit and verified it matches the listing. Once confirmed, the payment will be released to the landlord.
                                    </p>
                                </div>
                                <button type="submit"
                                    class="w-full bg-[#FF8A65] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm transition"
                                    onclick="return confirm('Are you sure you have moved in? This will release the payment to the landlord.')">
                                    I Have Moved In — Confirm Occupancy
                                </button>
                            </form>
                        </div>
                    @else
                        <!-- Payment processing (webhook hasn't fired yet) -->
                        <div class="mt-4 rounded-xl border border-[#64748B]/15 bg-[#E2E8F0]/40 p-4 flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#64748B] shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-bold text-[#1F2937]">Payment Processing</p>
                                <p class="text-xs text-[#64748B] mt-0.5">
                                    Your payment is being confirmed. This page will update once the payment is verified.
                                </p>
                            </div>
                        </div>
                    @endif
                @endif

            @elseif($reservation->isOccupied())
                <!-- Occupied state -->
                <div class="mt-8 p-4 bg-[#EEF8F8]/50 border border-[#2AA7A1]/25 rounded-xl flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#22C55E]/15 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-[#22C55E]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-[#1F2937]">You're All Moved In</p>
                        <p class="text-xs text-[#64748B] mt-0.5">
                            Move-in confirmed on {{ $reservation->tenant_confirmed_move_in_at?->format('F j, Y \a\t g:i A') ?? 'N/A' }}.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection