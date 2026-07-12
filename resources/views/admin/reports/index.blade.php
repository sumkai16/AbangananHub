@extends('layouts.admin')

@section('page-title', 'Reports')

@section('content')
    <div class="max-w-5xl">

        {{-- Page header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Reports</h1>
            <p class="text-[13.5px] text-gray-500 mt-1">Review complaints submitted against listings and users.</p>
        </div>

        {{-- Flash --}}
        @if(session('status'))
            <div
                class="mb-6 px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-[13.5px] font-medium">
                {{ session('status') }}
            </div>
        @endif

        {{-- Tabs --}}
        <div
            class="flex gap-1 bg-white border border-gray-100 rounded-2xl p-1 mb-6 w-fit max-w-full overflow-x-auto shadow-sm">
            @foreach (['Pending', 'Resolved', 'All'] as $tab)
                <a href="{{ route('admin.reports.index', ['status' => $tab]) }}" class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-150
                                    {{ $status === $tab
                ? 'bg-[#2AA7A1] text-white shadow-sm'
                : 'text-gray-500 hover:text-[#1A1A2E] hover:bg-gray-50' }}">
                    {{ $tab }}
                </a>
            @endforeach
        </div>

        @if ($reports->isEmpty())
            <div class="bg-white border border-gray-100 rounded-3xl p-16 text-center shadow-sm">
                <div
                    class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <p class="text-[15px] font-bold text-[#1A1A2E]">No reports here</p>
                <p class="text-[13px] text-gray-400 mt-1">No reports match this tab right now.</p>
            </div>
        @else
            <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                    <p class="text-[13px] font-semibold text-[#1A1A2E]">
                        {{ $reports->total() }} {{ Str::plural('report', $reports->total()) }}
                    </p>
                </div>
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50/60 border-b border-gray-100">
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Reporter</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Target</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Reason</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Status</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Submitted</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($reports as $report)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="text-[13.5px] font-semibold text-[#1A1A2E]">
                                            {{ $report->reporter ? $report->reporter->first_name . ' ' . $report->reporter->last_name : '—' }}
                                        </p>
                                        <p class="text-[12px] text-gray-400">{{ $report->reporter->email ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($report->property)
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-50 border border-gray-100 text-[11.5px] font-medium text-[#1A1A2E]">
                                                Listing: {{ $report->property->title }}
                                            </span>
                                        @elseif($report->reportedUser)
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-50 border border-gray-100 text-[11.5px] font-medium text-[#1A1A2E]">
                                                User: {{ $report->reportedUser->first_name }} {{ $report->reportedUser->last_name }}
                                            </span>
                                        @else
                                            <span class="text-[12px] text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 max-w-[220px]">
                                        <p class="text-[13px] text-[#1A1A2E] truncate" title="{{ $report->report_reason }}">
                                            {{ $report->report_reason }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full
                                                                {{ $report->isPending() ? 'bg-amber-50 text-amber-600 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
                                            <span
                                                class="w-1.5 h-1.5 rounded-full {{ $report->isPending() ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                            {{ $report->report_status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-[13px] text-gray-400">
                                        {{ $report->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('admin.reports.show', $report) }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-100 text-[12px] font-semibold text-[#1A1A2E] hover:bg-[#2AA7A1] hover:text-white hover:border-[#2AA7A1] transition-all">
                                            Review
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($reports->hasPages())
                    <div class="px-6 py-4 border-t border-gray-50">
                        {{ $reports->links() }}
                    </div>
                @endif
            </div>
        @endif

    </div>
@endsection