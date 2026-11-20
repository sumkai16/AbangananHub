@extends('layouts.admin')

@section('page-title', 'Activity Logs')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" title="Activity Logs" subtitle="Everything signed-in people did on the platform: every change they made, and every file they downloaded." />

    @include('admin.activity._tabs')

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'Actions today', 'value' => number_format($stats['today']), 'note' => 'By everyone'],
        ['label' => 'People active today', 'value' => number_format($stats['people']), 'note' => 'Signed-in accounts'],
        ['label' => 'Did not go through', 'value' => number_format($stats['failed']), 'note' => 'Today: errors and refusals'],
        ['label' => 'Matching these filters', 'value' => number_format($stats['matching']), 'note' => 'All time kept'],
    ]" />

    {{-- Search + filters --}}
    <form method="GET" action="{{ route('admin.user-activity.index') }}" class="mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <label for="activity-search" class="sr-only">Search the activity log</label>
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" id="activity-search" value="{{ $search }}"
                    placeholder="Search by name, email or action…"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select autosubmit name="role" :options="$roleOptions" :selected="$role"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[160px]" />
                <x-styled-select autosubmit name="outcome" :options="$outcomeOptions" :selected="$outcome"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[200px]" />

                <div>
                    <label for="activity-from" class="sr-only">From date</label>
                    <x-date-picker autosubmit name="from" id="activity-from" value="{{ $from }}" placeholder="From" class="w-full lg:w-36" />
                </div>
                <div>
                    <label for="activity-to" class="sr-only">To date</label>
                    <x-date-picker autosubmit name="to" id="activity-to" value="{{ $to }}" placeholder="To" class="w-full lg:w-36" />
                </div>

                @if($search || $role || $outcome || $from || $to)
                    <a href="{{ route('admin.user-activity.index') }}"
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

    @if($activities->isEmpty())
        <x-card flush><x-admin.empty title="No activity found" :note="$search || $role || $outcome || $from || $to ? 'Try a different search or filter.' : null" /></x-card>
    @else
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>When</x-admin.th>
                            <x-admin.th>Person</x-admin.th>
                            <x-admin.th>Action</x-admin.th>
                            <x-admin.th>Result</x-admin.th>
                            <x-admin.th>Device</x-admin.th>
                            <x-admin.th>IP</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($activities as $a)
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200 align-top">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-[12.5px] font-semibold text-[#060D26]">{{ $a->created_at?->timezone(config('rentals.timezone'))->format('M j, Y') }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">{{ $a->created_at?->timezone(config('rentals.timezone'))->format('g:i:s A') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if($a->user)
                                        <x-person-link :user="$a->user" class="text-[12.5px] font-semibold text-[#060D26]" />
                                    @else
                                        <p class="text-[12.5px] font-semibold text-[#060D26]">{{ $a->actor_name ?: 'Unknown' }}</p>
                                    @endif
                                    <p class="text-[11px] text-[#5B6A8E] truncate max-w-[220px]">{{ $a->actor_role }}@if($a->actor_email) · {{ $a->actor_email }}@endif</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[12.5px] text-[#060D26]">{{ $a->summary }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">
                                        @if($a->targetLabel()){{ $a->targetLabel() }} · @endif{{ $a->method }} {{ $a->path }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.status :status="\App\Models\UserActivity::OUTCOMES[$a->outcome] ?? $a->outcome" :tone="$a->outcome === 'ok' ? 'green' : 'amber'" />
                                </td>
                                <td class="px-4 py-3 text-[12.5px] text-[#060D26]">{{ \App\Support\UserAgentSummary::from($a->user_agent) }}</td>
                                <td class="px-4 py-3 text-[11.5px] text-[#5B6A8E] whitespace-nowrap">{{ $a->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-t border-[#E2E4EC]">
                <p class="text-[12.5px] text-[#5B6A8E]">
                    Showing <span class="font-semibold text-[#060D26]">{{ $activities->firstItem() }}–{{ $activities->lastItem() }}</span> of
                    <span class="font-semibold text-[#060D26]">{{ $activities->total() }}</span> actions
                </p>
                {{ $activities->links() }}
            </div>
        </x-card>
    @endif

</div>

<script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
