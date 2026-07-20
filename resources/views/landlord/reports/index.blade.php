@extends('layouts.landlord')

@section('page-title', 'My Reports')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <div class="flex items-center gap-3.5 mb-5">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">My Reports</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Track the status of reports you have filed.</p>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Reports</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ $stats['total'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All reports you've filed</p>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Pending</span>
                    <div class="w-8 h-8 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#B45309]">{{ $stats['pending'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Awaiting admin review</p>
            </div>
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Resolved</span>
                    <div class="w-8 h-8 rounded-lg bg-[#22C55E]/[0.07] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#15803D]">{{ $stats['resolved'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Reviewed and closed</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 items-start">
            <div class="min-w-0">
                {{-- Filter bar --}}
                <form method="GET" action="{{ route('landlord.reports.index') }}"
                    class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4 mb-6">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="relative">
                            <select name="status"
                                class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                                <option value="">All Statuses</option>
                                <option value="Pending" @selected(request('status') === 'Pending')>Pending</option>
                                <option value="Resolved" @selected(request('status') === 'Resolved')>Resolved</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>

                        <button type="submit"
                            class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            Filter
                        </button>

                        @if(request()->hasAny(['status']))
                            <a href="{{ route('landlord.reports.index') }}"
                                class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                Clear
                            </a>
                        @endif

                        <a href="{{ route('reports.create') }}"
                            class="ml-auto h-11 px-5 rounded-xl bg-[#2AA7A1] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            File a Report
                        </a>
                    </div>
                </form>

                {{-- Report cards --}}
                @if($reports->isEmpty())
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] flex flex-col items-center justify-center py-16 px-6 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <p class="text-[15px] font-semibold text-[#1F2937]">No reports filed</p>
                        <p class="text-[13px] text-[#64748B] mt-1 max-w-xs">Reports you submit against listings or users will be tracked here, from filing to resolution.</p>
                        <a href="{{ route('reports.create') }}"
                            class="mt-5 inline-flex items-center gap-1.5 h-10 px-5 rounded-xl bg-[#2AA7A1] text-white text-[13px] font-semibold hover:brightness-95 transition-all duration-200">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            File a Report
                        </a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($reports as $report)
                            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[13.5px] font-bold text-[#1F2937]">{{ Str::limit($report->report_reason, 60) }}</span>
                                    </div>
                                    @if($report->report_status === 'Resolved')
                                        <span class="inline-flex items-center gap-1.5 shrink-0 px-2.5 py-1 rounded-full bg-[#22C55E]/[0.07] text-[#15803D] text-[11px] font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
                                            Resolved
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 shrink-0 px-2.5 py-1 rounded-full bg-[#FBBF24]/[0.10] text-[#B45309] text-[11px] font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#FBBF24]"></span>
                                            Pending
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-4 text-[12px] text-[#64748B] flex-wrap">
                                    <span class="flex items-center gap-1.5">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                        {{ $report->reportedUser ? $report->reportedUser->first_name . ' ' . $report->reportedUser->last_name : 'N/A' }}
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                        {{ $report->property->title ?? 'General' }}
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                        {{ $report->created_at->format('M d, Y') }}
                                    </span>
                                </div>

                                @if($report->report_status === 'Resolved' && $report->admin_notes)
                                    <div class="mt-3 pt-3 border-t border-[#64748B]/10 pl-3 border-l-2 border-l-emerald-400">
                                        <p class="text-[11px] font-semibold text-[#15803D] uppercase tracking-wide">Admin Response</p>
                                        <p class="text-[12.5px] text-[#64748B] mt-0.5 leading-relaxed">{{ $report->admin_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $reports->links() }}
                    </div>
                @endif
            </div>

            {{-- Sidebar: reporting guidelines --}}
            <aside class="flex flex-col gap-4 lg:sticky lg:top-24">
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                    <h3 class="text-[13px] font-bold text-[#1F2937] mb-3.5">When to file a report</h3>
                    <ul class="flex flex-col gap-3">
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 mt-0.5">
                                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            <span class="text-[12.5px] text-[#64748B] leading-relaxed">A tenant or user violates community guidelines</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 mt-0.5">
                                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            <span class="text-[12.5px] text-[#64748B] leading-relaxed">You spot a scam, fake, or duplicate listing</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 mt-0.5">
                                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            <span class="text-[12.5px] text-[#64748B] leading-relaxed">Someone is harassing you or misusing the platform</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl bg-[#1F2937] p-5">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center mb-3">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m6-3v8.25a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <p class="text-[12.5px] font-semibold text-white leading-snug">Reports are confidential</p>
                    <p class="text-[11.5px] text-white/60 leading-relaxed mt-1.5">
                        The person or listing you report is never notified who filed it.
                    </p>
                </div>
            </aside>
        </div>
    </div>
@endsection
