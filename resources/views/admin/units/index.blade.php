@extends('layouts.admin')

@section('page-title', 'Unit Approvals')

@section('content')
    <div class="max-w-[1400px]">

        {{-- Page header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Unit Approvals</h1>
            <p class="text-[13.5px] text-gray-500 mt-1">Review rental units submitted by landlords for verification.</p>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-white border border-gray-100 rounded-2xl p-1 mb-6 w-fit shadow-sm">
            @foreach (['Pending', 'Approved', 'Rejected', 'All'] as $tab)
                <a href="{{ route('admin.units.index', ['status' => $tab]) }}" class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-150
                            {{ $status === $tab
                ? 'bg-[#286CD2] text-white shadow-sm'
                : 'text-gray-500 hover:text-[#1A1A2E] hover:bg-gray-50' }}">
                    {{ $tab }}
                </a>
            @endforeach
        </div>

        @if ($units->isEmpty())
            <div class="bg-white border border-gray-100 rounded-3xl p-16 text-center shadow-sm">
                <div
                    class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm9.75 0A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm-9.75 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75 0a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                </div>
                <p class="text-[15px] font-bold text-[#1A1A2E]">No units here</p>
                <p class="text-[13px] text-gray-400 mt-1">No units match this tab right now.</p>
            </div>
        @else
            <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                    <p class="text-[13px] font-semibold text-[#1A1A2E]">
                        {{ $units->total() }} {{ Str::plural('unit', $units->total()) }}
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50/60 border-b border-gray-100">
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Unit</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Property</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Landlord</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Rent</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($units as $unit)
                                @php
                                    $thumb = $unit->media->firstWhere('media_type', 'Image');
                                    $statusCls = match ($unit->verification_status) {
                                        'Approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Rejected' => 'bg-red-50 text-red-600 border-red-200',
                                        default => 'bg-amber-50 text-amber-700 border-amber-200',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gray-100 overflow-hidden shrink-0">
                                                @if($thumb)
                                                    <img src="{{ $thumb->media_url }}" alt="" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                            <p class="text-[13.5px] font-semibold text-[#1A1A2E]">{{ $unit->unit_label }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-[13px] text-gray-600 truncate max-w-[180px]">
                                            {{ $unit->property->title ?? '—' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-[13px] text-gray-600">
                                            {{ $unit->property->landlord->first_name ?? '' }}
                                            {{ $unit->property->landlord->last_name ?? '' }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 text-[13px] text-gray-600">
                                        ₱{{ number_format($unit->rental_fee, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusCls }}">
                                            {{ $unit->verification_status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('admin.units.show', [$unit->property, $unit]) }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-100 text-[12px] font-semibold text-[#1A1A2E] hover:bg-[#286CD2] hover:text-white hover:border-[#286CD2] transition-all">
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
                @if ($units->hasPages())
                    <div class="px-6 py-4 border-t border-gray-50">
                        {{ $units->links() }}
                    </div>
                @endif
            </div>
        @endif

    </div>
@endsection