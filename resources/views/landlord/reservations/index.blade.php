{{-- resources/views/landlord/reservations/index.blade.php --}}
@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-4xl mx-auto px-6 py-10 pb-16">

        <x-section-header title="Reservation Requests" sub="Approve or reject reservations for your listings." />

        @if (session('success'))
            <div
                class="mb-6 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-800 text-[14px] font-medium border border-emerald-100">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 text-red-800 text-[14px] font-medium border border-red-100">
                {{ $errors->first() }}
            </div>
        @endif

        @if($reservations->isEmpty())
            <x-empty-state title="No reservation requests"
                message="Once a tenant reserves one of your properties, it will show up here."
                href="{{ route('landlord.listings.index') }}" cta="View my listings">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </x-slot>
            </x-empty-state>
        @else
            <div class="flex flex-col gap-5">
                @foreach($reservations as $reservation)
                    @continue(!$reservation->property)
                    @php
                        $statusStyles = match ($reservation->reservation_status) {
                            'Pending' => ['badge' => 'bg-amber-50 text-amber-700 border-amber-100', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            'Approved' => ['badge' => 'bg-emerald-50 text-emerald-800 border-emerald-100', 'icon' => 'm9 12.75 2.25 2.25 6-6m4.5 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            'Rejected' => ['badge' => 'bg-red-50 text-red-800 border-red-100', 'icon' => 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            'Cancelled' => ['badge' => 'bg-gray-50 text-gray-500 border-gray-200', 'icon' => 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            default => ['badge' => 'bg-gray-50 text-gray-500 border-gray-200', 'icon' => 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        };
                        $thumbnail = $reservation->property->media->firstWhere('media_type', 'Image');
                    @endphp

                    <div class="group flex flex-col sm:flex-row gap-5 border border-gray-100 rounded-3xl p-5 bg-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">

                        <a href="{{ route('properties.show', $reservation->property) }}"
                            class="flex-shrink-0 w-full sm:w-36 h-36 rounded-2xl overflow-hidden bg-gray-150 relative block">
                            @if($thumbnail)
                                <img src="{{ $thumbnail->media_url }}" alt="{{ $reservation->property->title }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-[#EBF3FF] text-[#286CD2]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-10 h-10">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </div>
                            @endif
                        </a>

                        <div class="flex-1 min-w-0 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                    <div class="min-w-0">
                                        <a href="{{ route('properties.show', $reservation->property) }}"
                                            class="text-[16px] font-bold text-[#1A1A2E] hover:text-[#286CD2] transition-colors truncate block">
                                            {{ $reservation->property->title }}
                                        </a>
                                        <p class="flex items-center gap-1.5 text-[13px] text-gray-400 mt-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor" class="w-3.5 h-3.5 flex-shrink-0">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                            </svg>
                                            <span class="truncate">{{ $reservation->property->address }}</span>
                                        </p>
                                    </div>
                                    <span
                                        class="flex-shrink-0 inline-flex items-center gap-1 text-[11.5px] font-bold px-3 py-1 rounded-full border {{ $statusStyles['badge'] }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                            stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $statusStyles['icon'] }}" />
                                        </svg>
                                        {{ $reservation->reservation_status }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-x-5 gap-y-2 text-[13.5px] text-gray-600 my-3">
                                    <span class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                            stroke="currentColor" class="w-4 h-4 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                        {{ $reservation->reservation_date->format('M d, Y') }}
                                    </span>
                                    <span class="flex items-center gap-1.5 font-medium text-gray-700">
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
                                    <div class="text-[13px] text-gray-600 bg-gray-50 rounded-xl px-4 py-3 mb-3 border border-gray-100 italic">
                                        "{{ $reservation->remarks }}"
                                    </div>
                                @endif
                            </div>

                            @if($reservation->isPending())
                                <div class="flex justify-end items-center gap-3 mt-4">
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST"
                                        onsubmit="return confirm('Reject this reservation request?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="h-10 px-5 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-700 text-[13px] font-bold transition-all">
                                            Reject
                                        </button>
                                    </form>
                                    <form action="{{ route('landlord.reservations.approve', $reservation) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="h-10 px-5 rounded-xl bg-[#286CD2] hover:bg-[#1e5bb8] text-white text-[13px] font-bold shadow-sm hover:shadow transition-all">
                                            Approve
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                {{ $reservations->links() }}
            </div>
        @endif

    </div>
@endsection