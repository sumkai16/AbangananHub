@extends('layouts.admin')

@section('page-title', 'Property Details')

@section('content')
@php
    $landlord = $property->landlord;
    $images = $property->media->where('media_type', 'Image');
    $unpinned = (float) $property->latitude === 10.3157 && (float) $property->longitude === 123.8854;
    $docsTotal = $property->documents->count();
    $docsVerified = $property->documents->where('status', 'Verified')->count();
    $isPending = $property->verification_status === 'Pending';
    $isSuspended = $property->publication_status === 'Suspended';
@endphp

<div class="max-w-[1600px] mx-auto">

    <x-back-link href="{{ route('admin.catalogue.properties.index') }}" to="properties" class="mb-4" />

    <x-page-header :title="$property->title" :subtitle="$property->address">
        <x-slot:badge>
            <x-admin.status :status="$property->verification_status" />
            @if($property->publication_status !== 'Published')
                <x-admin.status :status="$property->publication_status" />
            @endif
        </x-slot:badge>
    </x-page-header>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px] items-start">

        <div class="min-w-0 space-y-5">

            {{-- Photos fill the row: one large, the rest beside it. --}}
            @if($property->media->isNotEmpty())
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4 md:grid-rows-2 md:h-[22rem]">
                    @foreach($property->media->take(5) as $media)
                        <div class="relative overflow-hidden rounded-xl bg-[#ECEEF6] {{ $loop->first ? 'col-span-2 aspect-[16/10] md:row-span-2 md:aspect-auto' : 'aspect-[4/3] md:aspect-auto' }}">
                            @if($media->media_type === 'Image')
                                <img src="{{ $media->media_url }}" alt="{{ $property->title }}" loading="lazy" class="h-full w-full object-cover">
                            @else
                                <video src="{{ $media->media_url }}" class="h-full w-full object-cover" muted controls></video>
                            @endif
                            @if($loop->iteration === 5 && $property->media->count() > 5)
                                <span class="absolute bottom-2 right-2 rounded-md bg-[#060D26] px-2 py-1 text-[12px] font-semibold text-white">+{{ $property->media->count() - 5 }} more</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="rounded-2xl bg-[#FBBF24]/[0.10] px-5 py-4 text-[13.5px] font-medium text-[#060D26]">No photos uploaded. Tenants will see an empty listing.</p>
            @endif

            {{-- Overview --}}
            <x-card>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Overview</h2>
                    <div class="flex flex-wrap gap-1">
                        <x-admin.status :status="$images->count().' '.Str::plural('photo', $images->count())" :tone="$images->count() > 0 ? 'green' : 'red'" />
                        <x-admin.status :status="$property->units->count().' '.Str::plural('unit', $property->units->count())" :tone="$property->units->count() > 0 ? 'green' : 'red'" />
                        <x-admin.status :status="$docsVerified.'/'.$docsTotal.' documents'" :tone="$docsTotal > 0 && $docsVerified === $docsTotal ? 'green' : 'amber'" />
                    </div>
                </div>
                <x-admin.facts :items="[
                    'Type' => $property->property_type,
                    'City' => collect([$property->barangay, $property->city_municipality])->filter()->implode(', '),
                    'Map location' => $unpinned ? 'Never pinned, showing the Cebu City default' : $property->latitude.', '.$property->longitude,
                    'Created' => $property->created_at?->format('M j, Y'),
                    'Last updated' => $property->updated_at?->format('M j, Y'),
                ]" />
                @if($property->description)
                    <div class="mt-5 border-t border-[#E2E4EC] pt-4">
                        <p class="text-[12.5px] text-[#5B6A8E]">Description</p>
                        <p class="mt-0.5 text-[14px] leading-relaxed text-[#060D26]">{{ $property->description }}</p>
                    </div>
                @endif
                @if($property->rejection_reason && $property->verification_status === 'Rejected')
                    <div class="mt-5 border-t border-[#E2E4EC] pt-4">
                        <p class="text-[12.5px] text-[#5B6A8E]">Rejection reason</p>
                        <p class="mt-0.5 text-[14px] leading-relaxed text-[#DC2626]">{{ $property->rejection_reason }}</p>
                    </div>
                @endif
            </x-card>

            {{-- Units --}}
            <x-card flush>
                <div class="border-b border-[#E2E4EC] px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Units</h2>
                </div>
                @if($property->units->isEmpty())
                    <x-admin.empty title="No units yet" note="The landlord has not added any units to this property." />
                @else
                    <div class="overflow-x-auto scrollbar-thin-light">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                    <x-admin.th>Unit</x-admin.th>
                                    <x-admin.th align="right">Rent</x-admin.th>
                                    <x-admin.th>Availability</x-admin.th>
                                    <x-admin.th>Verification</x-admin.th>
                                    <th class="px-4 py-2.5"><span class="sr-only">Open</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E4EC]">
                                @foreach($property->units as $unit)
                                    <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                        <td class="px-4 py-3 text-[13.5px] font-semibold text-[#060D26]">{{ $unit->unit_label }}</td>
                                        <td class="px-4 py-3 text-right text-[13.5px] font-semibold tabular-nums text-[#060D26]">₱{{ number_format($unit->rental_fee, 2) }}</td>
                                        <td class="px-4 py-3"><x-admin.status :status="$unit->availability_status" kind="unit" /></td>
                                        <td class="px-4 py-3"><x-admin.status :status="$unit->verification_status" /></td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('admin.units.show', ['property' => $property->property_id, 'unit' => $unit->unit_id]) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            {{-- Full document review lives in Property Documents, reachable without opening a property first. This card is
                 a scoped summary plus the request action, which does need this property's context. --}}
            <x-card flush x-data="{ requesting: false }">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#E2E4EC] px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Documents</h2>
                    <button type="button" @click="requesting = !requesting" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Request a document</button>
                </div>
                <form x-show="requesting" x-cloak method="POST" action="{{ route('admin.properties.documents.request', $property) }}"
                    class="flex flex-wrap items-end gap-2 border-b border-[#E2E4EC] bg-[#F7F8FC] px-5 py-4">
                    @csrf
                    <div class="min-w-[200px] flex-1">
                        @php
                            $requestDocumentTypeOptions = ['' => 'Select a document type'] + array_combine(\App\Models\PropertyDocument::TYPES, \App\Models\PropertyDocument::TYPES);
                        @endphp
                        <x-styled-select name="document_type" required :options="$requestDocumentTypeOptions" :selected="''"
                            class="h-9 w-full rounded-lg border border-[#E2E4EC] bg-white px-3 text-[13px] text-[#060D26]" />
                    </div>
                    <button type="submit" class="h-9 rounded-lg bg-[#FF8A66] px-4 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F]">Send request</button>
                </form>
                @if($property->documents->isEmpty())
                    <x-admin.empty title="No documents yet" />
                @else
                    <ul class="divide-y divide-[#E2E4EC]">
                        @foreach($property->documents->sortByDesc('updated_at') as $document)
                            <li>
                                <a href="{{ route('admin.documents.show', $document) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $document->document_type }}</span>
                                        <span class="block text-[12.5px] text-[#5B6A8E]">Updated {{ $document->updated_at?->format('M j, Y') }}@if($document->expiry_date) · expires {{ $document->expiry_date->format('M j, Y') }}@endif</span>
                                    </span>
                                    <x-admin.status :status="$document->isRequested() ? 'Requested' : $document->display_status" class="shrink-0" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- What happened on this listing, newest first: the history the landlord sees, written by the observers. Read-only
                 and plain text: its links lead into landlord-only pages. --}}
            <x-card flush>
                <div class="flex items-baseline justify-between gap-3 px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Recent activity</h2>
                    @if($activityTotal > 0)
                        <p class="text-[12px] text-[#5B6A8E] tabular-nums">{{ $activityTotal > $activities->count() ? 'Latest '.$activities->count().' of '.$activityTotal : $activityTotal.' '.\Illuminate\Support\Str::plural('line', $activityTotal) }}</p>
                    @endif
                </div>
                @if($activities->isEmpty())
                    <p class="px-5 sm:px-6 py-6 text-[13px] text-[#5B6A8E]">Nothing has happened on this listing yet.</p>
                @else
                    <ul class="divide-y divide-[#E2E4EC]">
                        @foreach($activities as $activity)
                            @php
                                $role = ['landlord' => 'Landlord', 'tenant' => 'Tenant', 'admin' => 'Admin'][$activity->actor_role] ?? 'System';
                                $chip = match ($activity->actor_role) {
                                    'landlord' => 'bg-[#ECEEF6] text-[#060D26]',
                                    'tenant'   => 'bg-[#FF8A66]/15 text-[#B35A3D]',
                                    'admin'    => 'bg-[#060D26] text-white',
                                    default    => 'bg-[#F7F8FC] text-[#5B6A8E] ring-1 ring-inset ring-[#E2E4EC]',
                                };
                            @endphp
                            <li class="flex items-start gap-3 px-5 sm:px-6 py-3.5">
                                <span class="mt-0.5 inline-flex h-6 min-w-[4rem] shrink-0 items-center justify-center rounded-full px-2.5 text-[11px] font-semibold {{ $chip }}">{{ $role }}</span>
                                <span class="min-w-0 flex-1"><span class="block text-[13.5px] leading-snug text-[#060D26]">{{ $activity->summary }}</span>
                                    <time datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ $activity->created_at->format('M j, Y \a\t g:i A') }}" class="mt-0.5 block text-[12px] text-[#5B6A8E]">{{ $activity->created_at->format($activity->created_at->isCurrentYear() ? 'M j · g:i A' : 'M j, Y') }}</time></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

        </div>

        {{-- Rail --}}
        <div class="space-y-5 lg:sticky lg:top-6">

            @if($isPending)
                <x-card>
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Your decision</h2>
                    <p class="mt-1 text-[13px] text-[#5B6A8E]">Waiting since {{ $property->created_at?->format('M j, Y') }}.</p>
                    <form method="POST" action="{{ route('admin.listings.approve', $property->property_id) }}" class="mt-3"
                        data-confirm="Approve this listing?"
                        data-confirm-message="It becomes visible to tenants and the landlord is told. Check the photos and documents first."
                        data-confirm-button="Approve listing">
                        @csrf
                        <button type="submit" class="h-10 w-full rounded-lg bg-[#15803D] text-[13.5px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">Approve listing</button>
                    </form>
                    <form method="POST" action="{{ route('admin.listings.reject', $property->property_id) }}" class="mt-4 space-y-2 border-t border-[#E2E4EC] pt-4"
                        data-confirm="Reject this listing?" data-confirm-type="error">
                        @csrf
                        <label for="listing-reject-reason" class="block text-[13px] font-medium text-[#060D26]">Reason, if rejecting</label>
                        <textarea id="listing-reject-reason" name="rejection_reason" rows="3" required placeholder="The landlord will see this"
                            class="w-full rounded-xl border border-[#E2E4EC] px-3.5 py-2.5 text-[13.5px] text-[#060D26] placeholder-[#94A3B8] transition focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <p class="text-[12.5px] text-[#DC2626]">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="h-10 w-full rounded-lg border border-[#EF4444]/30 text-[13.5px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06] cursor-pointer">Reject listing</button>
                    </form>
                </x-card>
            @elseif($isSuspended)
                <x-card>
                    <h2 class="text-[15px] font-semibold text-[#060D26]">This listing is suspended</h2>
                    <p class="mt-1 text-[13px] text-[#5B6A8E]">Tenants cannot see it.</p>
                    <form method="POST" action="{{ route('admin.listings.unsuspend', $property->property_id) }}" class="mt-3"
                        data-confirm="Reinstate this listing?" data-confirm-message="It will become visible to tenants again immediately.">
                        @csrf
                        <button type="submit" class="h-10 w-full rounded-lg bg-[#FF8A66] text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Unsuspend</button>
                    </form>
                </x-card>
            @endif

            <x-card flush>
                <div class="px-5 py-4">
                    <p class="text-[12.5px] text-[#5B6A8E]">Landlord</p>
                    @if($landlord)
                        <a href="{{ route('admin.users.show', $landlord) }}" class="mt-0.5 block text-[14px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ trim($landlord->first_name.' '.$landlord->last_name) }}</a>
                        <p class="break-words text-[12.5px] text-[#5B6A8E]">{{ $landlord->email ?: '—' }}</p>
                    @else
                        <p class="mt-0.5 text-[14px] text-[#5B6A8E]">—</p>
                    @endif
                </div>
                <div class="border-t border-[#E2E4EC] px-5 py-4">
                    <p class="text-[12.5px] text-[#5B6A8E]">Reviews</p>
                    @if($property->reviews->isEmpty())
                        <p class="mt-0.5 text-[14px] text-[#060D26]">None yet</p>
                    @else
                        <p class="mt-0.5 text-[14px] font-semibold tabular-nums text-[#060D26]">{{ number_format($property->reviews->avg('rating'), 1) }} <span class="font-normal text-[#5B6A8E]">from {{ $property->reviews->count() }} {{ Str::plural('review', $property->reviews->count()) }}</span></p>
                    @endif
                </div>
            </x-card>

            <x-card flush>
                <div class="border-b border-[#E2E4EC] px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Reservations</h2>
                </div>
                @if($property->reservations->isEmpty())
                    <x-admin.empty title="No reservations yet" />
                @else
                    <ul class="divide-y divide-[#E2E4EC]">
                        @foreach($property->reservations->sortByDesc('created_at')->take(10) as $reservation)
                            <li>
                                <a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center justify-between gap-3 px-5 py-2.5 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ trim(($reservation->tenant->first_name ?? '').' '.($reservation->tenant->last_name ?? '')) ?: '—' }}</span>
                                        <span class="block text-[12.5px] text-[#5B6A8E]">{{ $reservation->created_at?->format('M j, Y') }}</span>
                                    </span>
                                    <x-admin.status :status="$reservation->rental_status" class="shrink-0" />
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
