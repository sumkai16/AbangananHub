@props(['reservation', 'showActions' => false])

@php
    $pillClass = match ($reservation->rental_status) {
        'Occupied' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
        default => 'bg-[#F7FCFC] text-[#1F2937] border-[#E2E8F0]',
    };
    $canCancel = !in_array($reservation->rental_status, ['Cancelled', 'Rejected', 'Occupied']);
@endphp

<div class="flex flex-col md:flex-row md:items-center gap-5 bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-sm hover:shadow-md transition-all reservation-row"
    data-status="{{ $reservation->rental_status }}">

    <div
        class="w-[72px] h-[72px] rounded-xl flex-shrink-0 bg-[#EEF8F8] overflow-hidden flex items-center justify-center">
        @if($reservation->property->media->first())
            <img src="{{ $reservation->property->media->first()->media_url }}" class="w-full h-full object-cover"
                alt="Property">
        @else
            <div class="text-2xl font-bold text-[#94A3B8]">
                {{ strtoupper(substr($reservation->property->title ?? 'P', 0, 1)) }}
            </div>
        @endif
    </div>

    <div class="flex-1 min-w-0">
        <div class="text-[16px] font-bold text-[#156F8C] mb-1.5 truncate property-title">
            {{ $reservation->property->title ?? 'Property' }}
        </div>
        <div class="text-[14px] text-[#64748B]">
            <span class="font-semibold text-[#1F2937]">
                {{ $reservation->reservation_date?->format('M d, Y') ?? 'TBD' }}
            </span>
            @if($reservation->property->address ?? false)
                &nbsp;·&nbsp; {{ Str::limit($reservation->property->address, 42) }}
            @endif
        </div>
    </div>

    <div
        class="flex md:flex-col items-center justify-between md:items-end w-full md:w-auto mt-2 md:mt-0 text-right gap-2">
        <div class="inline-block text-[11.5px] font-bold px-3 py-1 rounded-full border {{ $pillClass }}">
            {{ $reservation->rental_status }}
        </div>
        <div class="text-[13px] text-[#94A3B8] font-medium reference-id">
            Ref: #R{{ $reservation->reservation_id }}
        </div>

        @if($showActions && $canCancel)
            <form action="{{ route('reservations.cancel', $reservation->reservation_id) }}" method="POST"
                data-confirm="Cancel this reservation?"
                data-confirm-type="warning"
                data-confirm-message="Your reservation will be cancelled."
                data-confirm-button="Cancel reservation"
                data-confirm-cancel="Keep it">
                @csrf
                @method('PATCH')
                <button type="submit" class="text-[13px] font-bold text-[#DC2626] hover:text-[#DC2626] transition-colors">
                    Cancel
                </button>
            </form>
        @endif
    </div>

</div>