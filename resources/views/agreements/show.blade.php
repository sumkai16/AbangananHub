@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">

        <a href="{{ route('reservations.index') }}"
            class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition mb-6">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Reservations
        </a>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-100 rounded-xl text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-sm font-semibold text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-8">
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight mb-1">Rental Agreement</h1>
            <p class="text-sm text-gray-500 mb-8">Please read the terms below carefully before signing.</p>

            <!-- Populated Template -->
            <div
                class="prose prose-sm max-w-none text-gray-800 leading-relaxed space-y-4 border border-gray-100 rounded-xl p-6 bg-gray-50/50">
                <p>
                    This Rental Agreement is entered into between
                    <strong>{{ $reservation->property->landlord->first_name }}
                        {{ $reservation->property->landlord->last_name }}</strong>
                    ("Landlord") and
                    <strong>{{ $reservation->tenant->first_name }} {{ $reservation->tenant->last_name }}</strong>
                    ("Tenant"), concerning the rental of the property located at:
                </p>

                <p class="font-semibold">{{ $reservation->property->address }}</p>

                <table class="w-full text-sm mt-4">
                    <tbody>
                        <tr class="border-b border-gray-200">
                            <td class="py-2 text-gray-500">Rental Fee</td>
                            <td class="py-2 font-semibold text-gray-900">
                                ₱{{ number_format($reservation->property->rental_fee, 2) }} / month</td>
                        </tr>
                        <tr class="border-b border-gray-200">
                            <td class="py-2 text-gray-500">Reservation Date</td>
                            <td class="py-2 font-semibold text-gray-900">
                                {{ $reservation->reservation_date->format('F j, Y') }}</td>
                        </tr>
                        @if($reservation->target_move_in_date)
                            <tr class="border-b border-gray-200">
                                <td class="py-2 text-gray-500">Target Move-In</td>
                                <td class="py-2 font-semibold text-gray-900">
                                    {{ $reservation->target_move_in_date->format('F j, Y') }}</td>
                            </tr>
                        @endif
                        @if($reservation->target_move_out_date)
                            <tr class="border-b border-gray-200">
                                <td class="py-2 text-gray-500">Target Move-Out</td>
                                <td class="py-2 font-semibold text-gray-900">
                                    {{ $reservation->target_move_out_date->format('F j, Y') }}</td>
                            </tr>
                        @endif
                        @if($reservation->occupants_count)
                            <tr class="border-b border-gray-200">
                                <td class="py-2 text-gray-500">Occupants</td>
                                <td class="py-2 font-semibold text-gray-900">{{ $reservation->occupants_count }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if($reservation->agreement_terms_notes)
                    <div class="mt-4">
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wide mb-1">Additional Terms</p>
                        <p class="whitespace-pre-wrap">{{ $reservation->agreement_terms_notes }}</p>
                    </div>
                @endif

                <p class="text-xs text-gray-400 mt-6">
                    By signing this agreement, both parties acknowledge the terms above as the basis for this rental
                    arrangement. AbangananHub facilitates this agreement as a record-keeping tool between Landlord and
                    Tenant and is not a party to, nor liable for, the terms herein.
                </p>
            </div>

            <!-- Sign Section -->
            @if($reservation->rental_status === 'Pending Rental Agreement')
                <form action="{{ route('agreements.sign', $reservation) }}" method="POST" class="mt-8">
                    @csrf
                    <div class="flex items-start gap-3 p-4 bg-yellow-50 border border-yellow-100 rounded-xl mb-6">
                        <svg class="w-5 h-5 text-yellow-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                        </svg>
                        <p class="text-xs text-yellow-800 leading-relaxed">
                            Please read the entire agreement above before proceeding. Signing is a binding acknowledgment
                            of the terms stated.
                        </p>
                    </div>

                    <label class="flex items-start gap-3 mb-6 cursor-pointer">
                        <input type="checkbox" name="agree" required
                            class="mt-1 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">
                            I have read and agree to the terms of this Rental Agreement.
                        </span>
                    </label>

                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
                        Sign Agreement
                    </button>
                </form>
            @elseif($reservation->isAgreementSigned())
                <div class="mt-8 p-4 bg-green-50 border border-green-100 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <p class="text-sm font-semibold text-green-700">
                        Signed on {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}.
                    </p>
                </div>

                {{-- Pay Now button placeholder — wired once the Payments controller/route exists --}}
                <button type="button" disabled
                    class="w-full mt-4 bg-gray-200 text-gray-400 font-bold text-sm py-3 rounded-xl cursor-not-allowed">
                    Pay Now (coming soon)
                </button>
            @endif
        </div>
    </div>
@endsection