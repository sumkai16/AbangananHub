@extends('layouts.admin')

@section('page-title', 'Unit Approvals')

@section('content')
    <div class="max-w-[1600px] mx-auto">

        {{-- Page header --}}
        <x-page-header title="Unit Approvals" subtitle="Review rental units submitted by landlords for verification.">
            @if ($counts['Pending'] > 0)
                <x-slot:actions>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FBBF24]/15 px-3 py-1.5 text-xs font-semibold text-[#92600A]">
                        <svg class="h-3.5 w-3.5 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                        </svg>
                        {{ $counts['Pending'] }} awaiting review
                    </span>
                </x-slot:actions>
            @endif
        </x-page-header>

        {{-- Stat summary --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            @php
                $stats = [
                    'Pending' => ['label' => 'Pending', 'value' => $counts['Pending'], 'valueColor' => '#B45309', 'iconBg' => 'rgba(251,191,36,0.10)', 'iconColor' => '#B45309'],
                    'Approved' => ['label' => 'Approved', 'value' => $counts['Approved'], 'valueColor' => '#15803D', 'iconBg' => 'rgba(34,197,94,0.07)', 'iconColor' => '#059669'],
                    'Rejected' => ['label' => 'Rejected', 'value' => $counts['Rejected'], 'valueColor' => '#B91C1C', 'iconBg' => 'rgba(239,68,68,0.07)', 'iconColor' => '#DC2626'],
                    'All' => ['label' => 'Total', 'value' => $counts['All'], 'valueColor' => '#156F8C', 'iconBg' => '#EEF8F8', 'iconColor' => '#156F8C'],
                ];
            @endphp
            @foreach ($stats as $key => $stat)
                <x-stat-card :label="$stat['label']" :value="$stat['value']" :value-color="$stat['valueColor']" :icon-bg="$stat['iconBg']"
                    :href="route('admin.units.index', ['status' => $key])"
                    :class="$status === $key ? 'ring-2 ring-[#2AA7A1]' : ''">
                    <x-slot:icon>
                        @switch($key)
                            @case('Pending')
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                @break
                            @case('Approved')
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                @break
                            @case('Rejected')
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                @break
                            @default
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M9 17V9m4 8V5m4 12v-6" />
                                </svg>
                        @endswitch
                    </x-slot:icon>
                </x-stat-card>
            @endforeach
        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-0.5 border-b border-[#E2E8F0] mb-6 overflow-x-auto">
            @foreach (['Pending', 'Approved', 'Rejected', 'All'] as $tab)
                <a href="{{ route('admin.units.index', ['status' => $tab]) }}"
                    class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                        {{ $status === $tab ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                    {{ $tab }}
                    <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
                </a>
            @endforeach
        </div>

        @if ($units->isEmpty())
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm9.75 0A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm-9.75 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75 0a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                </div>
                <p class="text-[15px] font-bold text-[#1F2937]">No units here</p>
                <p class="text-[13px] text-[#64748B] mt-1">No units match this tab right now.</p>
            </div>
        @else
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden divide-y divide-[#E2E8F0]">
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
                <div class="mt-4 bg-white border border-[#E2E8F0] rounded-2xl px-6 py-3 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                    {{ $units->links() }}
                </div>
            @endif
        @endif

    </div>
@endsection
