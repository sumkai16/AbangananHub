@extends('layouts.admin')

@section('page-title', 'Property Documents')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header title="Property Documents" subtitle="Review proof of ownership, tax declarations, and permits submitted across all properties." />

    {{-- Stat summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
        @php
            $stats = [
                'Pending'   => ['label' => 'Pending review', 'value' => $counts['Pending'], 'valueColor' => '#B45309', 'iconBg' => 'rgba(251,191,36,0.10)', 'iconColor' => '#B45309'],
                'Requested' => ['label' => 'Awaiting landlord', 'value' => $counts['Requested'], 'valueColor' => '#2563EB', 'iconBg' => 'rgba(59,130,246,0.10)', 'iconColor' => '#2563EB'],
                'Verified'  => ['label' => 'Verified', 'value' => $counts['Verified'], 'valueColor' => '#15803D', 'iconBg' => 'rgba(34,197,94,0.07)', 'iconColor' => '#059669'],
                'Rejected'  => ['label' => 'Rejected', 'value' => $counts['Rejected'], 'valueColor' => '#B91C1C', 'iconBg' => 'rgba(239,68,68,0.07)', 'iconColor' => '#DC2626'],
                'All'       => ['label' => 'Total', 'value' => $counts['All'], 'valueColor' => '#156F8C', 'iconBg' => '#EEF8F8', 'iconColor' => '#156F8C'],
            ];
        @endphp
        @foreach ($stats as $key => $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :value-color="$stat['valueColor']" :icon-bg="$stat['iconBg']"
                :href="route('admin.documents.index', ['status' => $key])"
                :class="$status === $key ? 'ring-2 ring-[#2AA7A1]' : ''">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-0.5 border-b border-[#E2E8F0] mb-6 overflow-x-auto">
        @foreach (['Pending', 'Requested', 'Verified', 'Rejected', 'All'] as $tab)
            <a href="{{ route('admin.documents.index', ['status' => $tab]) }}"
                class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                    {{ $status === $tab ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                {{ $tab }}
                <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
            </a>
        @endforeach
    </div>

    @if ($documents->isEmpty())
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">No documents here</p>
            <p class="text-[13px] text-[#64748B] mt-1">No documents match this tab right now.</p>
        </div>
    @else
        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden divide-y divide-[#E2E8F0]">
            @foreach ($documents as $document)
                <a href="{{ route('admin.documents.show', $document) }}"
                    class="flex flex-wrap sm:flex-nowrap items-center gap-4 px-6 py-4 hover:bg-[#F7FCFC]/70 transition-all duration-200 group">
                    <div class="w-11 h-11 rounded-full bg-[#156F8C] flex items-center justify-center shrink-0">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1 basis-56">
                        <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">{{ $document->document_type }}</p>
                        <p class="text-[12px] text-[#64748B] truncate">{{ $document->property->title ?? '—' }}</p>
                    </div>

                    <div class="min-w-0 flex-1 basis-40">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Landlord</p>
                        <p class="text-[13px] text-[#1F2937] font-medium truncate">
                            {{ trim(($document->property->landlord->first_name ?? '') . ' ' . ($document->property->landlord->last_name ?? '')) ?: '—' }}
                        </p>
                    </div>

                    <div class="shrink-0">
                        <x-document-status-badge :document="$document" />
                    </div>

                    <div class="shrink-0 text-right w-24">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Updated</p>
                        <p class="text-[13px] text-[#64748B]">{{ $document->updated_at?->format('M d, Y') }}</p>
                    </div>

                    <svg class="w-4 h-4 text-[#94A3B8] group-hover:text-[#2AA7A1] group-hover:translate-x-0.5 transition-all duration-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endforeach
        </div>
        @if ($documents->hasPages())
            <div class="mt-4 bg-white border border-[#E2E8F0] rounded-2xl px-6 py-3 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                {{ $documents->links() }}
            </div>
        @endif
    @endif

</div>
@endsection
