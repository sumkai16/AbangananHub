@extends('layouts.admin')

@section('page-title', 'Ratings')

@section('content')
    <div class="max-w-[1600px] mx-auto">

        <x-page-header icon-path="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" title="Ratings" subtitle="Platform-wide averages across how tenants, landlords and properties are rated." />

        {{-- Headline --}}
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4 mb-4">
            <x-card class="flex flex-col items-center justify-center text-center !py-7">
                <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide mb-2">Platform rating</p>
                @if($platformAvg !== null)
                    <p class="text-5xl font-extrabold text-[#060D26] leading-none">{{ number_format($platformAvg, 1) }}</p>
                    <div class="mt-3">
                        <x-star-rating :rating="$platformAvg" size="md" :show-value="false" />
                    </div>
                    <p class="text-[12px] text-[#5B6A8E] mt-2">from {{ number_format($totalCount) }} ratings</p>
                @else
                    <p class="text-3xl font-extrabold text-[#5B6A8E] leading-none">—</p>
                    <p class="text-[12px] text-[#5B6A8E] mt-3">No ratings yet</p>
                @endif
            </x-card>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach($relationships as $rel)
                    <x-card class="!p-4 flex flex-col">
                        <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">{{ $rel['label'] }}</p>
                        <p class="text-[11.5px] text-[#5B6A8E] mt-0.5 leading-snug">{{ $rel['sub'] }}</p>
                        <div class="mt-auto pt-3">
                            @if($rel['avg'] !== null)
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold text-[#060D26]">{{ number_format($rel['avg'], 1) }}</span>
                                    <span class="text-[12px] text-[#5B6A8E]">/ 5 · {{ $rel['count'] }}</span>
                                </div>
                                <div class="mt-1.5"><x-star-rating :rating="$rel['avg']" :show-value="false" /></div>
                            @else
                                <span class="text-2xl font-extrabold text-[#5B6A8E]">—</span>
                                <p class="text-[11.5px] text-[#5B6A8E] mt-1">No ratings yet</p>
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>
        </div>

        {{-- Ratings of AbangananHub itself (landing page / footer form) --}}
        <x-card class="mb-4">
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="text-[15px] font-semibold text-[#060D26]">Platform feedback</h2>
                <p class="text-[12px] text-[#5B6A8E]">
                    @if($totalCount > 0) Latest {{ $platformFeedback->count() }} of {{ number_format($totalCount) }} @else No feedback yet @endif
                </p>
            </div>
            @if($totalCount > 0)
                <div class="mb-3 space-y-1.5">
                    @foreach($feedbackDist as $bar)
                        <div class="flex items-center gap-2.5">
                            <span class="flex w-8 shrink-0 items-center gap-1 text-[11.5px] font-semibold text-[#5B6A8E]">{{ $bar['star'] }}
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="#FBBF24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                            </span>
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-[#ECEEF6]"><div class="h-full rounded-full bg-[#FF8A66]" style="width: {{ $bar['pct'] }}%"></div></div>
                            <span class="w-10 shrink-0 text-right text-[11.5px] text-[#5B6A8E]">{{ $bar['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            @forelse($platformFeedback as $f)
                <div class="flex items-start justify-between gap-3 border-t border-[#E2E4EC] py-2.5">
                    <div class="min-w-0">
                        <p class="text-[13px] font-semibold text-[#060D26]">{{ $f->user?->first_name }} {{ $f->user?->last_name }} <span class="font-normal text-[#5B6A8E]">· {{ $f->updated_at->diffForHumans() }}</span></p>
                        @if($f->comment)<p class="mt-0.5 text-[13px] text-[#5B6A8E]">{{ $f->comment }}</p>@endif
                    </div>
                    <x-star-rating :rating="$f->rating" :show-value="false" />
                </div>
            @empty
                <p class="text-[13px] text-[#5B6A8E]">Ratings from the landing page will show up here.</p>
            @endforelse
        </x-card>

        {{-- Distribution + trend --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            {{-- Distributions --}}
            <x-card>
                <h2 class="text-[15px] font-semibold text-[#060D26] mb-4">Rating distribution</h2>
                <div class="space-y-5">
                    @foreach($relationships as $rel)
                        @continue($rel['key'] === 'tenant_landlord') {{-- same source as tenant_property; don't double-draw --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[12.5px] font-semibold text-[#060D26]">{{ $rel['label'] }}</p>
                                <p class="text-[11.5px] text-[#5B6A8E]">{{ $rel['count'] }} ratings</p>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($rel['dist'] as $bar)
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex items-center gap-1 w-8 text-[11.5px] font-semibold text-[#5B6A8E] shrink-0">
                                            {{ $bar['star'] }}
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="#FBBF24" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                                        </span>
                                        <div class="flex-1 h-2.5 rounded-full bg-[#ECEEF6] overflow-hidden">
                                            <div class="h-full rounded-full bg-[#FF8A66]" style="width: {{ $bar['pct'] }}%"></div>
                                        </div>
                                        <span class="w-10 text-right text-[11.5px] text-[#5B6A8E] shrink-0">{{ $bar['count'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Trend --}}
            <x-card>
                <h2 class="text-[15px] font-semibold text-[#060D26] mb-1">Average rating — last 6 months</h2>
                <p class="text-[12px] text-[#5B6A8E] mb-4">Combined across all rating types, by the month each rating was left.</p>
                @php $hasTrend = collect($trend)->contains(fn ($p) => $p['value'] !== null); @endphp
                @if($hasTrend)
                    <div class="h-[220px]"><canvas id="ratingTrendChart"></canvas></div>
                @else
                    <div class="h-[220px] flex items-center justify-center text-[13px] text-[#5B6A8E]">
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
    @vite('resources/js/charts.js')
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
                        borderColor: '#FF8A66',
                        backgroundColor: 'rgba(255, 138, 102,0.10)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: '#FF8A66',
                        pointRadius: 4,
                        spanGaps: true,
                    }],
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 5, ticks: { stepSize: 1, color: '#5B6A8E' }, grid: { color: '#E2E4EC' } },
                        x: { ticks: { color: '#5B6A8E' }, grid: { display: false } },
                    },
                },
            });
        });
    </script>
@endpush
