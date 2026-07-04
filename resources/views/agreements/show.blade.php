@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">

        <a href="{{ route('reservations.index') }}"
            class="inline-flex items-center text-sm font-semibold text-[#61B2F0] hover:text-[#61B2F0]/80 transition mb-6">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Reservations
        </a>

        @if(session('success'))
            <div class="mb-6 bg-[#D7E8F3]/50 border border-[#61B2F0]/30 text-[#2A2523] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 bg-[#BD5434]/8 border border-[#BD5434]/30 text-[#BD5434] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="bg-white border border-[#9B9F98]/15 rounded-2xl shadow-sm p-8">
            <h1 class="text-2xl font-extrabold text-[#2A2523] tracking-tight mb-1">Rental Agreement</h1>
            <p class="text-sm text-[#9B9F98] mb-8">Please read the terms below carefully before signing.</p>

            <!-- Populated Template -->
            <div class="prose prose-sm max-w-none text-[#2A2523] leading-relaxed space-y-4 border border-[#9B9F98]/15 rounded-xl p-6 bg-[#F0EDE8]/30">
                <p>
                    This Rental Agreement is entered into between
                    <strong>{{ $reservation->property->landlord->first_name }}
                        {{ $reservation->property->landlord->last_name }}</strong>
                    ("Landlord") and
                    <strong>{{ $reservation->tenant->first_name }} {{ $reservation->tenant->last_name }}</strong>
                    ("Tenant"), concerning the rental of the property located at:
                </p>

                <p class="font-semibold text-[#2A2523]">{{ $reservation->property->address }}</p>

                <table class="w-full text-sm mt-4">
                    <tbody>
                        <tr class="border-b border-[#9B9F98]/15">
                            <td class="py-2 text-[#9B9F98]">Rental Fee</td>
                            <td class="py-2 font-semibold text-[#2A2523]">
                                ₱{{ number_format($reservation->unit->rental_fee, 2) }} / month</td>
                        </tr>
                        <tr class="border-b border-[#9B9F98]/15">
                            <td class="py-2 text-[#9B9F98]">Reservation Date</td>
                            <td class="py-2 font-semibold text-[#2A2523]">
                                {{ $reservation->reservation_date->format('F j, Y') }}</td>
                        </tr>
                        @if($reservation->target_move_in_date)
                            <tr class="border-b border-[#9B9F98]/15">
                                <td class="py-2 text-[#9B9F98]">Target Move-In</td>
                                <td class="py-2 font-semibold text-[#2A2523]">
                                    {{ $reservation->target_move_in_date->format('F j, Y') }}</td>
                            </tr>
                        @endif
                        @if($reservation->target_move_out_date)
                            <tr class="border-b border-[#9B9F98]/15">
                                <td class="py-2 text-[#9B9F98]">Target Move-Out</td>
                                <td class="py-2 font-semibold text-[#2A2523]">
                                    {{ $reservation->target_move_out_date->format('F j, Y') }}</td>
                            </tr>
                        @endif
                        @if($reservation->occupants_count)
                            <tr class="border-b border-[#9B9F98]/15">
                                <td class="py-2 text-[#9B9F98]">Occupants</td>
                                <td class="py-2 font-semibold text-[#2A2523]">{{ $reservation->occupants_count }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if($reservation->agreement_terms_notes)
                    <div class="mt-4">
                        <p class="text-[#9B9F98] text-xs font-bold uppercase tracking-wide mb-1">Additional Terms</p>
                        <p class="whitespace-pre-wrap text-[#2A2523]">{{ $reservation->agreement_terms_notes }}</p>
                    </div>
                @endif

                <p class="text-xs text-[#9B9F98] mt-6">
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
                    <div class="flex items-start gap-3 p-4 bg-[#BD5434]/8 border border-[#BD5434]/25 rounded-xl mb-6">
                        <svg class="w-5 h-5 text-[#BD5434] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                        </svg>
                        <p class="text-xs text-[#BD5434] leading-relaxed">
                            Please read the entire agreement above before proceeding. Signing is a binding acknowledgment
                            of the terms stated.
                        </p>
                    </div>

                    <!-- Checkbox consent -->
                    <label class="flex items-start gap-3 mb-6 cursor-pointer group">
                        <input type="checkbox" name="agree" required
                            class="mt-0.5 w-4 h-4 rounded border-[#9B9F98]/40 text-[#61B2F0] focus:ring-[#61B2F0] focus:ring-offset-0 transition">
                        <span class="text-sm text-[#2A2523] leading-relaxed group-hover:text-[#2A2523]/80 transition">
                            I have read and agree to the terms of this Rental Agreement.
                        </span>
                    </label>

                    <button type="submit"
                        class="w-full bg-[#61B2F0] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
                        Sign Agreement
                    </button>
                </form>

           @elseif($reservation->isAgreementSigned())
            <!-- Signed confirmation -->
            <div class="mt-8 p-4 bg-[#D7E8F3]/50 border border-[#61B2F0]/25 rounded-xl flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#61B2F0]/15 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#61B2F0]" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#2A2523]">Agreement Signed</p>
                    <p class="text-xs text-[#2A2523]/70 mt-0.5">
                        Signed on {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}.
                    </p>
                </div>
            </div>

            @if($reservation->payments->whereIn('status', ['Pending', 'Paid'])->isEmpty())
                <!-- Pay Now — no payment started yet -->
                <form action="{{ route('payments.checkout', $reservation) }}" method="POST" class="mt-4">
                    @csrf
                    <div class="rounded-xl border border-[#9B9F98]/15 bg-[#F0EDE8]/40 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-[#2A2523]">Initial Payment</p>
                            <p class="text-xs text-[#9B9F98] mt-0.5">
                                ₱{{ number_format($reservation->unit->rental_fee, 2) }} via GCash — you will be redirected to complete payment.
                            </p>
                        </div>
                        <button type="submit"
                            class="shrink-0 px-5 py-2.5 rounded-xl bg-[#61B2F0] hover:brightness-95 text-white font-bold text-sm shadow-sm transition">
                            Pay Now
                        </button>
                    </div>
                </form>
            @else
                <!-- Payment already initiated — waiting for confirmation -->
                <div class="mt-4 rounded-xl border border-[#9B9F98]/15 bg-[#F0EDE8]/40 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-[#9B9F98] shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-bold text-[#2A2523]">Payment Processing</p>
                        <p class="text-xs text-[#9B9F98] mt-0.5">
                            Your payment is being confirmed. This page will update once the payment is verified.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection