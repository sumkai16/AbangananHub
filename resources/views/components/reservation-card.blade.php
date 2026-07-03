@props(['reservation', 'showActions' => false])

@php
    $pillClass = match ($reservation->rental_status) {
        'Occupied' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed' => 'bg-amber-50 text-amber-700 border-amber-200',
        'Rejected' => 'bg-red-50 text-red-700 border-red-200',
        default => 'bg-gray-50 text-gray-700 border-gray-200',
    };
    $canCancel = !in_array($reservation->rental_status, ['Cancelled', 'Rejected', 'Occupied']);
@endphp

<div class="flex flex-col md:flex-row md:items-center gap-5 bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all reservation-row"
    data-status="{{ $reservation->rental_status }}">

    <div
        class="w-[72px] h-[72px] rounded-xl flex-shrink-0 bg-gray-100 overflow-hidden flex items-center justify-center">
        @if($reservation->property->media->first())
            <img src="{{ $reservation->property->media->first()->media_url }}" class="w-full h-full object-cover"
                alt="Property">
        @else
            <div class="text-2xl font-bold text-gray-400">
                {{ strtoupper(substr($reservation->property->title ?? 'P', 0, 1)) }}
            </div>
        @endif
    </div>

    <div class="flex-1 min-w-0">
        <div class="text-[16px] font-bold text-[#1A1A2E] mb-1.5 truncate property-title">
            {{ $reservation->property->title ?? 'Property' }}
        </div>
        <div class="text-[14px] text-gray-500">
            <span class="font-semibold text-gray-700">
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
        <div class="text-[13px] text-gray-400 font-medium reference-id">
            Ref: #R{{ $reservation->reservation_id }}
        </div>

        @if($showActions && $canCancel)
            <form action="{{ route('reservations.cancel', $reservation->reservation_id) }}" method="POST"
                onsubmit="return confirm('Cancel this reservation?');">
                @csrf
                @method('PATCH')
                <button type="submit" class="text-[13px] font-bold text-red-600 hover:text-red-800 transition-colors">
                    Cancel
                </button>
            </form>
        @endif
    </div>

</div>