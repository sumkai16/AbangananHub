@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-[900px] mx-auto px-6 py-10">

        <div class="mb-8">
            <h1 class="text-[24px] font-extrabold text-[#1A1A2E] mb-1">Reservation Requests</h1>
            <p class="text-gray-500">Approve or reject reservations for your listings.</p>
        </div>

        @if (session('success'))
            <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 text-[14px] font-medium border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif

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
                    @continue(!$reservation->property)
                    @php
                        $statusStyles = match ($reservation->reservation_status) {
                            'Pending' => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            'Approved' => ['badge' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'icon' => 'm9 12.75 2.25 2.25 6-6m4.5 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            'Rejected' => ['badge' => 'bg-red-50 text-red-800 border-red-200', 'icon' => 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            'Cancelled' => ['badge' => 'bg-gray-100 text-gray-500 border-gray-200', 'icon' => 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            default => ['badge' => 'bg-gray-100 text-gray-500 border-gray-200', 'icon' => 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        };
                        $thumbnail = $reservation->property->media->firstWhere('media_type', 'Image');
                    @endphp

                    <div class="flex gap-4 border border-gray-200 rounded-2xl p-4 bg-white">

                        <a href="{{ route('properties.show', $reservation->property) }}"
                            class="flex-shrink-0 w-24 h-24 sm:w-32 sm:h-32 rounded-xl overflow-hidden bg-gray-100">
                            @if($thumbnail)
                                <img src="{{ $thumbnail->media_url }}" alt="{{ $reservation->property->title }}"
                                    class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-8 h-8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </div>
                            @endif
                        </a>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                <div class="min-w-0">
                                    <a href="{{ route('properties.show', $reservation->property) }}"
                                        class="text-[15px] font-bold text-[#1A1A2E] hover:text-[#286CD2] transition-colors truncate block">
                                        {{ $reservation->property->title }}
                                    </a>
                                    <p class="flex items-center gap-1 text-[12.5px] text-gray-500 mt-0.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                            stroke="currentColor" class="w-3.5 h-3.5 flex-shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        <span class="truncate">{{ $reservation->property->address }}</span>
                                    </p>
                                </div>
                                <span
                                    class="flex-shrink-0 inline-flex items-center gap-1 text-[11.5px] font-bold px-3 py-1 rounded-full border {{ $statusStyles['badge'] }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $statusStyles['icon'] }}" />
                                    </svg>
                                    {{ $reservation->reservation_status }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-[13px] text-gray-600 mb-3">
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-4 h-4 text-gray-400">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    {{ $reservation->reservation_date->format('M d, Y') }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-4 h-4 text-gray-400">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    {{ trim($reservation->tenant->first_name . ' ' . $reservation->tenant->last_name) }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-4 h-4 text-gray-400">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                    </svg>
                                    {{ $reservation->tenant->contact_number ?? 'Not provided' }}
                                </span>
                            </div>

                            @if($reservation->remarks)
                                <p class="text-[13px] text-gray-600 bg-gray-50 rounded-lg px-3 py-2 mb-3">
                                    "{{ $reservation->remarks }}"
                                </p>
                            @endif

                            @if($reservation->isPending())
                                <div class="flex justify-end gap-2">
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST"
                                        onsubmit="return confirm('Reject this reservation request?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="px-5 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-700 text-[13px] font-bold transition-colors">
                                            Reject
                                        </button>
                                    </form>
                                    <form action="{{ route('landlord.reservations.approve', $reservation) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="px-5 py-2 rounded-lg bg-[#286CD2] hover:bg-[#1a57b0] text-white text-[13px] font-bold transition-colors">
                                            Approve
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $reservations->links() }}
            </div>
        @endif

    </div>
@endsection