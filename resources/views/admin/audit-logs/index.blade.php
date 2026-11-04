@extends('layouts.admin')

@section('page-title', 'Audit Logs')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" title="Audit Logs" subtitle="Every consequential admin action, recorded and unalterable." />

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'Actions', 'value' => number_format($stats['total']), 'note' => 'Matching these filters'],
        ['label' => 'Today', 'value' => number_format($stats['today']), 'note' => 'Last 24 hours'],
        ['label' => 'Actors', 'value' => number_format($stats['actors']), 'note' => 'Distinct actors'],
        ['label' => 'Destructive', 'value' => number_format($stats['destructive']), 'note' => 'Money, deletions, overrides'],
    ]" />

    {{-- Search + filters --}}
    <form method="GET" action="{{ route('admin.audit-logs.index') }}"
        class="mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <label for="audit-search" class="sr-only">Search audit logs</label>
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" id="audit-search" value="{{ $search }}"
                    placeholder="Search by admin, action, or reason…"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select name="action" :options="$actionOptions" :selected="$action"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[220px]" />

                <div>
                    <label for="audit-from" class="sr-only">From date</label>
                    <x-date-picker name="from" id="audit-from" value="{{ $from }}" placeholder="From" class="w-full lg:w-36" />
                </div>
                <div>
                    <label for="audit-to" class="sr-only">To date</label>
                    <x-date-picker name="to" id="audit-to" value="{{ $to }}" placeholder="To" class="w-full lg:w-36" />
                </div>

                <button type="submit" class="h-10 rounded-xl border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Filter</button>

                @if($search || $action || $from || $to)
                    <a href="{{ route('admin.audit-logs.index') }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if($logs->isEmpty())
        <x-card flush><x-admin.empty title="No audit entries found" :note="$search || $action || $from || $to ? 'Try a different search or filter.' : null" /></x-card>
    @else
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>When</x-admin.th>
                            <x-admin.th>Actor</x-admin.th>
                            <x-admin.th>Action</x-admin.th>
                            <x-admin.th>Details</x-admin.th>
                            <x-admin.th>IP</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($logs as $log)
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200 align-top">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-[12.5px] font-semibold text-[#060D26]">{{ $log->created_at?->format('M j, Y') }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">{{ $log->created_at?->format('g:i A') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[12.5px] font-semibold text-[#060D26]">{{ $log->actor_name }}</p>
                                    <p class="text-[11px] text-[#5B6A8E] truncate max-w-[180px]">{{ $log->actor_email }}</p>
                                    @unless($log->actor_id)
                                        {{-- The FK is ON DELETE SET NULL so the trail survives the account. --}}
                                        <p class="text-[10.5px] text-[#B45309] mt-0.5">Account deleted</p>
                                    @endunless
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$log->actionLabel()" :tone="$log->isDestructive() ? 'red' : 'neutral'" />
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[12.5px] text-[#060D26]">{{ $log->summary }}</p>
                                    @if($log->reason)
                                        <p class="text-[11px] text-[#5B6A8E] mt-0.5">Reason: {{ $log->reason }}</p>
                                    @endif
                                    @if($log->metadata)
                                        <p class="text-[11px] text-[#5B6A8E] mt-0.5">
                                            @foreach($log->metadata as $key => $value)
                                                <span class="inline-block mr-2">
                                                    {{ Str::headline($key) }}:
                                                    <span class="font-semibold text-[#060D26]">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                                                </span>
                                            @endforeach
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[11.5px] text-[#5B6A8E] whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-t border-[#E2E4EC]">
                <p class="text-[12.5px] text-[#5B6A8E]">
                    Showing <span class="font-semibold text-[#060D26]">{{ $logs->firstItem() }}–{{ $logs->lastItem() }}</span> of
                    <span class="font-semibold text-[#060D26]">{{ $logs->total() }}</span> entries
                </p>
                {{ $logs->links() }}
            </div>
        </x-card>
    @endif

</div>

<script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
