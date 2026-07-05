@extends('layouts.admin')

@section('page-title', 'Landlord Verifications')

@section('content')
<div class="max-w-5xl">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Landlord Verifications</h1>
        <p class="text-[13.5px] text-gray-500 mt-1">Review identity verification applications submitted by landlords.</p>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-white border border-gray-100 rounded-2xl p-1 mb-6 w-fit shadow-sm">
        @foreach (['Pending', 'Approved', 'Rejected', 'All'] as $tab)
            <a href="{{ route('admin.verifications.index', ['status' => $tab]) }}"
                class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-150
                    {{ $status === $tab
                        ? 'bg-[#2AA7A1] text-white shadow-sm'
                        : 'text-gray-500 hover:text-[#1A1A2E] hover:bg-gray-50' }}">
                {{ $tab }}
            </a>
        @endforeach
    </div>

    @if ($verifications->isEmpty())
        <div class="bg-white border border-gray-100 rounded-3xl p-16 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1A1A2E]">No applications here</p>
            <p class="text-[13px] text-gray-400 mt-1">No applications match this tab right now.</p>
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                <p class="text-[13px] font-semibold text-[#1A1A2E]">
                    {{ $verifications->total() }} {{ Str::plural('application', $verifications->total()) }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50/60 border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Applicant</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Business</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Submitted</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($verifications as $verification)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                                            <span class="text-[#156F8C] text-[12px] font-bold">
                                                {{ strtoupper(substr($verification->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($verification->user->last_name ?? '', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-[13.5px] font-semibold text-[#1A1A2E]">
                                                {{ $verification->user->first_name }} {{ $verification->user->last_name }}
                                            </p>
                                            <p class="text-[12px] text-gray-400">{{ $verification->user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] text-[#1A1A2E] font-medium">{{ $verification->business_name ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <x-verification-status-badge :status="$verification->verification_status" />
                                </td>
                                <td class="px-6 py-4 text-[13px] text-gray-400">
                                    {{ \Carbon\Carbon::parse($verification->submitted_at)->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.verifications.show', $verification) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-100 text-[12px] font-semibold text-[#1A1A2E] hover:bg-[#2AA7A1] hover:text-white hover:border-[#2AA7A1] transition-all">
                                        Review
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
            @if ($verifications->hasPages())
                <div class="px-6 py-4 border-t border-gray-50">
                    {{ $verifications->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
