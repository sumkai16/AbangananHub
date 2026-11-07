@extends('layouts.admin')

@section('page-title', 'Property Documents')

@section('content')
@php
    $empty = [
        'Pending' => ['check', 'No documents to review', 'Documents landlords upload for a property show here.'],
        'Requested' => ['document', 'Nothing asked for', 'Documents you ask a landlord for show here until they upload them.'],
        'Verified' => ['document', 'No verified documents yet', 'Documents you verify show here.'],
        'Rejected' => ['document', 'No rejected documents', 'Documents you reject show here, with your reason.'],
        'All' => ['document', 'No documents yet', 'Documents landlords upload for their properties show here.'],
    ][$status] ?? ['document', 'No documents here', null];
    $showStatus = in_array($status, ['All', 'Requested'], true);
@endphp
<div class="admin-compact admin-fill">

    <x-admin.header title="Property Documents" subtitle="Proof of ownership, tax declarations and permits.">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <x-admin.stat-line :cells="[
                    ['label' => 'To review', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
                    ['label' => 'Asked for', 'value' => number_format($counts['Requested']), 'note' => 'Waiting on landlords'],
                    ['label' => 'Verified', 'value' => number_format($summary['verified']), 'note' => 'Last 30 days'],
                    ['label' => 'Expiring soon', 'value' => number_format($summary['expiring']), 'note' => 'Runs out in 30 days'],
                ]" />
                <x-admin.checklist title="Before you verify" :items="['The name on the document is the landlord, or the owner who authorised them.', 'The address matches the property listing.', 'It is current and readable, not cropped or edited.', 'The expiry date is in the future.']"
                    note="A rejection needs a reason; the landlord will see it." />
            </div>
        </x-slot:actions>
    </x-admin.header>

    <x-admin.tabs class="mb-3" :tabs="collect(['All' => 'All', 'Pending' => 'Pending', 'Requested' => 'Awaiting landlord', 'Verified' => 'Verified', 'Rejected' => 'Rejected'])->map(fn ($label, $tab) => [
        'label' => $label,
        'count' => $counts[$tab],
        'href' => route('admin.documents.index', ['status' => $tab]),
        'active' => $status === $tab,
    ])->values()->all()" />

    @if ($documents->isEmpty())
        <x-card flush fill>
            <x-empty-state flat fill :symbol="$empty[0]" :title="$empty[1]" :note="$empty[2]">
                @if ($status !== 'All')
                    <a href="{{ route('admin.documents.index', ['status' => 'All']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">See all documents</a>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <x-card flush fill>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="w-full min-w-[960px]">
                    <thead>
                        <tr class="border-b border-[#E2E4EC] bg-[#F7F8FC]">
                            <x-admin.th class="w-[18%]">Document</x-admin.th>
                            <x-admin.th class="w-[26%]">Property</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th>Number</x-admin.th>
                            <x-admin.th>Expires</x-admin.th>
                            @if ($showStatus)<x-admin.th>Status</x-admin.th>@endif
                            <x-admin.th>Updated</x-admin.th>
                            <x-admin.th align="right"><span class="sr-only">Open</span></x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($documents as $document)
                            @php $landlord = trim(($document->property->landlord->first_name ?? '').' '.($document->property->landlord->last_name ?? '')) ?: '—'; @endphp
                            <tr class="transition-colors duration-200 hover:bg-[#F7F8FC]">
                                <td class="px-4 text-[13px] font-semibold whitespace-nowrap text-[#060D26]">{{ $document->document_type }}</td>
                                <td class="max-w-0 px-4"><p class="truncate text-[13px] text-[#060D26]" title="{{ $document->property->title ?? '' }}">{{ $document->property->title ?? '—' }}</p></td>
                                <td class="px-4 text-[13px] whitespace-nowrap text-[#060D26]">{{ $landlord }}</td>
                                <td class="px-4 text-[13px] tabular-nums whitespace-nowrap text-[#060D26]">{{ $document->document_number ?: '—' }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    @if($document->expiry_date)
                                        <p class="text-[13px] leading-5 text-[#060D26]">{{ $document->expiry_date->format('M j, Y') }}</p>
                                        @if($document->expiry_date->isPast())<p class="text-[12px] leading-4 font-semibold text-[#DC2626]">Expired</p>
                                        @elseif($document->expiry_date->lte(now()->addDays(30)))<p class="text-[12px] leading-4 font-semibold text-[#B45309]">In {{ now()->diffInDays($document->expiry_date) }} days</p>@endif
                                    @else
                                        <span class="text-[13px] text-[#5B6A8E]">No expiry</span>
                                    @endif
                                </td>
                                @if ($showStatus)<td class="px-4"><x-admin.status :status="$document->isRequested() ? 'Requested' : $document->display_status" /></td>@endif
                                <td class="px-4 whitespace-nowrap">
                                    <p class="text-[13px] leading-5 text-[#060D26]">{{ $document->updated_at?->format('M j, Y') }}</p>
                                    <p class="text-[12px] leading-4 text-[#5B6A8E]">{{ $document->updated_at?->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 text-right">
                                    <a href="{{ route('admin.documents.show', $document) }}" class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">{{ $document->isRequested() ? 'Open' : ($document->status === 'Pending' ? 'Review' : 'View') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2.5">
                @if ($documents->hasPages())
                    <div class="w-full">{{ $documents->links() }}</div>
                @else
                    <p class="text-[13px] text-[#5B6A8E]">Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $documents->firstItem() }}–{{ $documents->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($documents->total()) }}</span>@if ($status === 'Pending'), oldest first @elseif ($status === 'All'), waiting first, then newest @endif</p>
                @endif
            </div>
        </x-card>
    @endif

</div>
@endsection
