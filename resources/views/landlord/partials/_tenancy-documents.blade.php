{{--
    The landlord's side of the documents a lease asked for: open each file, approve it, send it back with a reason,
    or excuse the tenant from it. Needs $reservation. Narrow on purpose: it sits in the chat's side card.
    Approval is the landlord's record only; it never blocks the tenant's payment or move-in.
--}}
@php
    $docs = $reservation->documents()->orderBy('tenancy_document_id')->get();
    $tenantFirst = $reservation->tenant?->first_name ?? 'The tenant';
    $pill = [
        'Requested' => ['Not uploaded', 'bg-[#FBBF24]/[0.15] text-[#8A5A00]'],
        'Pending' => ['To review', 'bg-[#3B82F6]/[0.12] text-[#1D4ED8]'],
        'Approved' => ['Approved', 'bg-[#22C55E]/[0.12] text-[#15803D]'],
        'Rejected' => ['Sent back', 'bg-[#EF4444]/[0.10] text-[#B91C1C]'],
        'Waived' => ['Not needed', 'bg-[#94A3B8]/[0.15] text-[#5B6A8E]'],
    ];
    $toReview = $docs->where('status', 'Pending')->count();
    $ended = in_array($reservation->rental_status, \App\Models\Reservation::TERMINAL_STATUSES, true);
@endphp
@if($docs->isNotEmpty())
    <div class="border-t border-[#E2E4EC] pt-3" id="tenant-documents" aria-label="Tenant documents">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#5B6A8E]">Documents</p>
            @if($toReview)
                <span class="rounded-full bg-[#FF8A66] px-2 py-0.5 text-[10px] font-bold text-[#060D26]">{{ $toReview }} to review</span>
            @endif
        </div>
        @error('document')<p role="alert" class="mt-1 text-[11.5px] font-semibold text-[#DC2626]">{{ $message }}</p>@enderror
        <ul class="mt-2 flex flex-col gap-2">
            @foreach($docs as $doc)
                <li class="rounded-xl border border-[#E2E4EC] px-3 py-2" x-data="{ reject: false }">
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 text-[12.5px] font-semibold leading-snug text-[#060D26]">{{ $doc->label }}</p>
                        <span class="inline-flex h-5 shrink-0 items-center rounded-full px-2 text-[10px] font-bold {{ $pill[$doc->status][1] }}">{{ $pill[$doc->status][0] }}</span>
                    </div>
                    @if($doc->hasFile())
                        <a href="{{ route('tenancy-documents.file', $doc) }}" target="_blank" rel="noopener"
                            class="mt-1 block truncate text-[11.5px] font-semibold text-[#060D26] underline underline-offset-2">View {{ $doc->file_name }}</a>
                    @elseif($doc->purged_at)
                        <p class="mt-1 text-[11.5px] text-[#5B6A8E]">File deleted {{ $doc->purged_at->format('M j, Y') }}.</p>
                    @else
                        <p class="mt-1 text-[11.5px] text-[#5B6A8E]">{{ $tenantFirst }} has not uploaded this yet.</p>
                    @endif
                    @if($doc->status === 'Rejected' && $doc->rejection_reason)
                        <p class="mt-1 text-[11.5px] italic text-[#B91C1C]">&ldquo;{{ $doc->rejection_reason }}&rdquo;</p>
                    @endif

                    @unless($ended)
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @if($doc->status === 'Pending')
                                <form method="POST" action="{{ route('tenancy-documents.approve', $doc) }}">@csrf
                                    <button type="submit" class="h-8 rounded-lg bg-[#FF8A66] px-3 text-[11.5px] font-bold text-[#060D26] hover:bg-[#E96F4F]">Approve</button>
                                </form>
                            @endif
                            @if($doc->status === 'Pending')
                                <button type="button" @click="reject = !reject" class="h-8 rounded-lg border border-[#EF4444] px-3 text-[11.5px] font-semibold text-[#EF4444] hover:bg-[#EF4444]/5">Send back</button>
                            @endif
                            @if(in_array($doc->status, ['Requested', 'Pending', 'Rejected'], true))
                                <form method="POST" action="{{ route('tenancy-documents.waive', $doc) }}"
                                    data-confirm="Excuse {{ $tenantFirst }} from this document?" data-confirm-message="{{ $tenantFirst }} will be able to sign without uploading {{ $doc->label }}." data-confirm-button="Not needed">
                                    @csrf
                                    <button type="submit" class="h-8 rounded-lg border border-[#E2E4EC] px-3 text-[11.5px] font-semibold text-[#060D26] hover:bg-[#F7F8FC]">Not needed</button>
                                </form>
                            @endif
                            @if(in_array($doc->status, ['Approved', 'Rejected', 'Waived'], true))
                                <form method="POST" action="{{ route('tenancy-documents.reopen', $doc) }}">@csrf
                                    <button type="submit" class="h-8 rounded-lg border border-[#E2E4EC] px-3 text-[11.5px] font-semibold text-[#5B6A8E] hover:bg-[#F7F8FC]">Reopen</button>
                                </form>
                            @endif
                        </div>
                        <form x-show="reject" x-cloak method="POST" action="{{ route('tenancy-documents.reject', $doc) }}" class="mt-2 flex flex-col gap-1.5">
                            @csrf
                            <label class="sr-only" for="reject-doc-{{ $doc->tenancy_document_id }}">What should {{ $tenantFirst }} fix?</label>
                            <input id="reject-doc-{{ $doc->tenancy_document_id }}" name="rejection_reason" required minlength="3" maxlength="255" placeholder="e.g. The photo is blurry"
                                class="h-9 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12px] text-[#060D26] placeholder-[#5B6A8E] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                            <button type="submit" class="h-8 self-start rounded-lg bg-[#EF4444] px-3 text-[11.5px] font-bold text-white hover:brightness-95">Send back</button>
                        </form>
                    @endunless
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-[11px] leading-relaxed text-[#5B6A8E]">Opening a file is logged. They are deleted 30 days after the tenancy ends.</p>
    </div>
@endif
