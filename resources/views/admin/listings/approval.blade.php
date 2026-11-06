@extends('layouts.admin')

@section('page-title', 'Property Verifications')

@section('content')
<div class="max-w-[1600px] mx-auto" x-data="{ rejectTarget: { id: null, title: '' }, openReject(id, title) { this.rejectTarget = { id, title }; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'reject-listing' })); } }">

    <x-page-header icon-path="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" title="Property Verifications" subtitle="Listings waiting to go live, and what is missing from each." />

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'Waiting', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest sent '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
        ['label' => 'Not ready', 'value' => number_format($summary['incomplete']), 'note' => 'No photo or no unit yet'],
        ['label' => 'Approved', 'value' => number_format($summary['approved']), 'note' => 'Last 30 days'],
        ['label' => 'Rejected', 'value' => number_format($summary['rejected']), 'note' => 'Last 30 days'],
    ]" />

    <x-admin.tabs class="mb-4" :tabs="collect(['Pending', 'Approved', 'Rejected', 'Suspended', 'All'])->map(fn ($tab) => [
        'label' => $tab,
        'count' => $counts[$tab],
        'href' => route('admin.listings.approval', ['status' => $tab]),
        'active' => $status === $tab,
    ])->all()" />


    {{-- List --}}
    <x-card flush>
        @if ($pendingListings->isEmpty())
            <x-admin.empty :title="$status === 'Pending' ? 'No listings waiting' : 'No properties here'" />
        @else
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Property</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th>Location</x-admin.th>
                            <x-admin.th>Readiness</x-admin.th>
                            <x-admin.th align="right">Rent</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th align="right">Action</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($pendingListings as $property)
                            @php
                                $thumb = \App\Support\Images::src(optional($property->media->firstWhere('media_type', 'Image'))->media_url, 'card');

                                $locationLine = collect([$property->barangay, $property->city_municipality])
                                    ->filter()
                                    ->implode(', ') ?: $property->address;

                                $fees = $property->units->pluck('rental_fee')->filter();
                                $rentLine = $fees->isEmpty()
                                    ? 'No units yet'
                                    : ($fees->min() == $fees->max()
                                        ? '₱' . number_format($fees->min())
                                        : '₱' . number_format($fees->min()) . '–' . number_format($fees->max()));
                            @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-14 shrink-0 overflow-hidden rounded-lg bg-[#ECEEF6]">
                                            @if($thumb)
                                                <img loading="lazy" decoding="async" src="{{ $thumb }}" alt="{{ $property->title }}" class="h-full w-full object-cover" />
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="text-[13.5px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $property->title }}</a>
                                            <p class="text-[12.5px] text-[#5B6A8E]">{{ $property->property_type }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-[13.5px] text-[#060D26]">{{ $property->landlord->name }}</td>
                                <td class="max-w-[18rem] truncate px-4 py-3 text-[13px] text-[#5B6A8E]" title="{{ $locationLine }}">{{ $locationLine }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <x-admin.status :status="$property->photos_count.' '.\Illuminate\Support\Str::plural('photo', $property->photos_count)" :tone="$property->photos_count > 0 ? 'green' : 'red'" />
                                        <x-admin.status :status="$property->units->count().' '.\Illuminate\Support\Str::plural('unit', $property->units->count())" :tone="$property->units->count() > 0 ? 'green' : 'red'" />
                                        <x-admin.status :status="$property->verified_documents_count.'/'.$property->documents_count.' documents'" :tone="$property->documents_count > 0 && $property->verified_documents_count === $property->documents_count ? 'green' : 'amber'" />
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-[13.5px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">{{ $rentLine }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-admin.status :status="$property->verification_status" />
                                        @if ($property->publication_status !== 'Published')
                                            <x-admin.status :status="$property->publication_status" />
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($property->verification_status === 'Pending')
                                            <a href="{{ route('admin.catalogue.properties.show', $property) }}"
                                                class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Review</a>
                                            <form method="POST" action="{{ route('admin.listings.approve', $property->property_id) }}"
                                                data-confirm="Approve this listing?"
                                                data-confirm-message="It becomes visible to tenants and the landlord is told. Check the photos and documents first."
                                                data-confirm-button="Approve listing">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex h-9 items-center rounded-lg bg-[#15803D] px-3 text-[12.5px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">Approve</button>
                                            </form>
                                            <button type="button" x-on:click="openReject({{ $property->property_id }}, @js($property->title))"
                                                class="inline-flex h-9 items-center rounded-lg border border-[#EF4444]/30 px-3 text-[12.5px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06] cursor-pointer">Reject</button>
                                        @elseif ($property->publication_status === 'Suspended')
                                            <form method="POST" action="{{ route('admin.listings.unsuspend', $property->property_id) }}"
                                                data-confirm="Reinstate this listing?"
                                                data-confirm-message="It will become visible to tenants again immediately.">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex h-9 items-center rounded-lg bg-[#FF8A66] px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Unsuspend</button>
                                            </form>
                                        @else
                                            <span class="text-[13px] text-[#5B6A8E]">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($pendingListings->hasPages())
                <div class="border-t border-[#E2E4EC] px-4 py-3">{{ $pendingListings->links() }}</div>
            @endif
        @endif
    </x-card>



    <x-admin.queue-aside :recent="$recent->map(fn ($p) => ['name' => $p->title, 'sub' => $p->landlord?->name.' · '.$p->updated_at->diffForHumans(), 'status' => $p->verification_status, 'href' => route('admin.catalogue.properties.show', $p)])->all()"
        :checklist="['The photos show the property and look like the listing.', 'The address and the map pin agree.', 'There is at least one unit with a realistic rent.', 'The landlord is verified and the documents are in order.']"
        note="A rejection needs a reason; the landlord will see it." />

    {{-- Reject reason modal — shared across every row instead of an inline
         per-row form, since this page can list dozens of pending listings.
         The landlord previously got no reason at all when rejected (see
         admin/documents/show.blade.php for the pattern this mirrors, which
         already required one for document rejection). --}}
    <x-modal name="reject-listing" focusable>
        <form method="POST" x-bind:action="`{{ url('admin/listings') }}/${rejectTarget.id}/reject`" class="p-6">
            @csrf
            <h2 class="text-[15px] font-bold text-[#060D26]">Reject listing</h2>
            <p class="mt-1 text-[13px] text-[#5B6A8E]">
                <span x-text="rejectTarget.title"></span> will be rejected and the landlord notified with your reason below.
            </p>

            <label for="rejection_reason" class="block mt-4 text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-1.5">
                Reason for rejection
            </label>
            <textarea name="rejection_reason" id="rejection_reason" rows="4" required
                class="w-full rounded-lg border border-[#E2E4EC] px-3 py-2.5 text-[13px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all resize-none"
                placeholder="Explain what needs to change — the landlord will see this and can resubmit."></textarea>
            @error('rejection_reason')
                <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" x-on:click="show = false"
                    class="h-10 px-4 rounded-lg border border-[#E2E4EC] text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="h-10 px-4 rounded-lg bg-[#EF4444] hover:brightness-95 text-white text-[13px] font-bold transition-all">
                    Confirm rejection
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection
