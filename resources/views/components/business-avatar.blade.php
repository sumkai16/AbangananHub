@props([
    // A landlord User with `rentalBusiness` loaded.
    'landlord',
    // Tailwind size + text size for the tile, e.g. 'w-12 h-12 text-[15px]'.
    'size' => 'w-12 h-12 text-[15px]',
])

{{--
    The public face of a landlord: their business logo, else the initials of the business name. Never the
    person's photo or name (see User::publicName()). The logo is object-cover so it fills the circle.
--}}
@php
    $business = $landlord->rentalBusiness;
    $logo = $business?->logo_url;
@endphp

<div {{ $attributes->class([
    'shrink-0 rounded-full overflow-hidden flex items-center justify-center font-bold text-[#060D26]',
    $size,
    'bg-white ring-1 ring-[#E2E4EC]' => $logo,
    'bg-[#FF8A66]' => ! $logo,
]) }}>
    @if ($logo)
        <img loading="lazy" decoding="async" src="{{ $logo }}" alt="{{ $landlord->publicName() }}" class="w-full h-full object-cover">
    @else
        <span aria-hidden="true">{{ $landlord->publicInitials() }}</span>
    @endif
</div>
