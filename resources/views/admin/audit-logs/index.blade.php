@extends('layouts.admin')

@section('page-title', 'Audit Logs')

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Header --}}
    <x-page-header title="Audit Logs" subtitle="Every consequential admin action, recorded and unalterable.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" />
            </svg>
        </x-slot:icon>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <x-stat-card label="Total Actions" :value="number_format($stats['total'])" sub="Matching current filters">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3.75-9.75H18a2.25 2.25 0 012.25 2.25v9A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25v-9A2.25 2.25 0 016 8.25h1.5" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Today" :value="number_format($stats['today'])" value-color="#156F8C" icon-bg="#EEF8F8" sub="Recorded in the last 24h">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Actors Involved" :value="number_format($stats['actors'])" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)" sub="Distinct actors">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Destructive" :value="number_format($stats['destructive'])" value-color="#DC2626" icon-bg="rgba(239,68,68,0.07)" sub="Money, deletions, overrides">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    {{-- Search + filters --}}
    <form method="GET" action="{{ route('admin.audit-logs.index') }}"
        class="bg-white rounded-2xl p-4 mb-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <label for="audit-search" class="sr-only">Search audit logs</label>
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" id="audit-search" value="{{ $search }}"
                    placeholder="Search by admin, action, or reason…"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] focus:bg-white transition-all duration-200">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select name="action" :options="$actionOptions" :selected="$action"
                    class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] max-w-[220px]" />

                <div>
                    <label for="audit-from" class="sr-only">From date</label>
                    <x-date-picker name="from" id="audit-from" value="{{ $from }}" placeholder="From" class="w-full lg:w-36" />
                </div>
                <div>
                    <label for="audit-to" class="sr-only">To date</label>
                    <x-date-picker name="to" id="audit-to" value="{{ $to }}" placeholder="To" class="w-full lg:w-36" />
                </div>

                <button type="submit"
                    class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5 cursor-pointer">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    Filter
                </button>

                @if($search || $action || $from || $to)
                    <a href="{{ route('admin.audit-logs.index') }}"
                        class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5">
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
        <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-white flex flex-col items-center justify-center py-16 text-center">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3.75-9.75H18a2.25 2.25 0 012.25 2.25v9A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25v-9A2.25 2.25 0 016 8.25h1.5" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-[#1F2937]">No audit entries found</p>
            <p class="text-xs text-[#64748B] mt-1">
                {{ $search || $action || $from || $to ? 'Try adjusting your search or filters.' : 'Actions will be recorded here as they happen.' }}
            </p>
        </div>
    @else
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left">
                    <thead>
                        <tr class="border-b border-[#E2E8F0]">
                            <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">When</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Actor</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Action</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Details</th>
                            <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach($logs as $log)
                            <tr class="hover:bg-[#F7FCFC]/70 transition-colors duration-200 align-top">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <p class="text-[12.5px] font-semibold text-[#1F2937]">{{ $log->created_at?->format('M j, Y') }}</p>
                                    <p class="text-[11px] text-[#64748B]">{{ $log->created_at?->format('g:i A') }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[12.5px] font-semibold text-[#1F2937]">{{ $log->actor_name }}</p>
                                    <p class="text-[11px] text-[#64748B] truncate max-w-[180px]">{{ $log->actor_email }}</p>
                                    @unless($log->actor_id)
                                        {{-- The FK is ON DELETE SET NULL so the trail survives the account. --}}
                                        <p class="text-[10.5px] text-[#B45309] mt-0.5">Account deleted</p>
                                    @endunless
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap
                                        {{ $log->isDestructive() ? 'bg-[#EF4444]/[0.07] text-[#DC2626]' : 'bg-[#EEF8F8] text-[#156F8C]' }}">
                                        {{ $log->actionLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[12.5px] text-[#1F2937]">{{ $log->summary }}</p>
                                    @if($log->reason)
                                        <p class="text-[11px] text-[#64748B] mt-0.5">Reason: {{ $log->reason }}</p>
                                    @endif
                                    @if($log->metadata)
                                        <p class="text-[11px] text-[#64748B] mt-0.5">
                                            @foreach($log->metadata as $key => $value)
                                                <span class="inline-block mr-2">
                                                    {{ Str::headline($key) }}:
                                                    <span class="font-semibold text-[#1F2937]">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                                                </span>
                                            @endforeach
                                        </p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-[11.5px] text-[#64748B] whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-t border-[#E2E8F0]">
                <p class="text-[12.5px] text-[#64748B]">
                    Showing <span class="font-semibold text-[#1F2937]">{{ $logs->firstItem() }}–{{ $logs->lastItem() }}</span> of
                    <span class="font-semibold text-[#1F2937]">{{ $logs->total() }}</span> entries
                </p>
                {{ $logs->links() }}
            </div>
        </x-card>
    @endif

</div>

<script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
