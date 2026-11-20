@extends('layouts.admin')

@section('page-title', 'Search insights')

@section('content')
<div class="max-w-[1400px] mx-auto">

    <x-page-header icon-path="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" title="Search insights" subtitle="What tenants search for, what it costs, and where nothing was found. Search text can hold personal details: this page is for admins only and the log is kept 30 days." />

    <div class="mb-4 flex items-center gap-2">
        @foreach($dayOptions as $option)
            <a href="{{ route('admin.search-insights.index', ['days' => $option]) }}"
                class="inline-flex h-9 items-center rounded-xl border px-3.5 text-[13px] font-semibold transition-colors {{ $days === $option ? 'border-[#060D26] bg-[#060D26] text-white' : 'border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#F7F8FC]' }}">Last {{ $option }} days</a>
        @endforeach
    </div>

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'Searches', 'value' => number_format($stats['total']), 'note' => 'In this period'],
        ['label' => 'Read by the AI', 'value' => $stats['ai_share'] . '%', 'note' => 'The rest used keyword search'],
        ['label' => 'Found nothing exact', 'value' => $stats['zero_rate'] . '%', 'note' => 'No result at all'],
        ['label' => 'Time to answer', 'value' => number_format($stats['avg_ms']) . ' ms', 'note' => 'Slowest 5%: ' . number_format($stats['p95_ms']) . ' ms'],
        ['label' => 'AI cost', 'value' => '$' . number_format($stats['cost'], 2), 'note' => '$' . $stats['cost_per_search'] . ' a search'],
    ]" />

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 font-jakarta text-[15px] font-bold text-[#060D26]">Searches and cost by day</h2>
            <table class="w-full text-[13.5px]">
                <thead class="text-left text-[12px] uppercase tracking-[0.04em] text-[#5B6A8E]"><tr><th class="py-1.5">Day</th><th class="py-1.5 text-right">Searches</th><th class="py-1.5 text-right">Cost</th></tr></thead>
                <tbody>
                    @forelse($perDay as $day => $row)
                        <tr class="border-t border-[#F1F2F7]"><td class="py-1.5">{{ $day }}</td><td class="py-1.5 text-right">{{ $row['n'] }}</td><td class="py-1.5 text-right">${{ number_format($row['cost'], 4) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-[#5B6A8E]">No searches in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-3 text-[13px] text-[#5B6A8E]">Helpful: {{ $stats['up'] }} · Not helpful: {{ $stats['down'] }}</p>
        </x-card>

        <x-card>
            <h2 class="mb-3 font-jakarta text-[15px] font-bold text-[#060D26]">Why the AI was not used</h2>
            <table class="w-full text-[13.5px]">
                <tbody>
                    @forelse($fallbacks as $reason => $n)
                        <tr class="border-t border-[#F1F2F7] first:border-0"><td class="py-1.5">{{ str_replace('_', ' ', $reason) }}</td><td class="py-1.5 text-right">{{ $n }}</td></tr>
                    @empty
                        <tr><td class="py-3 text-[#5B6A8E]">Every search was read by the AI.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <h3 class="mb-2 mt-5 text-[13px] font-semibold text-[#060D26]">Most relaxed filter</h3>
            <table class="w-full text-[13.5px]">
                <tbody>
                    @forelse($relaxed as $step => $n)
                        <tr class="border-t border-[#F1F2F7] first:border-0"><td class="py-1.5">{{ $step }}</td><td class="py-1.5 text-right">{{ $n }}</td></tr>
                    @empty
                        <tr><td class="py-3 text-[#5B6A8E]">No search needed relaxing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card>
            <h2 class="mb-1 font-jakarta text-[15px] font-bold text-[#060D26]">Supply gaps</h2>
            <p class="mb-3 text-[13px] text-[#5B6A8E]">Searches with a type and a place that found no exact match: places landlords could list.</p>
            <table class="w-full text-[13.5px]">
                <tbody>
                    @forelse($gaps as $gap => $n)
                        <tr class="border-t border-[#F1F2F7] first:border-0"><td class="py-1.5">{{ $gap }}</td><td class="py-1.5 text-right">{{ $n }} {{ \Illuminate\Support\Str::plural('search', $n) }}</td></tr>
                    @empty
                        <tr><td class="py-3 text-[#5B6A8E]">No gaps found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card>
            <h2 class="mb-3 font-jakarta text-[15px] font-bold text-[#060D26]">Searches that found nothing</h2>
            <table class="w-full text-[13.5px]">
                <tbody>
                    @forelse($zeroQueries as $query => $n)
                        <tr class="border-t border-[#F1F2F7] first:border-0"><td class="py-1.5 pr-3 break-words">{{ $query }}</td><td class="py-1.5 text-right whitespace-nowrap">{{ $n }}×</td></tr>
                    @empty
                        <tr><td class="py-3 text-[#5B6A8E]">Every search found something.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card>
            <h2 class="mb-1 font-jakarta text-[15px] font-bold text-[#060D26]">Wishes we cannot filter on yet</h2>
            <p class="mb-3 text-[13px] text-[#5B6A8E]">What the AI understood but no filter checks. The most asked-for are the next ones to build.</p>
            <table class="w-full text-[13.5px]">
                <tbody>
                    @forelse($unmapped as $wish => $n)
                        <tr class="border-t border-[#F1F2F7] first:border-0"><td class="py-1.5 pr-3 break-words">{{ $wish }}</td><td class="py-1.5 text-right">{{ $n }}×</td></tr>
                    @empty
                        <tr><td class="py-3 text-[#5B6A8E]">Nothing left unmapped.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card>
            <h2 class="mb-1 font-jakarta text-[15px] font-bold text-[#060D26]">Places we did not know</h2>
            <p class="mb-3 text-[13px] text-[#5B6A8E]">Place names that are not a landmark, area or city. Good candidates for <code>config/landmarks.php</code>.</p>
            <table class="w-full text-[13.5px]">
                <tbody>
                    @forelse($unknownPlaces as $place => $n)
                        <tr class="border-t border-[#F1F2F7] first:border-0"><td class="py-1.5 pr-3 break-words">{{ $place }}</td><td class="py-1.5 text-right">{{ $n }}×</td></tr>
                    @empty
                        <tr><td class="py-3 text-[#5B6A8E]">Every place was known.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>

    <x-card class="mt-5">
        <h2 class="font-jakarta text-[15px] font-bold text-[#060D26]">Turn a real search into a test</h2>
        <p class="mb-3 mt-1 text-[13px] text-[#5B6A8E]">Correct what the search should have understood, then save it. <code>php artisan ai-search:export-fixtures</code> adds the saved ones ({{ $reviewed }} so far) to the sentences <code>ai-search:eval</code> scores.</p>
        @if(session('success'))<p class="mb-3 rounded-xl bg-[#EAF8EF] px-3 py-2 text-[13px] font-semibold text-[#166534]">{{ session('success') }}</p>@endif
        @error('intent')<p class="mb-3 rounded-xl bg-[#FEF6E4] px-3 py-2 text-[13px] font-semibold text-[#8A5300]">{{ $message }}</p>@enderror
        <div class="space-y-4">
            @forelse($toReview as $log)
                <form method="POST" action="{{ route('admin.search-insights.review') }}" class="rounded-xl border border-[#E2E4EC] p-3">
                    @csrf
                    <input type="hidden" name="log_id" value="{{ $log->id }}">
                    <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $log->query }}</p>
                    <label for="intent-{{ $log->id }}" class="sr-only">Corrected search as JSON</label>
                    <textarea id="intent-{{ $log->id }}" name="intent" rows="4" class="mt-2 w-full rounded-xl border border-[#E2E4EC] p-2.5 font-mono text-[12px] text-[#060D26]">{{ json_encode(collect($log->intent)->only(['location', 'property_type', 'price_min', 'price_max', 'amenities', 'rules', 'for', 'furnishing', 'sort', 'bedrooms_min', 'deposit_max', 'occupants'])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
                    <button type="submit" class="mt-2 inline-flex h-9 items-center rounded-xl bg-[#FF8A66] px-4 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F]">Save as test case</button>
                </form>
            @empty
                <p class="text-[13.5px] text-[#5B6A8E]">No search without an exact match in this period.</p>
            @endforelse
        </div>
    </x-card>
</div>
@endsection
