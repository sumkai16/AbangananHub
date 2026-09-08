@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="min-h-[calc(100vh-72px)]">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="mb-6">
                <h1 class="text-[20px] font-normal text-[#060D26]">My Reports</h1>
                <p class="text-[13px] text-[#5B6A8E] mt-1">Track the status of reports you have filed.</p>
            </div>

            {{-- Stat cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E4EC] p-4">
                    <p class="text-[12px] text-[#5B6A8E]">Total reports</p>
                    <p class="text-[22px] font-bold text-[#060D26]">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E4EC] p-4">
                    <p class="text-[12px] text-[#5B6A8E]">Pending</p>
                    <p class="text-[22px] font-bold text-[#060D26]">{{ $stats['pending'] }}</p>
                </div>
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E4EC] p-4">
                    <p class="text-[12px] text-[#5B6A8E]">Resolved</p>
                    <p class="text-[22px] font-bold text-[#060D26]">{{ $stats['resolved'] }}</p>
                </div>
            </div>

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('tenant.reports.index') }}"
                class="flex flex-col sm:flex-row sm:items-center gap-2 mb-6">
                <x-styled-select name="status" :options="['' => 'All statuses', 'Pending' => 'Pending', 'Resolved' => 'Resolved']"
                    :selected="request('status', '')"
                    class="w-full sm:w-48 pl-4 pr-8 py-2.5 rounded-xl border border-[#E2E4EC] bg-white text-[13px] text-[#060D26]" />

                <button type="submit"
                    class="bg-[#060D26] text-white rounded-xl px-4 py-2.5 text-[13px] font-semibold hover:brightness-95 transition">
                    Filter
                </button>
            </form>

            {{-- Reports table --}}
            @if($reports->isEmpty())
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E4EC] p-12 flex flex-col items-center text-center">
                    <svg class="w-10 h-10 text-[#5B6A8E] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 3v18m0-13.5h13.5c.621 0 .659.34.286.836L14.25 11.25l2.536 2.914c.373.496.335.836-.286.836H3" />
                    </svg>
                    <p class="text-[14px] font-semibold text-[#060D26]">No reports filed</p>
                    <p class="text-[13px] text-[#5B6A8E] mt-1">Reports you submit will be tracked here.</p>
                </div>
            @else
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E4EC] overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-[#ECEEF6]">
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#060D26]">Target</th>
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#060D26]">Reason</th>
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#060D26]">Status</th>
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#060D26]">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E4EC]">
                                @foreach($reports as $report)
                                    <tr class="hover:bg-[#F7F8FC] transition">
                                        <td class="px-5 py-3.5 text-[13px] text-[#060D26]">
                                            @if($report->property)
                                                {{ $report->property->title }}
                                                <span class="text-[#5B6A8E]">· Listing</span>
                                            @elseif($report->reportedUser)
                                                {{ $report->reportedUser->first_name }} {{ $report->reportedUser->last_name }}
                                                <span class="text-[#5B6A8E]">· User</span>
                                            @else
                                                <span class="text-[#5B6A8E]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <p class="text-[13px] text-[#060D26]">{{ Str::limit($report->report_reason, 50) }}</p>
                                            @if($report->report_status === 'Resolved' && $report->admin_notes)
                                                <p class="text-[11px] text-[#5B6A8E] mt-1">Admin: {{ $report->admin_notes }}</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            @if($report->report_status === 'Resolved')
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-[#F0FDF4] text-[#166534] text-[11px] font-semibold">
                                                    Resolved
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-[#FFFBEB] text-[#92400E] text-[11px] font-semibold">
                                                    Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-[13px] text-[#5B6A8E]">
                                            {{ $report->created_at->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination --}}
                <div class="mt-6">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
