@extends('layouts.admin')

@section('page-title', 'Unit Approvals')

@section('content')
    <div class="max-w-7xl">

        {{-- Page header --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">Unit Approvals</h1>
                <p class="text-sm text-[#64748B] mt-1">Review rental units submitted by landlords for verification.</p>
            </div>
            @if ($counts['Pending'] > 0)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FBBF24]/15 px-3 py-1.5 text-xs font-semibold text-[#92600A]">
                    <svg class="h-3.5 w-3.5 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                    </svg>
                    {{ $counts['Pending'] }} awaiting review
                </span>
            @endif
        </div>

        {{-- Stat summary --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            @php
                $stats = [
                    'Pending' => ['label' => 'Pending', 'value' => $counts['Pending'], 'accent' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]'],
                    'Approved' => ['label' => 'Approved', 'value' => $counts['Approved'], 'accent' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
                    'Rejected' => ['label' => 'Rejected', 'value' => $counts['Rejected'], 'accent' => 'text-[#B91C1C]', 'dot' => 'bg-[#EF4444]'],
                    'All' => ['label' => 'Total', 'value' => $counts['All'], 'accent' => 'text-[#156F8C]', 'dot' => 'bg-[#156F8C]'],
                ];
            @endphp
            @foreach ($stats as $key => $stat)
                <a href="{{ route('admin.units.index', ['status' => $key]) }}"
                    class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-4 py-3.5 shadow-lg transition-all duration-200 hover:shadow-xl {{ $status === $key ? 'ring-2 ring-[#2AA7A1]' : '' }}">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ $stat['dot'] }}"></span>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">{{ $stat['label'] }}</p>
                    </div>
                    <p class="text-2xl font-bold {{ $stat['accent'] }} mt-1">{{ $stat['value'] }}</p>
                </a>
            @endforeach
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-1 mb-5 w-fit max-w-full overflow-x-auto shadow-lg">
            @foreach (['Pending', 'Approved', 'Rejected', 'All'] as $tab)
                <a href="{{ route('admin.units.index', ['status' => $tab]) }}"
                    class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-200 whitespace-nowrap
                        {{ $status === $tab
                            ? 'bg-[#2AA7A1] text-white shadow-sm'
                            : 'text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC]' }}">
                    {{ $tab }}
                    <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-white/80' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
                </a>
            @endforeach
        </div>

        @if ($units->isEmpty())
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-16 text-center shadow-lg">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-white/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm9.75 0A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm-9.75 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75 0a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                </div>
                <p class="text-[15px] font-bold text-[#1F2937]">No units here</p>
                <p class="text-[13px] text-[#64748B] mt-1">No units match this tab right now.</p>
            </div>
        @else
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden divide-y divide-[#E2E8F0]">
                @foreach ($units as $unit)
                    @php
                        $thumb = $unit->media->firstWhere('media_type', 'Image');
                    @endphp
                    <a href="{{ route('admin.units.show', [$unit->property, $unit]) }}"
                        class="flex flex-wrap sm:flex-nowrap items-center gap-4 px-6 py-4 hover:bg-[#F7FCFC]/70 transition-all duration-200 group">
                        <div class="w-11 h-11 rounded-xl bg-[#EEF8F8] overflow-hidden shrink-0 flex items-center justify-center">
                            @if($thumb)
                                <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" loading="lazy" class="w-full h-full object-cover">
                            @else
                                <svg class="w-5 h-5 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                                </svg>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 basis-48">
                            <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">{{ $unit->unit_label }}</p>
                            <p class="text-[12px] text-[#64748B] truncate">{{ $unit->property->title ?? '—' }}</p>
                        </div>

                        <div class="min-w-0 flex-1 basis-40">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Landlord</p>
                            <p class="text-[13px] text-[#1F2937] font-medium truncate">
                                {{ trim(($unit->property->landlord->first_name ?? '').' '.($unit->property->landlord->last_name ?? '')) ?: '—' }}
                            </p>
                        </div>

                        <div class="shrink-0 w-24">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Rent</p>
                            <p class="text-[13px] text-[#1F2937] font-medium">₱{{ number_format($unit->rental_fee, 2) }}</p>
                        </div>

                        <div class="shrink-0">
                            <x-verification-status-badge :status="$unit->verification_status" />
                        </div>

                        <svg class="w-4 h-4 text-[#94A3B8] group-hover:text-[#2AA7A1] group-hover:translate-x-0.5 transition-all duration-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endforeach
            </div>
            @if ($units->hasPages())
                <div class="mt-4 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-6 py-3 shadow-lg">
                    {{ $units->links() }}
                </div>
            @endif
        @endif

    </div>
@endsection
