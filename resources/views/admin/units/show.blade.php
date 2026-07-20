@extends('layouts.admin')

@section('page-title', 'Review Unit')

@section('content')
    <div class="max-w-7xl">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-1.5 text-[13px] text-[#94A3B8] mb-4">
            <a href="{{ route('admin.units.index') }}" class="hover:text-[#1F2937] transition-colors">Unit Approvals</a>
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">{{ $unit->unit_label }}</span>
        </div>

        @php
            $statusCls = match ($unit->verification_status) {
                'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
                'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
                default => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
            };
        @endphp

        <div class="flex items-center justify-between mb-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">{{ $unit->unit_label }}</h1>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusCls }}">
                        {{ $unit->verification_status }}
                    </span>
                </div>
                <p class="text-[13.5px] text-[#64748B] mt-1">{{ $property->title }}</p>
            </div>
        </div>

        @if($unit->isRejected() && $unit->rejection_reason)
            <div class="mb-6 px-5 py-4 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 flex items-start gap-3">
                <svg class="w-5 h-5 text-[#DC2626] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div>
                    <p class="text-[13px] font-bold text-[#DC2626]">Previously rejected</p>
                    <p class="text-[13px] text-[#DC2626] mt-0.5">{{ $unit->rejection_reason }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            {{-- Left: details + media --}}
            <div class="lg:col-span-8 space-y-5">

                <div class="bg-white border border-[#E2E8F0] rounded-3xl p-6 shadow-sm">
                    <h2 class="text-[15px] font-bold text-[#1F2937] mb-4">Unit Details</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Monthly Rent</p>
                            <p class="text-[14px] font-semibold text-[#1F2937]">₱{{ number_format($unit->rental_fee, 2) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Capacity</p>
                            <p class="text-[14px] font-semibold text-[#1F2937]">{{ $unit->occupancy_limit }}
                                {{ Str::plural('person', $unit->occupancy_limit) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Availability</p>
                            <p class="text-[14px] font-semibold text-[#1F2937]">{{ $unit->availability_status }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Submitted</p>
                            <p class="text-[14px] font-semibold text-[#1F2937]">{{ $unit->created_at->format('M j, Y') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-[#E2E8F0] rounded-3xl p-6 shadow-sm">
                    <h2 class="text-[15px] font-bold text-[#1F2937] mb-4">Verification Capture</h2>

                    @php
                        $photos = $unit->media->where('media_type', 'Image');
                        $video = $unit->media->where('media_type', 'Video')->first();
                    @endphp

                    @if($photos->isEmpty() && !$video)
                        <p class="text-[13px] text-[#94A3B8]">No media submitted for this unit.</p>
                    @else
                        @if($photos->isNotEmpty())
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-4">
                                @foreach($photos as $photo)
                                    <a href="{{ $photo->media_url }}" target="_blank"
                                        class="aspect-square rounded-xl overflow-hidden bg-[#F7FCFC] border border-[#E2E8F0] block">
                                        <img src="{{ $photo->media_url }}" alt="Unit photo" class="w-full h-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if($video)
                            <div class="rounded-xl overflow-hidden bg-[#F7FCFC] border border-[#E2E8F0] max-w-sm">
                                <video src="{{ $video->media_url }}" controls class="w-full h-auto"></video>
                            </div>
                        @endif
                    @endif
                </div>

            </div>

            {{-- Right: landlord card + actions --}}
            <div class="lg:col-span-4 space-y-5">

                <div class="bg-white border border-[#E2E8F0] rounded-3xl p-6 shadow-sm">
                    <h2 class="text-[15px] font-bold text-[#1F2937] mb-4">Landlord</h2>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                            <span class="text-[#156F8C] text-[13px] font-bold">
                                {{ strtoupper(substr($property->landlord->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($property->landlord->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                {{ $property->landlord->first_name ?? '' }} {{ $property->landlord->last_name ?? '' }}
                            </p>
                            <p class="text-[12px] text-[#94A3B8]">{{ $property->landlord->email ?? '—' }}</p>
                        </div>
                    </div>
                </div>
                @if($unit->isPending())
                    <div class="bg-white border border-[#E2E8F0] rounded-3xl p-6 shadow-sm space-y-3">
                        <h2 class="text-[15px] font-bold text-[#1F2937] mb-1">Decision</h2>

                        <form method="POST" action="{{ route('admin.units.approve', [$property, $unit]) }}"
                            data-confirm="Approve this unit?">
                            @csrf
                            <button type="submit"
                                class="w-full h-11 rounded-xl bg-[#22C55E] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all">
                                Approve Unit
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.units.reject', [$property, $unit]) }}"
                            data-confirm="Reject this unit?" data-confirm-type="error" class="space-y-2">
                            @csrf
                            <textarea name="rejection_reason" rows="3" maxlength="500"
                                placeholder="Reason for rejection (shown to landlord)"
                                class="w-full rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 text-[13px] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#EF4444]/25 transition"></textarea>
                            <button type="submit"
                                class="w-full h-11 rounded-xl border border-[#EF4444]/25 text-[#DC2626] text-[13.5px] font-semibold hover:bg-[#EF4444]/[0.07] transition-all">
                                Reject Unit
                            </button>
                        </form>
                    </div>
                @endif

            </div>

        </div>

    </div>
@endsection