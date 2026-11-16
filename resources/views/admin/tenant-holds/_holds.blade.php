{{-- Pending, On hold and History share one table; the last column changes with the tab. Times in the app timezone. --}}
@php
    $tz = config('rentals.timezone');
    $empty = [
        'pending' => ['No reports waiting', 'When a landlord reports a tenant for unpaid rent, it shows up here for you to confirm or reject.'],
        'active'  => ['Nobody is on hold', null],
        'history' => ['Nothing here yet', 'Released and rejected holds appear here.'],
    ][$tab];
@endphp

@if($rows->isEmpty())
    <x-card flush><x-admin.empty :title="$empty[0]" :note="$empty[1]" /></x-card>
@else
    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px]">
                <thead>
                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                        <x-admin.th>Tenant</x-admin.th>
                        <x-admin.th>Reported by</x-admin.th>
                        <x-admin.th>Place</x-admin.th>
                        <x-admin.th>Owed</x-admin.th>
                        <x-admin.th>Reason</x-admin.th>
                        <x-admin.th>{{ $tab === 'history' ? 'Outcome' : 'Reported' }}</x-admin.th>
                        @if($tab !== 'history')<x-admin.th>Action</x-admin.th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E4EC]">
                    @foreach($rows as $hold)
                        @php
                            $tenant = $hold->tenant;
                            $unit = $hold->reservation?->unit?->unit_label;
                            $property = $hold->reservation?->property?->title;
                        @endphp
                        <tr class="align-top hover:bg-[#F7F8FC] transition-colors duration-200">
                            <td class="px-4 py-3">
                                @if($tenant)
                                    <a href="{{ route('admin.users.show', $tenant) }}" class="text-[12.5px] font-semibold text-[#060D26] hover:underline">{{ trim($tenant->first_name.' '.$tenant->last_name) }}</a>
                                    <p class="max-w-[220px] truncate text-[11px] text-[#5B6A8E]">{{ $tenant->email }}</p>
                                @else
                                    <p class="text-[12.5px] text-[#5B6A8E]">Deleted account</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[12.5px] text-[#060D26]">
                                @if($hold->landlord)
                                    {{ trim($hold->landlord->first_name.' '.$hold->landlord->last_name) }}
                                @else
                                    Admin
                                    @if($hold->source_hold_id)<p class="text-[11px] text-[#5B6A8E]">Possible duplicate of hold #{{ $hold->source_hold_id }}</p>@endif
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[12.5px] text-[#060D26]">
                                @if($property)
                                    <p class="max-w-[200px] truncate font-semibold">{{ $property }}</p>
                                    @if($unit)<p class="text-[11px] text-[#5B6A8E]">{{ $unit }}</p>@endif
                                @else
                                    <span class="text-[#5B6A8E]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-[12.5px] font-semibold text-[#060D26]">
                                @if((float) $hold->amount_owed > 0)₱{{ number_format((float) $hold->amount_owed, 2) }}@else<span class="font-normal text-[#5B6A8E]">—</span>@endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="max-w-[300px] text-[12.5px] text-[#060D26]">{{ $hold->reason }}</p>
                                @if($hold->tenant_note)
                                    <p class="mt-1 max-w-[300px] rounded-lg bg-[#F7F8FC] px-2.5 py-1.5 text-[11.5px] text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">Tenant says:</span> {{ $hold->tenant_note }}</p>
                                @endif
                            </td>

                            @if($tab === 'history')
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$hold->statusLabel()" :tone="$hold->status === 'released' ? 'green' : 'red'" />
                                    <p class="mt-1 text-[11.5px] text-[#5B6A8E]">
                                        @if($hold->status === 'rejected')
                                            {{ $hold->rejected_note }}
                                        @else
                                            {{ \App\Models\TenantHold::RELEASE_REASONS[$hold->release_reason] ?? '' }}
                                        @endif
                                    </p>
                                    <p class="text-[11px] text-[#5B6A8E]" title="{{ $hold->updated_at?->timezone($tz)->format('M j, Y g:i A') }}">{{ $hold->updated_at?->diffForHumans() }}</p>
                                </td>
                            @else
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-[12.5px] font-semibold text-[#060D26]" title="{{ $hold->flagged_at?->timezone($tz)->format('M j, Y g:i A') }}">{{ $hold->flagged_at?->diffForHumans() }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">{{ $hold->flagged_at?->timezone($tz)->format('M j, Y') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if($tab === 'pending')
                                        <div x-data="{ rejecting: false }" class="w-[230px]">
                                            <div x-show="!rejecting" class="flex gap-2">
                                                <form method="POST" action="{{ route('admin.tenant-holds.confirm', $hold) }}"
                                                    data-confirm="Put this tenant on hold?" data-confirm-type="warning"
                                                    data-confirm-message="They will not be able to start new reservations or chats. They can still pay and message the landlord."
                                                    data-confirm-button="Confirm hold">
                                                    @csrf
                                                    <button type="submit" class="h-9 rounded-xl bg-[#FF8A66] px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors hover:bg-[#E96F4F]">Confirm</button>
                                                </form>
                                                <button type="button" x-on:click="rejecting = true"
                                                    class="h-9 rounded-xl border border-[#E2E4EC] px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">Reject</button>
                                            </div>
                                            <form x-show="rejecting" x-cloak method="POST" action="{{ route('admin.tenant-holds.reject', $hold) }}" class="space-y-2">
                                                @csrf
                                                <label for="note-{{ $hold->hold_id }}" class="block text-[11px] font-semibold uppercase tracking-widest text-[#5B6A8E]">Why is it rejected?</label>
                                                <textarea id="note-{{ $hold->hold_id }}" name="note" rows="2" required maxlength="1000"
                                                    class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2 text-[12.5px] text-[#060D26] focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20"></textarea>
                                                <div class="flex gap-2">
                                                    <button type="submit" class="h-9 rounded-xl bg-[#060D26] px-3.5 text-[13px] font-semibold text-white transition-colors hover:bg-[#060D26]/90">Reject report</button>
                                                    <button type="button" x-on:click="rejecting = false" class="h-9 px-2 text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26]">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('admin.tenant-holds.release', $hold) }}"
                                            data-confirm="Release this hold?" data-confirm-type="confirm"
                                            data-confirm-message="The tenant gets full use of their account again."
                                            data-confirm-button="Release">
                                            @csrf
                                            <button type="submit" class="h-9 rounded-xl border border-[#E2E4EC] px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">Release</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-t border-[#E2E4EC]">
            <p class="text-[12.5px] text-[#5B6A8E]">
                Showing <span class="font-semibold text-[#060D26]">{{ $rows->firstItem() }}–{{ $rows->lastItem() }}</span> of
                <span class="font-semibold text-[#060D26]">{{ $rows->total() }}</span>
            </p>
            {{ $rows->links() }}
        </div>
    </x-card>
@endif
