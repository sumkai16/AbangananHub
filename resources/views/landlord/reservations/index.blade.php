{{-- resources/views/landlord/reservations/index.blade.php --}}
@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 pb-16" x-data="{ rejectModalOpen: false, activeReservationId: null }">

        {{-- Minimal Navigation Header --}}
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Reservation Requests</h1>
                <p class="text-[14px] text-gray-500 mt-0.5">Approve or reject reservations for your listings.</p>
            </div>
            <span class="text-[13px] font-semibold text-gray-400 bg-gray-50 border border-gray-200/60 px-3 py-1.5 rounded-xl">
                {{ $reservations->total() }} Applications
            </span>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-6 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-800 text-[14px] font-medium border border-emerald-100 flex items-center gap-2">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 text-red-800 text-[14px] font-medium border border-red-100">
                {{ session('error') ?? $errors->first() }}
            </div>
        @endif

        @if($reservations->isEmpty())
            <x-empty-state title="No reservation requests"
                message="Once a tenant reserves one of your properties, it will show up here."
                href="{{ route('landlord.listings.index') }}" cta="View my listings">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </x-slot>
            </x-empty-state>
        @else
            <div class="space-y-12">
                @foreach($reservations as $reservation)
                    @continue(!$reservation->property)
                    @php
                        $currentStatus = $reservation->status ?? $reservation->reservation_status;
                        $statusBadgeStyles = match ($currentStatus) {
                            'Pending' => 'bg-amber-50 text-amber-700 border-amber-100',
                            'Approved' => 'bg-emerald-50 text-emerald-800 border-emerald-100',
                            'Rejected' => 'bg-red-50 text-red-800 border-red-100',
                            default => 'bg-gray-50 text-gray-500 border-gray-200',
                        };
                        $thumbnail = $reservation->property->media->firstWhere('media_type', 'Image') ?? $reservation->property->media->first();
                        $resId = $reservation->id ?? $reservation->reservation_id;
                    @endphp

                    {{-- Premium Booking-Inspired Section Block --}}
                    <div class="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-300">
                        
                        {{-- Top Layer: Image & Real-Time Data Metrics Layout --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6 h-auto md:h-56">
                            {{-- Large Main Photo Asset --}}
                            <div class="md:col-span-2 relative rounded-2xl overflow-hidden bg-gray-100 border border-gray-150 h-48 md:h-full">
                                @if($thumbnail)
                                    <img src="{{ $thumbnail->media_url }}" alt="{{ $reservation->property->title }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-blue-50 text-[#286CD2]">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                                    </div>
                                @endif
                                <a href="{{ route('properties.show', $reservation->property) }}" class="absolute inset-0 bg-black/0 hover:bg-black/5 transition-colors"></a>
                            </div>

                            {{-- Replaced Placeholder text panels with functional info panels --}}
                            <div class="hidden md:flex flex-col gap-3 h-full">
                                {{-- Metric Box 1: Listing Financial Rate --}}
                                <div class="flex-1 rounded-2xl bg-gray-50/70 border border-gray-150 p-4 flex flex-col justify-center">
                                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Property Rate</span>
                                    <span class="text-[20px] font-black text-gray-900 mt-0.5">
                                        ₱{{ number_format($reservation->property->price ?? 0) }}<span class="text-[12px] font-normal text-gray-400">/mo</span>
                                    </span>
                                </div>
                                {{-- Metric Box 2: Time Context --}}
                                <div class="flex-1 rounded-2xl bg-gray-50/70 border border-gray-150 p-4 flex flex-col justify-center">
                                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Timeline</span>
                                    <span class="text-[13.5px] font-semibold text-gray-700 mt-1 flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-gray-400"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        {{ $reservation->created_at ? $reservation->created_at->diffForHumans() : 'Submitted recently' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Middle Layer: Identity Headers --}}
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b border-gray-150 pb-5 mb-5">
                            <div>
                                <a href="{{ route('properties.show', $reservation->property) }}" class="text-xl font-bold text-gray-900 hover:text-[#286CD2] transition-colors tracking-tight">
                                    {{ $property->title ?? $reservation->property->title }}
                                </a>
                                <p class="text-[13px] text-gray-400 font-medium mt-1 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 flex-shrink-0 text-gray-300"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                    {{ $reservation->property->address }}
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-2 self-start sm:self-auto">
                                <span class="inline-flex items-center gap-1.5 text-[12px] font-bold px-3 py-1 rounded-full border {{ $statusBadgeStyles }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $currentStatus }}
                                </span>
                            </div>
                        </div>

                        {{-- Lower Layer: Details Organized like Property Features / Amenities --}}
                        <div class="mb-6">
                            <h3 class="text-[13px] font-bold uppercase tracking-wider text-gray-400 mb-4">Application Parameters</h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-8">
                                {{-- Move-in Date feature --}}
                                <div class="flex items-center gap-3 text-[14px]">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    <span class="text-gray-500">Move-in:</span>
                                    <span class="font-semibold text-gray-900 ml-auto sm:ml-0">{{ is_string($reservation->reservation_date) ? \Carbon\Carbon::parse($reservation->reservation_date)->format('M d, Y') : $reservation->reservation_date->format('M d, Y') }}</span>
                                </div>

                                {{-- Duration of Stay feature --}}
                                <div class="flex items-center gap-3 text-[14px]">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    <span class="text-gray-500">Stay Duration:</span>
                                    <span class="font-semibold text-gray-900 ml-auto sm:ml-0">{{ $reservation->duration_of_stay ?? 'Not Specified' }}</span>
                                </div>

                                {{-- Tenant Name feature --}}
                                <div class="flex items-center gap-3 text-[14px]">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    <span class="text-gray-500">Applicant:</span>
                                    <span class="font-semibold text-gray-900 ml-auto sm:ml-0">{{ trim(($reservation->tenant->first_name ?? '') . ' ' . ($reservation->tenant->last_name ?? '')) }}</span>
                                </div>

                                {{-- Occupants count feature --}}
                                <div class="flex items-center gap-3 text-[14px]">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477" /></svg>
                                    <span class="text-gray-500">Occupants:</span>
                                    <span class="font-semibold text-gray-900 ml-auto sm:ml-0">{{ $reservation->occupants_count ?? 1 }} Person(s)</span>
                                </div>

                                {{-- Contact details feature --}}
                                <div class="flex items-center gap-3 text-[14px] sm:col-span-2">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143" /></svg>
                                    <span class="text-gray-500">Contact:</span>
                                    <span class="font-semibold text-gray-900 ml-auto sm:ml-0">{{ $reservation->tenant->contact_number ?? $reservation->tenant->phone ?? 'Not provided' }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Remarks Messaging Card Block --}}
                        @if($reservation->remarks)
                            <div class="text-[14px] text-gray-600 bg-gray-50 border border-gray-150 rounded-2xl px-4 py-3.5 mb-6">
                                <span class="block text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-1">Message from Applicant:</span>
                                "{{ $reservation->remarks }}"
                            </div>
                        @endif

                        @if($reservation->rejection_reason)
                            <div class="text-[14px] text-red-800 bg-red-50/50 border border-red-100 rounded-2xl px-4 py-3.5 mb-6">
                                <span class="block text-[11px] font-bold text-red-500 uppercase tracking-wide mb-1">Decline Reason:</span>
                                {{ $reservation->rejection_reason }}
                            </div>
                        @endif

                        {{-- Dynamic Bottom Management Ribbon --}}
                        @if($currentStatus === 'Pending')
                            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button" 
                                    @click="activeReservationId = {{ $resId }}; rejectModalOpen = true;"
                                    class="h-10 px-5 rounded-xl border border-gray-200 hover:bg-gray-50 font-bold text-[13px] text-gray-700 transition">
                                    Reject
                                </button>
                                
                                <form action="{{ route('landlord.reservations.approve', $reservation) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="h-10 px-5 rounded-xl bg-[#286CD2] hover:bg-[#1e5bb8] text-white font-bold text-[13px] transition shadow-sm">
                                        Approve
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-12 flex justify-center">
                {{ $reservations->links() }}
            </div>
        @endif

        {{-- Premium Rejection Dialog Overlay Component --}}
        <div class="fixed inset-0 z-50 overflow-y-auto" x-show="rejectModalOpen" style="display: none;" x-transition>
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="rejectModalOpen = false"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-2xl bg-white shadow-xl w-full max-w-md p-6 border border-gray-100"
                    @click.away="rejectModalOpen = false">
                    
                    <div class="mb-4">
                        <h3 class="text-[18px] font-bold text-gray-900 tracking-tight">Decline Request</h3>
                        <p class="text-[13px] text-gray-400 mt-0.5">Provide a reason to inform the tenant why this application was declined.</p>
                    </div>

                    <form :action="`/landlord/reservations/${activeReservationId}/reject`" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-5">
                            <textarea id="rejection_reason" name="rejection_reason" rows="4" required
                                placeholder="e.g., The room has already been filled through offline means."
                                class="w-full border border-gray-200 rounded-xl px-4 py-3 text-[14px] text-gray-700 placeholder-gray-400 focus:outline-none focus:border-[#286CD2] transition resize-none"></textarea>
                        </div>

                        <div class="flex items-center gap-2 justify-end">
                            <button type="button" @click="rejectModalOpen = false"
                                class="h-10 px-4 border border-gray-200 text-gray-700 font-bold text-[13px] rounded-xl hover:bg-gray-50 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-10 px-4 bg-red-600 hover:bg-red-700 text-white font-bold text-[13px] rounded-xl transition">
                                Confirm Decline
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
@endsection