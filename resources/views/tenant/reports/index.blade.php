@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="min-h-[calc(100vh-72px)]">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="mb-6">
                <h1 class="text-[20px] font-bold text-[#1F2937]">My Reports</h1>
                <p class="text-[13px] text-[#64748B] mt-1">Track the status of reports you have filed.</p>
            </div>

            {{-- Stat cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E8F0] p-4">
                    <p class="text-[12px] text-[#64748B]">Total reports</p>
                    <p class="text-[22px] font-bold text-[#1F2937]">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E8F0] p-4">
                    <p class="text-[12px] text-[#64748B]">Pending</p>
                    <p class="text-[22px] font-bold text-[#1F2937]">{{ $stats['pending'] }}</p>
                </div>
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E8F0] p-4">
                    <p class="text-[12px] text-[#64748B]">Resolved</p>
                    <p class="text-[22px] font-bold text-[#1F2937]">{{ $stats['resolved'] }}</p>
                </div>
            </div>

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('tenant.reports.index') }}"
                class="flex flex-col sm:flex-row sm:items-center gap-2 mb-6">
                <select name="status"
                    class="w-full sm:w-48 pl-4 pr-8 py-2.5 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:border-[#2AA7A1]">
                    <option value="">All statuses</option>
                    <option value="Pending" @selected(request('status') === 'Pending')>Pending</option>
                    <option value="Resolved" @selected(request('status') === 'Resolved')>Resolved</option>
                </select>

                <button type="submit"
                    class="bg-[#2AA7A1] text-white rounded-xl px-4 py-2.5 text-[13px] font-semibold hover:brightness-95 transition">
                    Filter
                </button>
            </form>

            {{-- Reports table --}}
            @if($reports->isEmpty())
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E8F0] p-12 flex flex-col items-center text-center">
                    <svg class="w-10 h-10 text-[#64748B] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 3v18m0-13.5h13.5c.621 0 .659.34.286.836L14.25 11.25l2.536 2.914c.373.496.335.836-.286.836H3" />
                    </svg>
                    <p class="text-[14px] font-semibold text-[#1F2937]">No reports filed</p>
                    <p class="text-[13px] text-[#64748B] mt-1">Reports you submit will be tracked here.</p>
                </div>
            @else
                <div class="bg-[#FFFFFF] rounded-2xl border border-[#E2E8F0] overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-[#EEF8F8]">
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Target</th>
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Reason</th>
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Status</th>
                                    <th class="px-5 py-3 text-[12px] font-semibold text-[#156F8C]">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                @foreach($reports as $report)
                                    <tr class="hover:bg-[#F7FCFC] transition">
                                        <td class="px-5 py-3.5 text-[13px] text-[#1F2937]">
                                            @if($report->property)
                                                {{ $report->property->title }}
                                                <span class="text-[#64748B]">· Listing</span>
                                            @elseif($report->reportedUser)
                                                {{ $report->reportedUser->first_name }} {{ $report->reportedUser->last_name }}
                                                <span class="text-[#64748B]">· User</span>
                                            @else
                                                <span class="text-[#64748B]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <p class="text-[13px] text-[#1F2937]">{{ Str::limit($report->report_reason, 50) }}</p>
                                            @if($report->report_status === 'Resolved' && $report->admin_notes)
                                                <p class="text-[11px] text-[#64748B] mt-1">Admin: {{ $report->admin_notes }}</p>
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
                                        <td class="px-5 py-3.5 text-[13px] text-[#64748B]">
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
