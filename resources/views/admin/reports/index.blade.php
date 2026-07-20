@extends('layouts.admin')

@section('page-title', 'Reports')

@section('content')
    <div class="max-w-7xl">

        {{-- Page header --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">Reports</h1>
                <p class="text-sm text-[#64748B] mt-1">Review complaints submitted against listings and users.</p>
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
        <div class="grid grid-cols-3 gap-3 mb-6">
            @php
                $stats = [
                    'Pending' => ['label' => 'Pending', 'value' => $counts['Pending'], 'accent' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]'],
                    'Resolved' => ['label' => 'Resolved', 'value' => $counts['Resolved'], 'accent' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
                    'All' => ['label' => 'Total', 'value' => $counts['All'], 'accent' => 'text-[#156F8C]', 'dot' => 'bg-[#156F8C]'],
                ];
            @endphp
            @foreach ($stats as $key => $stat)
                <a href="{{ route('admin.reports.index', ['status' => $key]) }}"
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
            @foreach (['Pending', 'Resolved', 'All'] as $tab)
                <a href="{{ route('admin.reports.index', ['status' => $tab]) }}"
                    class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-200 whitespace-nowrap
                        {{ $status === $tab
                            ? 'bg-[#2AA7A1] text-white shadow-sm'
                            : 'text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC]' }}">
                    {{ $tab }}
                    <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-white/80' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
                </a>
            @endforeach
        </div>

        @if ($reports->isEmpty())
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-16 text-center shadow-lg">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-white/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <p class="text-[15px] font-bold text-[#1F2937]">No reports here</p>
                <p class="text-[13px] text-[#64748B] mt-1">No reports match this tab right now.</p>
            </div>
        @else
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden divide-y divide-[#E2E8F0]">
                @foreach ($reports as $report)
                    <a href="{{ route('admin.reports.show', $report) }}"
                        class="flex flex-wrap sm:flex-nowrap items-center gap-4 px-6 py-4 hover:bg-[#F7FCFC]/70 transition-all duration-200 group">
                        <div class="w-11 h-11 rounded-full bg-[#156F8C] flex items-center justify-center shrink-0">
                            <span class="text-white text-[13px] font-bold">
                                {{ strtoupper(substr($report->reporter->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($report->reporter->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1 basis-48">
                            <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">
                                {{ $report->reporter ? $report->reporter->first_name . ' ' . $report->reporter->last_name : '—' }}
                            </p>
                            <p class="text-[12px] text-[#64748B] truncate">{{ $report->reporter->email ?? '' }}</p>
                        </div>

                        <div class="min-w-0 flex-1 basis-40">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Target</p>
                            @if($report->property)
                                <p class="text-[13px] text-[#1F2937] font-medium truncate">Listing: {{ $report->property->title }}</p>
                            @elseif($report->reportedUser)
                                <p class="text-[13px] text-[#1F2937] font-medium truncate">User: {{ $report->reportedUser->first_name }} {{ $report->reportedUser->last_name }}</p>
                            @else
                                <p class="text-[13px] text-[#94A3B8]">—</p>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 basis-56">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Reason</p>
                            <p class="text-[13px] text-[#1F2937] truncate" title="{{ $report->report_reason }}">{{ $report->report_reason }}</p>
                        </div>

                        <div class="shrink-0">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full
                                {{ $report->isPending() ? 'bg-[#FBBF24]/15 text-[#B45309]' : 'bg-[#22C55E]/15 text-[#15803D]' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $report->isPending() ? 'bg-[#FBBF24]' : 'bg-[#22C55E]' }}"></span>
                                {{ $report->report_status }}
                            </span>
                        </div>

                        <div class="shrink-0 text-right w-24">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Submitted</p>
                            <p class="text-[13px] text-[#64748B]">{{ $report->created_at->format('M d, Y') }}</p>
                        </div>

                        <svg class="w-4 h-4 text-[#94A3B8] group-hover:text-[#2AA7A1] group-hover:translate-x-0.5 transition-all duration-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endforeach
            </div>
            @if ($reports->hasPages())
                <div class="mt-4 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-6 py-3 shadow-lg">
                    {{ $reports->links() }}
                </div>
            @endif
        @endif

    </div>
@endsection
