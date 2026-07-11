@extends('layouts.admin')

@section('page-title', 'Report Detail')

@section('content')
<div>

    {{-- Back --}}
    <a href="{{ route('admin.reports.index') }}"
        class="inline-flex items-center gap-2 text-[13px] font-bold text-gray-400 hover:text-[#156F8C] transition-colors mb-5">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to reports
    </a>

    {{-- Flash --}}
    @if(session('status'))
        <div class="mb-4 px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-[13.5px] font-medium">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white border border-gray-100 rounded-xl px-5 py-4 mb-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-[#1A1A2E] leading-tight">Report #{{ $report->report_id }}</h1>
            <p class="text-[12px] text-gray-400 mt-0.5">Submitted {{ $report->created_at->format('M d, Y \a\t g:i A') }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold px-3 py-1.5 rounded-full
            {{ $report->isPending() ? 'bg-amber-50 text-amber-600 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $report->isPending() ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
            {{ $report->report_status }}
        </span>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-4">
        {{-- Reporter --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Reported By</p>
            <p class="text-[14px] font-semibold text-[#1A1A2E]">{{ $report->reporter->name ?? '—' }}</p>
            <p class="text-[12.5px] text-gray-400 mt-0.5">{{ $report->reporter->email ?? '' }}</p>
        </div>

        {{-- Target --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Target</p>
            @if($report->property)
                <p class="text-[14px] font-semibold text-[#1A1A2E]">{{ $report->property->title }}</p>
                <p class="text-[12.5px] text-gray-400 mt-0.5">Property Listing</p>
            @elseif($report->reportedUser)
                <p class="text-[14px] font-semibold text-[#1A1A2E]">{{ $report->reportedUser->name }}</p>
                <p class="text-[12.5px] text-gray-400 mt-0.5">{{ $report->reportedUser->email }} · User</p>
            @else
                <p class="text-[13px] text-gray-400">No target recorded.</p>
            @endif
        </div>
    </div>

    {{-- Reason --}}
    <div class="bg-white border border-gray-100 rounded-xl p-5 mb-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Reason</p>
        <p class="text-[13.5px] text-[#1A1A2E] leading-relaxed whitespace-pre-line">{{ $report->report_reason }}</p>
    </div>

    {{-- Actions --}}
    @if($report->isPending())
        <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
            @csrf
            @method('PATCH')
            <button type="submit"
                class="inline-flex items-center gap-2 h-11 px-6 rounded-full bg-[#2AA7A1] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
                Mark as Resolved
            </button>
        </form>
    @endif

</div>
@endsection
