@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-[900px] mx-auto px-6 py-10">

        <div class="mb-8">
            <h1 class="text-[24px] font-extrabold text-[#1A1A2E] mb-1">Reservation Requests</h1>
            <p class="text-gray-500">Approve or reject reservations for your listings.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-800 text-[14px] font-medium border border-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        @if($reservations->isEmpty())
            <x-empty-state title="No reservation requests"
                message="Once a tenant reserves one of your properties, it will show up here."
                href="{{ route('landlord.listings.index') }}" cta="View my listings" />
        @else
            <div class="flex flex-col gap-4">
                @foreach($reservations as $reservation)
                    <div class="border border-gray-200 rounded-2xl p-5 bg-white">

                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div>
                                <a href="{{ route('properties.show', $reservation->property) }}"
                                    class="text-[15px] font-bold text-[#1A1A2E] hover:text-[#286CD2] transition-colors">
                                    {{ $reservation->property->title }}
                                </a>
                                <p class="text-[12.5px] text-gray-500 mt-0.5">{{ $reservation->property->address }}</p>
                            </div>

                            @php
                                $badgeClasses = match($reservation->reservation_status) {
                                    'Pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'Approved' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    'Rejected' => 'bg-red-50 text-red-800 border-red-200',
                                    'Cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
                                    default => 'bg-gray-100 text-gray-500 border-gray-200',
                                };
                            @endphp
                            <span
                                class="flex-shrink-0 text-[11.5px] font-bold px-3 py-1 rounded-full border {{ $badgeClasses }}">
                                {{ $reservation->reservation_status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3 text-[13px]">
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Tenant</span>
                                <span class="text-gray-700">
                                    {{ trim($reservation->tenant->first_name . ' ' . $reservation->tenant->last_name) }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Contact</span>
                                <span class="text-gray-700">{{ $reservation->tenant->contact_number ?? 'Not provided' }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Move-in date</span>
                                <span class="text-gray-700">{{ $reservation->reservation_date->format('M d, Y') }}</span>
                            </div>
                        </div>

                        @if($reservation->remarks)
                            <p class="text-[13px] text-gray-600 bg-gray-50 rounded-lg px-3 py-2 mb-3">
                                "{{ $reservation->remarks }}"
                            </p>
                        @endif

                        @if($reservation->isPending())
                            <div class="flex gap-2 mt-2">
                                <form action="{{ route('landlord.reservations.approve', $reservation) }}" method="POST"
                                    class="flex-1">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="w-full text-center bg-[#286CD2] hover:bg-[#1a57b0] text-white text-[13.5px] font-bold py-2.5 rounded-lg transition-colors">
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST"
                                    class="flex-1"
                                    onsubmit="return confirm('Reject this reservation request?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="w-full text-center bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-[13.5px] font-bold py-2.5 rounded-lg transition-colors">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $reservations->links() }}
            </div>
        @endif

    </div>
@endsection