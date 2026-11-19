{{-- The listing's history, newest first: what the landlord sees, written by the observers. Read-only plain text, because the landlord's own links lead into landlord-only pages. --}}
@if($lines->isEmpty())
    <p class="py-6 text-center text-[13px] text-[#5B6A8E]">Nothing has happened on this listing yet.</p>
@else
    <ul class="divide-y divide-[#E2E4EC]">
        @foreach($lines as $activity)
            @php
                $role = ['landlord' => 'Landlord', 'tenant' => 'Tenant', 'admin' => 'Admin'][$activity->actor_role] ?? 'System';
                $chip = match ($activity->actor_role) {
                    'landlord' => 'bg-[#ECEEF6] text-[#060D26]',
                    'tenant'   => 'bg-[#FF8A66]/15 text-[#B35A3D]',
                    'admin'    => 'bg-[#060D26] text-white',
                    default    => 'bg-[#F7F8FC] text-[#5B6A8E] ring-1 ring-inset ring-[#E2E4EC]',
                };
            @endphp
            <li class="flex items-start gap-3 py-3">
                <span class="mt-0.5 inline-flex h-6 min-w-[4rem] shrink-0 items-center justify-center rounded-full px-2.5 text-[11px] font-semibold {{ $chip }}">{{ $role }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13.5px] leading-snug text-[#060D26]">{{ $activity->summary }}</span>
                    <time datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ $activity->created_at->format('M j, Y \a\t g:i A') }}" class="mt-0.5 block text-[12px] text-[#5B6A8E]">{{ $activity->created_at->format($activity->created_at->isCurrentYear() ? 'M j · g:i A' : 'M j, Y') }}</time>
                </span>
            </li>
        @endforeach
    </ul>
@endif
