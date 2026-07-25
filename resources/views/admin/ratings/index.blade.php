@extends('layouts.admin')

@section('page-title', 'Ratings')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-12">

        {{-- Header --}}
        <div class="flex items-center gap-3.5 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#FBBF24" stroke="none">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Overall Ratings</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Platform-wide averages across how tenants, landlords and properties are rated.</p>
            </div>
        </div>

        {{-- Headline --}}
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4 mb-4">
            <x-card class="flex flex-col items-center justify-center text-center !py-7">
                <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-2">Platform average</p>
                @if($platformAvg !== null)
                    <p class="text-5xl font-extrabold text-[#1F2937] leading-none">{{ number_format($platformAvg, 1) }}</p>
                    <div class="mt-3">
                        <x-star-rating :rating="$platformAvg" size="md" :show-value="false" />
                    </div>
                    <p class="text-[12px] text-[#64748B] mt-2">from {{ number_format($totalCount) }} ratings</p>
                @else
                    <p class="text-3xl font-extrabold text-[#94A3B8] leading-none">—</p>
                    <p class="text-[12px] text-[#64748B] mt-3">No ratings yet</p>
                @endif
            </x-card>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach($relationships as $rel)
                    <x-card class="!p-4 flex flex-col">
                        <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">{{ $rel['label'] }}</p>
                        <p class="text-[11.5px] text-[#94A3B8] mt-0.5 leading-snug">{{ $rel['sub'] }}</p>
                        <div class="mt-auto pt-3">
                            @if($rel['avg'] !== null)
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold text-[#1F2937]">{{ number_format($rel['avg'], 1) }}</span>
                                    <span class="text-[12px] text-[#64748B]">/ 5 · {{ $rel['count'] }}</span>
                                </div>
                                <div class="mt-1.5"><x-star-rating :rating="$rel['avg']" :show-value="false" /></div>
                            @else
                                <span class="text-2xl font-extrabold text-[#94A3B8]">—</span>
                                <p class="text-[11.5px] text-[#94A3B8] mt-1">No ratings yet</p>
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>
        </div>

        {{-- Distribution + trend --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            {{-- Distributions --}}
            <x-card>
                <h2 class="text-[15px] font-bold text-[#1F2937] mb-4">Rating distribution</h2>
                <div class="space-y-5">
                    @foreach($relationships as $rel)
                        @continue($rel['key'] === 'tenant_landlord') {{-- same source as tenant_property; don't double-draw --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[12.5px] font-semibold text-[#1F2937]">{{ $rel['label'] }}</p>
                                <p class="text-[11.5px] text-[#64748B]">{{ $rel['count'] }} ratings</p>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($rel['dist'] as $bar)
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex items-center gap-1 w-8 text-[11.5px] font-semibold text-[#64748B] shrink-0">
                                            {{ $bar['star'] }}
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="#FBBF24" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                                        </span>
                                        <div class="flex-1 h-2.5 rounded-full bg-[#EEF8F8] overflow-hidden">
                                            <div class="h-full rounded-full bg-[#2AA7A1]" style="width: {{ $bar['pct'] }}%"></div>
                                        </div>
                                        <span class="w-10 text-right text-[11.5px] text-[#64748B] shrink-0">{{ $bar['count'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Trend --}}
            <x-card>
                <h2 class="text-[15px] font-bold text-[#1F2937] mb-1">Average rating — last 6 months</h2>
                <p class="text-[12px] text-[#64748B] mb-4">Combined across all rating types, by the month each rating was left.</p>
                @php $hasTrend = collect($trend)->contains(fn ($p) => $p['value'] !== null); @endphp
                @if($hasTrend)
                    <div class="h-[220px]"><canvas id="ratingTrendChart"></canvas></div>
                @else
                    <div class="h-[220px] flex items-center justify-center text-[13px] text-[#94A3B8]">
                        Not enough dated ratings to chart a trend yet.
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Leaderboards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-rating-board title="Top-rated landlords" sub="From tenant reviews" :rows="$topLandlords" type="user"
                empty="No landlord has enough reviews yet." />
            <x-rating-board title="Lowest-rated landlords" sub="Needing attention" :rows="$lowLandlords" type="user"
                empty="No landlord has enough reviews yet." />
            <x-rating-board title="Top-rated tenants" sub="From landlord ratings" :rows="$topTenants" type="user"
                empty="No tenant has been rated yet." />
            <x-rating-board title="Top-rated properties" sub="From tenant reviews" :rows="$topProperties" type="property"
                empty="No property has enough reviews yet." />
        </div>

        @if($lowProperties->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                <x-rating-board title="Lowest-rated properties" sub="Needing attention" :rows="$lowProperties" type="property"
                    empty="No property has enough reviews yet." />
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('ratingTrendChart');
            if (!el || typeof Chart === 'undefined') return;

            new Chart(el, {
                type: 'line',
                data: {
                    labels: @json(collect($trend)->pluck('label')),
                    datasets: [{
                        data: @json(collect($trend)->pluck('value')),
                        borderColor: '#2AA7A1',
                        backgroundColor: 'rgba(42,167,161,0.10)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: '#2AA7A1',
                        pointRadius: 4,
                        spanGaps: true,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 5, ticks: { stepSize: 1, color: '#64748B' }, grid: { color: '#E2E8F0' } },
                        x: { ticks: { color: '#64748B' }, grid: { display: false } },
                    },
                },
            });
        });
    </script>
@endpush
