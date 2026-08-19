@extends('layouts.admin')

@section('page-title', 'Property Details')

@section('content')
@php
    $verificationBadge = [
        'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Pending'  => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
    ];
    $availabilityBadge = [
        'Available'   => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Reserved'    => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
        'Occupied'    => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
        'Maintenance' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
    ];
@endphp

<div class="max-w-[1200px] mx-auto">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-1.5 text-[12.5px] text-[#94A3B8] mb-4">
        <a href="{{ route('admin.catalogue.properties.index') }}" class="hover:text-[#1F2937] transition-colors">Properties</a>
        <span>/</span>
        <span class="text-[#1F2937] font-medium">{{ $property->title }}</span>
    </div>

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">{{ $property->title }}</h1>
            <p class="text-[13.5px] text-[#64748B] mt-1">{{ $property->address }}</p>
        </div>
        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[12px] font-bold border {{ $verificationBadge[$property->verification_status] ?? '' }}">
            {{ $property->verification_status }}
        </span>
    </div>

    {{-- Media gallery --}}
    @if($property->media->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            @foreach($property->media->take(8) as $media)
                <div class="aspect-square rounded-xl overflow-hidden bg-[#EEF8F8]">
                    @if($media->media_type === 'Image')
                        <img src="{{ $media->media_url }}" alt="{{ $property->title }}" loading="lazy" class="w-full h-full object-cover">
                    @else
                        <video src="{{ $media->media_url }}" class="w-full h-full object-cover" muted></video>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="mb-6 rounded-2xl border-2 border-dashed border-[#E2E8F0] bg-[#F7FCFC] py-8 text-center">
            <p class="text-[13.5px] font-semibold text-[#B45309]">No photos uploaded</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Main column --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Overview --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8] mb-4">Overview</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Type</dt>
                        <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">{{ $property->property_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Units</dt>
                        <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">{{ $property->units->count() }}</dd>
                    </div>
                    @php
                        $unpinned = (float) $property->latitude === 10.3157 && (float) $property->longitude === 123.8854;
                    @endphp
                    <div>
                        <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Map Location</dt>
                        <dd class="text-[14px] font-medium mt-0.5 {{ $unpinned ? 'text-[#B45309]' : 'text-[#1F2937]' }}">
                            @if($unpinned)
                                Never pinned — showing the Cebu City center default
                            @else
                                {{ $property->latitude }}, {{ $property->longitude }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Created</dt>
                        <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">{{ $property->created_at?->format('M d, Y') }}</dd>
                    </div>
                </dl>
                @if($property->description)
                    <div class="mt-4 pt-4 border-t border-[#E2E8F0]">
                        <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide mb-1">Description</dt>
                        <dd class="text-[13.5px] text-[#64748B] leading-relaxed">{{ $property->description }}</dd>
                    </div>
                @endif
            </div>

            {{-- Units --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8] px-5 pt-5 pb-3">Units</h2>
                @if($property->units->isEmpty())
                    <p class="px-5 pb-5 text-[13.5px] text-[#94A3B8]">No units have been added to this property yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-[#F7FCFC] border-y border-[#E2E8F0]">
                                    <th class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Unit</th>
                                    <th class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Rent</th>
                                    <th class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Status</th>
                                    <th class="px-5 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                @foreach($property->units as $unit)
                                    <tr class="hover:bg-[#F7FCFC] transition-colors">
                                        <td class="px-5 py-3 text-[13.5px] font-semibold text-[#1F2937]">{{ $unit->unit_label }}</td>
                                        <td class="px-5 py-3 text-[13px] text-[#64748B]">₱{{ number_format($unit->rental_fee, 0) }}</td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold border {{ $availabilityBadge[$unit->availability_status] ?? '' }}">
                                                {{ $unit->availability_status }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <a href="{{ route('admin.units.show', ['property' => $property->property_id, 'unit' => $unit->unit_id]) }}"
                                                class="text-[12px] font-semibold text-[#156F8C] hover:underline">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Verification Documents — full review lives in Verification & Approvals →
                 Property Documents, so it's reachable without drilling into a specific
                 property first. This card is a scoped summary + the request action, which
                 does need this property's context. --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="flex items-center justify-between px-5 pt-5 pb-3">
                    <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8]">Verification Documents</h2>
                    <a href="{{ route('admin.documents.index', ['status' => 'All']) }}"
                        class="text-[12.5px] font-semibold text-[#156F8C] hover:underline">Review in Verification &amp; Approvals →</a>
                </div>

                @if($property->documents->isEmpty())
                    <p class="px-5 pb-4 text-[13.5px] text-[#94A3B8]">No documents submitted yet.</p>
                @else
                    <div class="px-5 pb-4 flex flex-wrap gap-2">
                        @foreach($property->documents->sortByDesc('updated_at') as $document)
                            <a href="{{ route('admin.documents.show', $document) }}"
                               class="inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] pl-3 pr-1 py-1 hover:bg-[#F7FCFC] transition-colors">
                                <span class="text-[12.5px] font-medium text-[#1F2937]">{{ $document->document_type }}</span>
                                <x-document-status-badge :document="$document" />
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Request a document --}}
                <div class="px-5 py-4 bg-[#F7FCFC] border-t border-[#E2E8F0]" x-data="{ requesting: false }">
                    <button type="button" @click="requesting = !requesting"
                        class="text-[12.5px] font-semibold text-[#156F8C] hover:underline">
                        + Request a document
                    </button>
                    <form x-show="requesting" x-cloak method="POST" action="{{ route('admin.properties.documents.request', $property) }}"
                        class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="flex-1 min-w-[200px]">
                            @php
                                $requestDocumentTypeOptions = ['' => 'Select a document type'] + array_combine(\App\Models\PropertyDocument::TYPES, \App\Models\PropertyDocument::TYPES);
                            @endphp
                            <x-styled-select name="document_type" required :options="$requestDocumentTypeOptions" :selected="''"
                                class="h-9 w-full rounded-lg border border-[#E2E8F0] px-3 text-[13px] text-[#1F2937] bg-white" />
                        </div>
                        <button type="submit" class="h-9 px-4 rounded-lg bg-[#1F2937] text-white text-[12.5px] font-bold hover:brightness-95 transition-all">
                            Request
                        </button>
                    </form>
                </div>
            </div>

            {{-- Reservations --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8] px-5 pt-5 pb-3">Reservation History</h2>
                @if($property->reservations->isEmpty())
                    <p class="px-5 pb-5 text-[13.5px] text-[#94A3B8]">No reservations on this property yet.</p>
                @else
                    <div class="divide-y divide-[#E2E8F0]">
                        @foreach($property->reservations->sortByDesc('created_at')->take(10) as $reservation)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                        {{ trim(($reservation->tenant->first_name ?? '') . ' ' . ($reservation->tenant->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[12px] text-[#94A3B8]">{{ $reservation->created_at?->format('M d, Y') }}</p>
                                </div>
                                <a href="{{ route('admin.reservations.show', $reservation) }}"
                                    class="text-[12px] font-semibold text-[#156F8C] hover:underline">{{ $reservation->rental_status }}</a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Side column --}}
        <div class="space-y-5">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8] mb-3">Landlord</h2>
                <p class="text-[14.5px] font-semibold text-[#1F2937]">
                    {{ trim(($property->landlord->first_name ?? '') . ' ' . ($property->landlord->last_name ?? '')) ?: '—' }}
                </p>
                <p class="text-[13px] text-[#64748B] mt-0.5">{{ $property->landlord->email ?? '—' }}</p>
                @if($property->landlord)
                    <a href="{{ route('admin.users.show', $property->landlord) }}"
                        class="inline-flex items-center gap-1.5 mt-3 text-[12.5px] font-semibold text-[#156F8C] hover:underline">
                        View landlord profile
                    </a>
                @endif
            </div>

            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8] mb-3">Reviews</h2>
                @if($property->reviews->isEmpty())
                    <p class="text-[13.5px] text-[#94A3B8]">No reviews yet.</p>
                @else
                    <p class="text-[28px] font-extrabold text-[#1F2937] leading-none">
                        {{ number_format($property->reviews->avg('rating'), 1) }}
                    </p>
                    <p class="text-[12px] text-[#94A3B8] mt-1">{{ $property->reviews->count() }} review{{ $property->reviews->count() > 1 ? 's' : '' }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
