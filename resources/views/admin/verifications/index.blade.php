@extends('layouts.admin')

@section('page-title', 'Landlord Verifications')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" title="Landlord Verifications" subtitle="Check each applicant's ID and selfie before they can list properties." />

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'Waiting', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest sent '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
        ['label' => 'Need a closer look', 'value' => number_format($summary['flagged']), 'note' => 'Face match did not clear'],
        ['label' => 'Approved', 'value' => number_format($summary['approved']), 'note' => 'Last 30 days'],
        ['label' => 'Rejected', 'value' => number_format($summary['rejected']), 'note' => 'Last 30 days'],
    ]" />

    <x-admin.tabs class="mb-4" :tabs="collect(['Pending', 'Approved', 'Rejected', 'All'])->map(fn ($tab) => [
        'label' => $tab,
        'count' => $counts[$tab],
        'href' => route('admin.verifications.index', ['status' => $tab]),
        'active' => $status === $tab,
    ])->all()" />

    <x-card flush>
        @if ($verifications->isEmpty())
            <x-admin.empty :title="$status === 'Pending' ? 'No applications waiting' : 'No applications here'" :note="$status === 'Pending' ? 'New landlord applications will show up here.' : null" />
        @else
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Applicant</x-admin.th>
                            <x-admin.th>Business</x-admin.th>
                            <x-admin.th>ID checks</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th>Submitted</x-admin.th>
                            <th class="px-4 py-2.5"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($verifications as $verification)
                            @php
                                $face = match ($verification->face_match_status) {
                                    'match' => ['Face match', 'green'],
                                    'review' => ['Face: compare', 'amber'],
                                    'mismatch' => ['Face mismatch', 'red'],
                                    'no_face' => ['No face found', 'amber'],
                                    default => ['Face not checked', 'neutral'],
                                };
                                $text = match ($verification->ocr_status) {
                                    'pass' => ['ID text read', 'green'],
                                    'partial', 'unreadable' => ['ID text unclear', 'amber'],
                                    'mismatch' => ['Name differs', 'red'],
                                    default => null,
                                };
                            @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.verifications.show', $verification) }}" class="text-[13.5px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $verification->user->first_name }} {{ $verification->user->last_name }}</a>
                                    <p class="text-[12.5px] text-[#5B6A8E]">{{ $verification->user->email }}</p>
                                    @if($verification->contact_number)<p class="text-[12.5px] tabular-nums text-[#5B6A8E]">{{ $verification->contact_number }}</p>@endif
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[13.5px] text-[#060D26]">{{ $verification->business_name ?: '—' }}</p>
                                    @if($verification->business_address)<p class="max-w-[16rem] truncate text-[12.5px] text-[#5B6A8E]" title="{{ $verification->business_address }}">{{ $verification->business_address }}</p>@endif
                                </td>
                                <td class="px-4 py-3">
                                    <p class="mb-1 text-[12.5px] text-[#5B6A8E]">{{ $verification->id_type ?: 'ID type not given' }}</p>
                                    <div class="flex flex-wrap gap-1">
                                        <x-admin.status :status="$face[0]" :tone="$face[1]" />
                                        @if($text)<x-admin.status :status="$text[0]" :tone="$text[1]" />@endif
                                        @unless($verification->liveness_passed)<x-admin.status status="No liveness check" tone="amber" />@endunless
                                    </div>
                                </td>
                                <td class="px-4 py-3"><x-admin.status :status="$verification->verification_status" /></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-[13.5px] text-[#060D26]">{{ $verification->submitted_at?->format('M j, Y') }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $verification->submitted_at?->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.verifications.show', $verification) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">{{ $verification->verification_status === 'Pending' ? 'Review' : 'View' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($verifications->hasPages())
                <div class="border-t border-[#E2E4EC] px-4 py-3">{{ $verifications->links() }}</div>
            @endif
        @endif
    </x-card>

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-5">
        <x-card flush class="lg:col-span-3">
            <div class="border-b border-[#E2E4EC] px-5 py-3.5">
                <h2 class="text-[15px] font-semibold text-[#060D26]">Recently decided</h2>
            </div>
            @if($recent->isEmpty())
                <x-admin.empty title="No decisions yet" />
            @else
                <ul class="divide-y divide-[#E2E4EC]">
                    @foreach($recent as $item)
                        <li>
                            <a href="{{ route('admin.verifications.show', $item) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                <span class="min-w-0">
                                    <span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $item->user->first_name }} {{ $item->user->last_name }}</span>
                                    <span class="block truncate text-[12.5px] text-[#5B6A8E]">{{ $item->reviewed_at?->diffForHumans() }}@if($item->reviewer) by {{ $item->reviewer->first_name }} {{ $item->reviewer->last_name }}@endif</span>
                                </span>
                                <x-admin.status :status="$item->verification_status" class="shrink-0" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card class="lg:col-span-2">
            <h2 class="text-[15px] font-semibold text-[#060D26]">Before you approve</h2>
            <ol class="mt-3 space-y-2.5 text-[13.5px] text-[#060D26]">
                <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">1</span><span>The face on the ID is the face in the selfie.</span></li>
                <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">2</span><span>The name on the ID matches the applicant's account.</span></li>
                <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">3</span><span>The ID is a real card, not a screen or a photocopy.</span></li>
                <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">4</span><span>No liveness check or a weak face match means you compare by eye.</span></li>
            </ol>
            <p class="mt-4 text-[12.5px] text-[#5B6A8E]">A rejection needs a reason; the applicant will see it.</p>
        </x-card>
    </div>

</div>
@endsection