{{-- A new account that resembles a tenant on hold. A prompt to look, not a verdict: nothing is blocked until you act. --}}
@php $tz = config('rentals.timezone'); @endphp

<p class="mb-4 max-w-3xl text-[13px] text-[#5B6A8E]">
    These accounts share details with a tenant who is on hold. Many are innocent (family or roommates on the same phone or Wi-Fi), so nothing is blocked until you decide. The person is never told who they were compared to.
</p>

@if($rows->isEmpty())
    <x-card flush><x-admin.empty title="No possible duplicates" note="A new account that matches a held tenant by phone, device, or name and network address shows up here." /></x-card>
@else
    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px]">
                <thead>
                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                        <x-admin.th>New account</x-admin.th>
                        <x-admin.th>Looks like</x-admin.th>
                        <x-admin.th>What matched</x-admin.th>
                        <x-admin.th>Found</x-admin.th>
                        <x-admin.th>Action</x-admin.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E4EC]">
                    @foreach($rows as $alert)
                        @php $new = $alert->user; $held = $alert->hold?->tenant; @endphp
                        <tr class="align-top hover:bg-[#F7F8FC] transition-colors duration-200">
                            <td class="px-4 py-3">
                                @if($new)
                                    <a href="{{ route('admin.users.show', $new) }}" class="text-[12.5px] font-semibold text-[#060D26] hover:underline">{{ trim($new->first_name.' '.$new->last_name) }}</a>
                                    <p class="max-w-[220px] truncate text-[11px] text-[#5B6A8E]">{{ $new->email }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">Joined {{ $new->created_at?->timezone($tz)->format('M j, Y') }}</p>
                                @else
                                    <p class="text-[12.5px] text-[#5B6A8E]">Deleted account</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($held)
                                    <a href="{{ route('admin.users.show', $held) }}" class="text-[12.5px] font-semibold text-[#060D26] hover:underline">{{ trim($held->first_name.' '.$held->last_name) }}</a>
                                    <p class="max-w-[220px] truncate text-[11px] text-[#5B6A8E]">{{ $held->email }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">On hold, reported by {{ $alert->hold->landlord ? trim($alert->hold->landlord->first_name.' '.$alert->hold->landlord->last_name) : 'an admin' }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-[260px] flex-wrap gap-1.5">
                                    @foreach($alert->signals ?? [] as $signal)
                                        <x-admin.status :status="\App\Models\HoldMatchAlert::SIGNALS[$signal] ?? $signal" tone="amber" />
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-[12.5px] font-semibold text-[#060D26]" title="{{ $alert->created_at?->timezone($tz)->format('M j, Y g:i A') }}">{{ $alert->created_at?->diffForHumans() }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.hold-alerts.dismiss', $alert) }}">
                                        @csrf
                                        <button type="submit" class="h-9 whitespace-nowrap rounded-xl border border-[#E2E4EC] px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">Not the same person</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hold-alerts.hold', $alert) }}"
                                        data-confirm="Put this account on hold too?" data-confirm-type="warning"
                                        data-confirm-message="They will not be able to start new reservations or chats. The hold lifts when the original hold is released."
                                        data-confirm-button="Put on hold">
                                        @csrf
                                        <button type="submit" class="h-9 whitespace-nowrap rounded-xl bg-[#FF8A66] px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors hover:bg-[#E96F4F]">Put on hold too</button>
                                    </form>
                                </div>
                            </td>
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
