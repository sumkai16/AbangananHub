@extends('layouts.admin')

@section('page-title', 'Conversations')

@section('content')
<div class="max-w-[1400px]">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Conversations</h1>
            <p class="text-[13.5px] text-gray-500 mt-1">Read-only access to all tenant–landlord conversations for dispute support.</p>
        </div>
        <span class="text-[13px] font-semibold text-gray-400">{{ number_format($counts['all']) }} total</span>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 mb-1">Total</p>
            <p class="text-[28px] font-extrabold text-[#1A1A2E] leading-none">{{ number_format($counts['all']) }}</p>
        </div>
        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-blue-600 mb-1">Open</p>
            <p class="text-[28px] font-extrabold text-blue-700 leading-none">{{ number_format($counts['Open']) }}</p>
            <p class="text-[11px] text-blue-500 mt-1">Active threads</p>
        </div>
        <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 mb-1">Resolved</p>
            <p class="text-[28px] font-extrabold text-gray-600 leading-none">{{ number_format($counts['Resolved']) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.conversations.index') }}"
        class="bg-white border border-gray-100 rounded-2xl p-4 mb-5 shadow-sm flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant, landlord, or property…"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-gray-200 bg-gray-50/50 focus:outline-none focus:ring-2 focus:ring-[#286CD2]/20 focus:border-[#286CD2] transition-all">
        </div>
        <select name="status"
            class="h-10 text-[13.5px] rounded-xl border border-gray-200 bg-gray-50/50 px-3 focus:outline-none focus:ring-2 focus:ring-[#286CD2]/20 focus:border-[#286CD2] transition-all">
            <option value="all"     {{ $status === 'all'     ? 'selected' : '' }}>All statuses</option>
            <option value="Open"    {{ $status === 'Open'    ? 'selected' : '' }}>Open</option>
            <option value="Resolved"{{ $status === 'Resolved'? 'selected' : '' }}>Resolved</option>
        </select>
        <button type="submit"
            class="h-10 px-5 text-[13.5px] font-bold bg-[#286CD2] text-white rounded-xl hover:bg-[#1e5bb8] transition-colors shadow-sm">
            Filter
        </button>
        @if($search || $status !== 'all')
            <a href="{{ route('admin.conversations.index') }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:text-[#1A1A2E] transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- List --}}
    @if($conversations->isEmpty())
        <div class="bg-white border border-gray-100 rounded-3xl p-16 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1A1A2E]">No conversations found</p>
            <p class="text-[13px] text-gray-400 mt-1">{{ $search ? 'Try adjusting your search.' : 'None yet.' }}</p>
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50/60 border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Tenant</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Landlord</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Property</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Last Message</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($conversations as $conv)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-semibold text-[#1A1A2E]">
                                        {{ trim(($conv->tenant->first_name ?? '') . ' ' . ($conv->tenant->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[12px] text-gray-400">{{ $conv->tenant->email ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-semibold text-[#1A1A2E]">
                                        {{ trim(($conv->landlord->first_name ?? '') . ' ' . ($conv->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] text-[#1A1A2E] truncate max-w-[160px]">{{ $conv->property->title ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    @if($conv->latestMessage)
                                        <p class="text-[12.5px] text-gray-500 truncate max-w-[180px]">{{ $conv->latestMessage->message }}</p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $conv->latestMessage->sent_at?->diffForHumans() }}</p>
                                    @else
                                        <span class="text-[12px] text-gray-300 italic">No messages</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border
                                        {{ $conv->status === 'Resolved' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                        {{ $conv->status ?? 'Open' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.conversations.show', $conv) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-100 text-[12px] font-semibold text-[#1A1A2E] hover:bg-[#286CD2] hover:text-white hover:border-[#286CD2] transition-all">
                                        Read
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($conversations->hasPages())
                <div class="px-6 py-4 border-t border-gray-50">{{ $conversations->links() }}</div>
            @endif
        </div>
    @endif

</div>
@endsection
