@extends('layouts.admin')

@section('page-title', 'Reports')

@section('content')
    <div class="max-w-[1600px] mx-auto">

        <x-page-header title="Reports" subtitle="Complaints against listings and users." />

        @php
            $statusOptions = ['Pending' => 'Pending ('.$counts['Pending'].')', 'Resolved' => 'Resolved ('.$counts['Resolved'].')', 'All' => 'All ('.$counts['All'].')'];
            $cellHref = fn (array $params) => route('admin.reports.index', array_filter(array_merge(['search' => $search], $params)));
            $summary = [
                ['label' => 'Total reports', 'value' => number_format($counts['All']), 'note' => 'Complaints against listings and users', 'href' => $status === 'All' ? $cellHref([]) : $cellHref(['status' => 'All']), 'active' => $status === 'All'],
                ['label' => 'Pending', 'value' => number_format($counts['Pending']), 'note' => $counts['Pending'] > 0 ? 'Waiting for a decision' : 'Nothing waiting', 'dot' => '#FBBF24', 'href' => $status === 'Pending' ? $cellHref(['status' => 'All']) : $cellHref(['status' => 'Pending']), 'active' => $status === 'Pending'],
                ['label' => 'Resolved', 'value' => number_format($counts['Resolved']), 'note' => 'Handled', 'dot' => '#22C55E', 'href' => $status === 'Resolved' ? $cellHref(['status' => 'All']) : $cellHref(['status' => 'Resolved']), 'active' => $status === 'Resolved'],
            ];
        @endphp
        <x-stat-strip :cells="$summary" class="mb-4" />

        <form data-live method="GET" action="{{ route('admin.reports.index') }}" class="mb-4">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search reporter, listing, user or reason…" aria-label="Search reports"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select autosubmit name="status" :options="$statusOptions" :selected="$status"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                    @if($search || $status !== 'Pending')
                        <a href="{{ route('admin.reports.index') }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </a>
                    @endif
                </div>
            </div>
        </form>

        <x-card flush>
            @if ($reports->isEmpty())
                <x-admin.empty title="No reports here" :note="$search ? 'Try a different search.' : null" />
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                <x-admin.th>Reported by</x-admin.th>
                                <x-admin.th>Target</x-admin.th>
                                <x-admin.th>Reason</x-admin.th>
                                <x-admin.th>Status</x-admin.th>
                                <x-admin.th>Submitted</x-admin.th>
                                <th class="px-4 py-2.5"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach ($reports as $report)
                                <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                    <td class="px-4 py-3">
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $report->reporter ? $report->reporter->first_name . ' ' . $report->reporter->last_name : '—' }}</p>
                                        <p class="text-[12.5px] text-[#5B6A8E]">{{ $report->reporter->email ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-[13.5px] text-[#060D26]">
                                        @if($report->property)
                                            Listing: {{ $report->property->title }}
                                        @elseif($report->reportedUser)
                                            User: {{ $report->reportedUser->first_name }} {{ $report->reportedUser->last_name }}
                                        @else
                                            <span class="text-[#5B6A8E]">—</span>
                                        @endif
                                    </td>
                                    <td class="max-w-[24rem] truncate px-4 py-3 text-[13.5px] text-[#060D26]" title="{{ $report->report_reason }}">{{ $report->report_reason }}</td>
                                    <td class="px-4 py-3"><x-admin.status :status="$report->report_status" /></td>
                                    <td class="px-4 py-3 text-[13px] whitespace-nowrap text-[#5B6A8E]">{{ $report->created_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.reports.show', $report) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">Review</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($reports->hasPages())
                    <div class="border-t border-[#E2E4EC] px-4 py-3">{{ $reports->links() }}</div>
                @endif
            @endif
        </x-card>

    </div>
@endsection
