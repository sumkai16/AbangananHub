@extends('layouts.admin')

@section('page-title', 'Unit Approvals')

@section('content')
    <div class="max-w-[1600px] mx-auto">

        <x-page-header title="Unit Approvals" subtitle="Rental units waiting to be shown to tenants." />

        <x-stat-strip class="mb-5" :cells="[
            ['label' => 'Waiting', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest sent '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
            ['label' => 'No photo', 'value' => number_format($summary['no_photo']), 'note' => 'Waiting units with no photos'],
            ['label' => 'Approved', 'value' => number_format($summary['approved']), 'note' => 'Last 30 days'],
            ['label' => 'Rejected', 'value' => number_format($summary['rejected']), 'note' => 'Last 30 days'],
        ]" />

        <x-admin.tabs class="mb-4" :tabs="collect(['Pending', 'Approved', 'Rejected', 'All'])->map(fn ($tab) => [
            'label' => $tab,
            'count' => $counts[$tab],
            'href' => route('admin.units.index', ['status' => $tab]),
            'active' => $status === $tab,
        ])->all()" />

        <x-card flush>
            @if ($units->isEmpty())
                <x-admin.empty title="No units here" />
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                <x-admin.th>Unit</x-admin.th>
                                <x-admin.th>Landlord</x-admin.th>
                                <x-admin.th>Photos</x-admin.th>
                                <x-admin.th>Submitted</x-admin.th>
                                <x-admin.th align="right">Rent</x-admin.th>
                                <x-admin.th>Status</x-admin.th>
                                <th class="px-4 py-2.5"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach ($units as $unit)
                                @php $thumb = $unit->media->firstWhere('media_type', 'Image'); @endphp
                                <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-[#ECEEF6]">
                                                @if($thumb)
                                                    <img src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="{{ $unit->unit_label }}" loading="lazy" class="h-full w-full object-cover">
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $unit->unit_label }}</p>
                                                <p class="text-[12.5px] text-[#5B6A8E]">{{ $unit->property->title ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-[13.5px] text-[#060D26]">{{ trim(($unit->property->landlord->first_name ?? '').' '.($unit->property->landlord->last_name ?? '')) ?: '—' }}</td>
                                    <td class="px-4 py-3"><x-admin.status :status="$unit->media->count().' '.\Illuminate\Support\Str::plural('photo', $unit->media->count())" :tone="$unit->media->count() > 0 ? 'green' : 'red'" /></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="text-[13.5px] text-[#060D26]">{{ $unit->created_at?->format('M j, Y') }}</p>
                                        <p class="text-[12px] text-[#5B6A8E]">{{ $unit->created_at?->diffForHumans() }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right text-[13.5px] font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($unit->rental_fee, 2) }}</td>
                                    <td class="px-4 py-3"><x-admin.status :status="$unit->verification_status" /></td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.units.show', [$unit->property, $unit]) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">Review</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($units->hasPages())
                    <div class="border-t border-[#E2E4EC] px-4 py-3">{{ $units->links() }}</div>
                @endif
            @endif
        </x-card>

        <x-admin.queue-aside :recent="$recent->map(fn ($u) => ['name' => $u->unit_label, 'sub' => ($u->property->title ?? '—').' · '.$u->updated_at->diffForHumans(), 'status' => $u->verification_status, 'href' => route('admin.units.show', [$u->property_id, $u->unit_id])])->all()"
            :checklist="['The photos show this unit, not the building in general.', 'The rent is realistic for the area and the unit type.', 'The unit belongs to a property that is already approved.', 'The details (floor, beds, inclusions) match the photos.']"
            note="A rejection needs a reason; the landlord will see it." />
    </div>
@endsection
