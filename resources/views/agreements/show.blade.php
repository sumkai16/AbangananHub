@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">

        <a href="{{ route('reservations.index') }}"
<<<<<<< HEAD
            class="inline-flex items-center text-sm font-semibold text-[#FF8A65] hover:text-[#FF8A65]/80 transition mb-6">
=======
            class="inline-flex items-center text-sm font-semibold text-[#156F8C] hover:text-[#156F8C]/80 transition mb-6">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Reservations
        </a>

        @if(session('success'))
<<<<<<< HEAD
            <div class="mb-6 bg-[#F7FCFC]/50 border border-[#FF8A65]/30 text-[#156F8C] rounded-xl px-4 py-3 text-[13px] font-medium">
=======
            <div class="mb-6 bg-[#EEF8F8]/50 border border-[#2AA7A1]/30 text-[#1F2937] rounded-xl px-4 py-3 text-[13px] font-medium">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
<<<<<<< HEAD
            <div class="mb-6 bg-[#DC2626]/8 border border-[#DC2626]/30 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">
=======
            <div class="mb-6 bg-[#EF4444]/8 border border-[#EF4444]/30 text-[#EF4444] rounded-xl px-4 py-3 text-[13px] font-medium">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                {{ $errors->first() }}
            </div>
        @endif

<<<<<<< HEAD
        <div class="bg-white border border-[#9B9F98]/15 rounded-2xl shadow-sm p-8">
            <h1 class="text-2xl font-extrabold text-[#156F8C] tracking-tight mb-1">Rental Agreement</h1>
            <p class="text-sm text-[#9B9F98] mb-8">Please read the terms below carefully before signing.</p>

            <!-- Populated Template -->
            <div class="prose prose-sm max-w-none text-[#156F8C] leading-relaxed space-y-4 border border-[#9B9F98]/15 rounded-xl p-6 bg-[#F7FCFC]/30">
=======
        <div class="bg-white border border-[#64748B]/15 rounded-2xl shadow-sm p-8">
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight mb-1">Rental Agreement</h1>
            <p class="text-sm text-[#64748B] mb-8">Please read the terms below carefully before signing.</p>

            <!-- Populated Template -->
            <div
                class="prose prose-sm max-w-none text-[#1F2937] leading-relaxed space-y-4 border border-[#64748B]/15 rounded-xl p-6 bg-[#E2E8F0]/30">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                <p>
                    This Rental Agreement is entered into between
                    <strong>{{ $reservation->property->landlord->first_name }}
                        {{ $reservation->property->landlord->last_name }}</strong>
                    ("Landlord") and
                    <strong>{{ $reservation->tenant->first_name }} {{ $reservation->tenant->last_name }}</strong>
                    ("Tenant"), concerning the rental of the property located at:
                </p>

<<<<<<< HEAD
                <p class="font-semibold text-[#156F8C]">{{ $reservation->property->address }}</p>

                <table class="w-full text-sm mt-4">
                    <tbody>
                        <tr class="border-b border-[#9B9F98]/15">
                            <td class="py-2 text-[#9B9F98]">Rental Fee</td>
                            <td class="py-2 font-semibold text-[#156F8C]">
                                ₱{{ number_format($reservation->unit->rental_fee, 2) }} / month</td>
                        </tr>
                        <tr class="border-b border-[#9B9F98]/15">
                            <td class="py-2 text-[#9B9F98]">Reservation Date</td>
                            <td class="py-2 font-semibold text-[#156F8C]">
                                {{ $reservation->reservation_date->format('F j, Y') }}</td>
                        </tr>
                        @if($reservation->target_move_in_date)
                            <tr class="border-b border-[#9B9F98]/15">
                                <td class="py-2 text-[#9B9F98]">Target Move-In</td>
                                <td class="py-2 font-semibold text-[#156F8C]">
                                    {{ $reservation->target_move_in_date->format('F j, Y') }}</td>
                            </tr>
                        @endif
                        @if($reservation->target_move_out_date)
                            <tr class="border-b border-[#9B9F98]/15">
                                <td class="py-2 text-[#9B9F98]">Target Move-Out</td>
                                <td class="py-2 font-semibold text-[#156F8C]">
                                    {{ $reservation->target_move_out_date->format('F j, Y') }}</td>
                            </tr>
                        @endif
                        @if($reservation->occupants_count)
                            <tr class="border-b border-[#9B9F98]/15">
                                <td class="py-2 text-[#9B9F98]">Occupants</td>
                                <td class="py-2 font-semibold text-[#156F8C]">{{ $reservation->occupants_count }}</td>
=======
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
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if($reservation->agreement_terms_notes)
                    <div class="mt-4">
<<<<<<< HEAD
                        <p class="text-[#9B9F98] text-xs font-bold uppercase tracking-wide mb-1">Additional Terms</p>
                        <p class="whitespace-pre-wrap text-[#156F8C]">{{ $reservation->agreement_terms_notes }}</p>
=======
                        <p class="text-[#64748B] text-xs font-bold uppercase tracking-wide mb-1">Additional Terms</p>
                        <p class="whitespace-pre-wrap text-[#1F2937]">{{ $reservation->agreement_terms_notes }}</p>
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
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
<<<<<<< HEAD
                    <div class="flex items-start gap-3 p-4 bg-[#DC2626]/8 border border-[#DC2626]/25 rounded-xl mb-6">
                        <svg class="w-5 h-5 text-[#DC2626] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
=======
                    <div class="flex items-start gap-3 p-4 bg-[#EF4444]/8 border border-[#EF4444]/25 rounded-xl mb-6">
                        <svg class="w-5 h-5 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                        </svg>
<<<<<<< HEAD
                        <p class="text-xs text-[#DC2626] leading-relaxed">
=======
                        <p class="text-xs text-[#EF4444] leading-relaxed">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            Please read the entire agreement above before proceeding. Signing is a binding acknowledgment
                            of the terms stated.
                        </p>
                    </div>

                    <!-- Checkbox consent -->
                    <label class="flex items-start gap-3 mb-6 cursor-pointer group">
                        <input type="checkbox" name="agree" required
<<<<<<< HEAD
                            class="mt-0.5 w-4 h-4 rounded border-[#9B9F98]/40 text-[#FF8A65] focus:ring-[#FF8A65] focus:ring-offset-0 transition">
                        <span class="text-sm text-[#156F8C] leading-relaxed group-hover:text-[#156F8C]/80 transition">
=======
                            class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                        <span class="text-sm text-[#1F2937] leading-relaxed group-hover:text-[#1F2937]/80 transition">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            I have read and agree to the terms of this Rental Agreement.
                        </span>
                    </label>

                    <button type="submit"
<<<<<<< HEAD
                        class="w-full bg-[#FF8A65] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
=======
                        class="w-full bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                        Sign Agreement
                    </button>
                </form>

           @elseif($reservation->isAgreementSigned())
            <!-- Signed confirmation -->
<<<<<<< HEAD
            <div class="mt-8 p-4 bg-[#F7FCFC]/50 border border-[#FF8A65]/25 rounded-xl flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#FF8A65]/15 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#FF8A65]" fill="none" stroke="currentColor" stroke-width="2.5"
=======
            <div class="mt-8 p-4 bg-[#EEF8F8]/50 border border-[#2AA7A1]/25 rounded-xl flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#2AA7A1]/15 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" stroke="currentColor" stroke-width="2.5"
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <div>
<<<<<<< HEAD
                    <p class="text-sm font-bold text-[#156F8C]">Agreement Signed</p>
                    <p class="text-xs text-[#156F8C]/70 mt-0.5">
=======
                    <p class="text-sm font-bold text-[#1F2937]">Agreement Signed</p>
                    <p class="text-xs text-[#1F2937]/70 mt-0.5">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                        Signed on {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}.
                    </p>
                </div>
            </div>

            @if($reservation->payments->whereIn('status', ['Pending', 'Paid'])->isEmpty())
                <!-- Pay Now — no payment started yet -->
                <form action="{{ route('payments.checkout', $reservation) }}" method="POST" class="mt-4">
                    @csrf
<<<<<<< HEAD
                    <div class="rounded-xl border border-[#9B9F98]/15 bg-[#F7FCFC]/40 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-[#156F8C]">Initial Payment</p>
                            <p class="text-xs text-[#9B9F98] mt-0.5">
=======
                    <div class="rounded-xl border border-[#64748B]/15 bg-[#E2E8F0]/40 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-[#1F2937]">Initial Payment</p>
                            <p class="text-xs text-[#64748B] mt-0.5">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                                ₱{{ number_format($reservation->unit->rental_fee, 2) }} via GCash — you will be redirected to complete payment.
                            </p>
                        </div>
                        <button type="submit"
<<<<<<< HEAD
                            class="shrink-0 px-5 py-2.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white font-bold text-sm shadow-sm transition">
=======
                            class="shrink-0 px-5 py-2.5 rounded-xl bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-sm shadow-sm transition">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            Pay Now
                        </button>
                    </div>
                </form>
            @else
                <!-- Payment already initiated — waiting for confirmation -->
<<<<<<< HEAD
                <div class="mt-4 rounded-xl border border-[#9B9F98]/15 bg-[#F7FCFC]/40 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-[#9B9F98] shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
=======
                <div class="mt-4 rounded-xl border border-[#64748B]/15 bg-[#E2E8F0]/40 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-[#64748B] shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <div>
<<<<<<< HEAD
                        <p class="text-sm font-bold text-[#156F8C]">Payment Processing</p>
                        <p class="text-xs text-[#9B9F98] mt-0.5">
=======
                        <p class="text-sm font-bold text-[#1F2937]">Payment Processing</p>
                        <p class="text-xs text-[#64748B] mt-0.5">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            Your payment is being confirmed. This page will update once the payment is verified.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection