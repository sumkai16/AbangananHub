@extends('layouts.admin')

@section('page-title', 'Reports')

@section('content')
    <div class="max-w-[1600px] mx-auto">

        <x-page-header title="Reports" subtitle="Complaints against listings and users." />

        <x-admin.tabs class="mb-4" :tabs="collect(['Pending', 'Resolved', 'All'])->map(fn ($tab) => [
            'label' => $tab,
            'count' => $counts[$tab],
            'href' => route('admin.reports.index', ['status' => $tab]),
            'active' => $status === $tab,
        ])->all()" />

        <x-card flush>
            @if ($reports->isEmpty())
                <x-admin.empty title="No reports here" />
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
