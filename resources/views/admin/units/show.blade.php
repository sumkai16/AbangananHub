@extends('layouts.admin')

@section('page-title', 'Review Unit')

@section('content')
@php
    $landlord = $property->landlord;
    $photos = $unit->media->where('media_type', 'Image');
    $video = $unit->media->where('media_type', 'Video')->first();
    $tenant = $unit->activeReservation?->tenant;
    $unitReservations = $unit->reservations->sortByDesc('created_at')->take(8);
@endphp
    <div class="admin-compact max-w-[1600px] mx-auto">

        <x-back-link href="{{ route('admin.units.index') }}" to="unit approvals" class="mb-2" />

        <x-admin.header :title="$unit->unit_label" :subtitle="$property->title.($property->address ? ' · '.$property->address : '')">
            <x-slot:badge>
                <x-admin.status :status="$unit->verification_status" />
                <x-admin.status :status="$unit->availability_status" kind="unit" />
            </x-slot:badge>
        </x-admin.header>

        @if($unit->isRejected() && $unit->rejection_reason)
            <p class="mb-3 rounded-lg bg-[#EF4444]/[0.07] px-3 py-2 text-[13px] leading-5 text-[#060D26]"><span class="font-semibold text-[#DC2626]">Previously rejected:</span> {{ $unit->rejection_reason }}</p>
        @endif

        <div class="grid grid-cols-1 items-start gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">

            <div class="min-w-0 space-y-3">

                <x-card>
                    <h2 class="mb-2 text-[14px] font-semibold text-[#060D26]">Unit details</h2>
                    <x-admin.facts compact :cols="5" hide-blank :items="[
                        'Monthly rent' => ['value' => '₱'.number_format($unit->rental_fee, 2), 'num' => true],
                        'Security deposit' => ['value' => $unit->security_deposit !== null ? '₱'.number_format($unit->security_deposit, 2) : null, 'num' => true],
                        'Capacity' => $unit->occupancy_limit.' '.Str::plural('person', $unit->occupancy_limit),
                        'Floor area' => $unit->floor_area_label,
                        'Floor' => $unit->floor,
                        'Bedrooms' => $unit->bedrooms,
                        'Bathrooms' => $unit->bathrooms,
                        'Furnishing' => $unit->furnishing_status,
                        'Current tenant' => $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : null,
                        'Submitted' => $unit->created_at->format('M j, Y'),
                    ]" />
                    @if($unit->description)
                        <p class="mt-2.5 max-w-[75ch] text-[13px] leading-5 text-[#5B6A8E]">{{ $unit->description }}</p>
                    @endif
                </x-card>

                <x-card>
                    <div class="mb-2 flex items-baseline justify-between gap-3">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Photos and video</h2>
                        <p class="text-[12.5px] tabular-nums text-[#5B6A8E]">{{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }}{{ $video ? ', 1 video' : '' }}</p>
                    </div>
                    @if($photos->isEmpty() && ! $video)
                        <p class="rounded-lg bg-[#FBBF24]/[0.10] px-3 py-2 text-[13px] text-[#060D26]">No photos or video were submitted for this unit.</p>
                    @else
                        <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-5 xl:grid-cols-6">
                            @foreach($photos as $photo)
                                <a href="{{ $photo->media_url }}" target="_blank" rel="noopener" class="block aspect-[4/3] overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66]">
                                    <img loading="lazy" decoding="async" src="{{ $photo->media_url }}" alt="Photo of {{ $unit->unit_label }}" class="h-full w-full object-cover">
                                </a>
                            @endforeach
                            @if($video)
                                <div class="aspect-[4/3] overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC]">
                                    <video src="{{ $video->media_url }}" controls class="h-full w-full object-cover"></video>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-card>

                <x-card flush>
                    <div class="border-b border-[#E2E4EC] px-4 py-2.5">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Reservations for this unit</h2>
                    </div>
                    @if($unitReservations->isEmpty())
                        <x-admin.empty compact title="No reservations yet" />
                    @else
                        <div class="overflow-x-auto scrollbar-thin-light">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                        <x-admin.th>Tenant</x-admin.th>
                                        <x-admin.th>Created</x-admin.th>
                                        <x-admin.th>Status</x-admin.th>
                                        <th class="px-4 py-2"><span class="sr-only">Open</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E4EC]">
                                    @foreach($unitReservations as $reservation)
                                        <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                            <td class="px-4 text-[13px] font-medium text-[#060D26]">{{ trim(($reservation->tenant->first_name ?? '').' '.($reservation->tenant->last_name ?? '')) ?: '—' }}</td>
                                            <td class="px-4 text-[13px] whitespace-nowrap tabular-nums text-[#5B6A8E]">{{ $reservation->created_at?->format('M j, Y') }}</td>
                                            <td class="px-4"><x-admin.status :status="$reservation->rental_status" /></td>
                                            <td class="px-4 text-right"><a href="{{ route('admin.reservations.show', $reservation) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">View</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            <div class="space-y-3 lg:sticky lg:top-6">

                @if($unit->isPending())
                    <x-admin.decision
                        :since="$unit->created_at->format('M j, Y')"
                        :approve-action="route('admin.units.approve', [$property, $unit])"
                        approve-label="Approve unit"
                        approve-confirm="Approve this unit?"
                        :reject-action="route('admin.units.reject', [$property, $unit])"
                        reject-label="Reject unit"
                        reject-confirm="Reject this unit?"
                        :required="false"
                        maxlength="500" />
                @endif

                <x-card flush>
                    <div class="px-4 py-2.5">
                        <p class="text-[12px] text-[#5B6A8E]">Landlord</p>
                        @if($landlord)
                            <a href="{{ route('admin.users.show', $landlord) }}" class="block text-[13px] font-semibold leading-5 text-[#060D26] underline-offset-2 hover:underline">{{ trim($landlord->first_name.' '.$landlord->last_name) }}</a>
                            <p class="break-words text-[12px] leading-4 text-[#5B6A8E]">{{ $landlord->email ?: '—' }}</p>
                        @else
                            <p class="text-[13px] text-[#5B6A8E]">—</p>
                        @endif
                    </div>
                    <div class="border-t border-[#E2E4EC] px-4 py-2.5">
                        <p class="flex items-center justify-between gap-2 text-[12px] text-[#5B6A8E]">Property <x-admin.status :status="$property->verification_status" /></p>
                        <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="block text-[13px] font-semibold leading-5 text-[#060D26] underline-offset-2 hover:underline">{{ $property->title }}</a>
                    </div>
                </x-card>

                <x-card flush>
                    <div class="border-b border-[#E2E4EC] px-4 py-2.5">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Other units here</h2>
                    </div>
                    @if($siblings->isEmpty())
                        <x-admin.empty compact title="This is the only unit" />
                    @else
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($siblings as $sibling)
                                <li>
                                    <a href="{{ route('admin.units.show', [$property, $sibling]) }}" class="flex items-center justify-between gap-3 px-4 py-1.5 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                        <span class="min-w-0">
                                            <span class="block truncate text-[13px] font-medium leading-5 text-[#060D26]">{{ $sibling->unit_label }}</span>
                                            <span class="block text-[12px] leading-4 tabular-nums text-[#5B6A8E]">₱{{ number_format($sibling->rental_fee, 2) }}</span>
                                        </span>
                                        <x-admin.status :status="$sibling->availability_status" kind="unit" class="shrink-0" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        </div>
    </div>
@endsection
