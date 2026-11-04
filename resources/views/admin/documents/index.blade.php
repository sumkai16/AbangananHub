@extends('layouts.admin')

@section('page-title', 'Property Documents')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header title="Property Documents" subtitle="Proof of ownership, tax declarations and permits." />

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'To review', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest sent '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
        ['label' => 'Asked for', 'value' => number_format($counts['Requested']), 'note' => 'Waiting on landlords to upload'],
        ['label' => 'Verified', 'value' => number_format($summary['verified']), 'note' => 'Last 30 days'],
        ['label' => 'Expiring soon', 'value' => number_format($summary['expiring']), 'note' => 'Verified, runs out in 30 days'],
    ]" />

    <x-admin.tabs class="mb-4" :tabs="collect(['Pending' => 'Pending', 'Requested' => 'Awaiting landlord', 'Verified' => 'Verified', 'Rejected' => 'Rejected', 'All' => 'All'])->map(fn ($label, $tab) => [
        'label' => $label,
        'count' => $counts[$tab],
        'href' => route('admin.documents.index', ['status' => $tab]),
        'active' => $status === $tab,
    ])->values()->all()" />

    <x-card flush>
        @if ($documents->isEmpty())
            <x-admin.empty title="No documents here" />
        @else
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Document</x-admin.th>
                            <x-admin.th>Property</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th>Number</x-admin.th>
                            <x-admin.th>Expires</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th>Updated</x-admin.th>
                            <th class="px-4 py-2.5"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($documents as $document)
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 py-3 text-[13.5px] font-semibold text-[#060D26]">{{ $document->document_type }}</td>
                                <td class="px-4 py-3 text-[13.5px] text-[#060D26]">{{ $document->property->title ?? '—' }}</td>
                                <td class="px-4 py-3 text-[13.5px] text-[#060D26]">{{ trim(($document->property->landlord->first_name ?? '') . ' ' . ($document->property->landlord->last_name ?? '')) ?: '—' }}</td>
                                <td class="px-4 py-3 text-[13px] tabular-nums text-[#060D26]">{{ $document->document_number ?: '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($document->expiry_date)
                                        <p class="text-[13px] text-[#060D26]">{{ $document->expiry_date->format('M j, Y') }}</p>
                                        @if($document->expiry_date->isPast())<p class="text-[12px] font-semibold text-[#DC2626]">Expired</p>
                                        @elseif($document->expiry_date->lte(now()->addDays(30)))<p class="text-[12px] font-semibold text-[#B45309]">In {{ now()->diffInDays($document->expiry_date) }} days</p>@endif
                                    @else
                                        <span class="text-[13px] text-[#5B6A8E]">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><x-admin.status :status="$document->isRequested() ? 'Requested' : $document->display_status" /></td>
                                <td class="px-4 py-3 text-[13px] whitespace-nowrap text-[#5B6A8E]">{{ $document->updated_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.documents.show', $document) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($documents->hasPages())
                <div class="border-t border-[#E2E4EC] px-4 py-3">{{ $documents->links() }}</div>
            @endif
        @endif
    </x-card>

    <x-admin.queue-aside checklist-title="Before you verify"
        :recent="$recent->map(fn ($d) => ['name' => $d->document_type, 'sub' => ($d->property->title ?? '—').' · '.$d->updated_at->diffForHumans().($d->verifier ? ' by '.$d->verifier->first_name : ''), 'status' => $d->status, 'href' => route('admin.documents.show', $d)])->all()"
        :checklist="['The name on the document is the landlord, or the owner who authorised them.', 'The address matches the property listing.', 'It is current and readable, not cropped or edited.', 'The expiry date is in the future.']"
        note="A rejection needs a reason; the landlord will see it." />

</div>
@endsection
