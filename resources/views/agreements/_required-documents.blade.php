{{--
    The tenant's side of the documents a lease asks for. One row per requirement: status, Upload / Replace, and the
    landlord's reason when a file was sent back. Needs $reservation (documents loaded or loadable).
    Rules: App\Services\TenancyDocuments. Files are private; only the landlord of the property can open them.
--}}
@php
    $docs = $reservation->documents()->orderBy('tenancy_document_id')->get();
    $landlordFirst = $reservation->property?->landlord?->first_name ?? 'your landlord';
    $pill = [
        'Requested' => ['To upload', 'bg-[#FBBF24]/[0.15] text-[#8A5A00]'],
        'Pending' => ['Waiting for review', 'bg-[#3B82F6]/[0.12] text-[#1D4ED8]'],
        'Approved' => ['Approved', 'bg-[#22C55E]/[0.12] text-[#15803D]'],
        'Rejected' => ['Sent back', 'bg-[#EF4444]/[0.10] text-[#B91C1C]'],
        'Waived' => ['Not needed', 'bg-[#94A3B8]/[0.15] text-[#5B6A8E]'],
    ];
    $outstanding = $docs->filter->isOutstanding()->count();
@endphp
@if($docs->isNotEmpty())
    <div class="mb-5 rounded-2xl border border-[#E2E4EC] bg-white p-5 shadow-[0_1px_3px_rgba(6,13,38,0.06)] print:hidden" id="documents">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-[15px] font-semibold text-[#060D26]">Documents to upload</h3>
            <span class="text-[12px] font-semibold text-[#5B6A8E]">{{ $docs->count() - $outstanding }} of {{ $docs->count() }} done</span>
        </div>
        <p class="mt-1 text-[12px] leading-relaxed text-[#5B6A8E]">
            {{ $landlordFirst }} asks for these before you sign. Only {{ $landlordFirst }} can see them, and they are deleted 30 days after your tenancy ends.
        </p>

        @error('document')<p role="alert" class="mt-3 text-[12px] font-semibold text-[#DC2626]">{{ $message }}</p>@enderror

        <ul class="mt-3 divide-y divide-[#E2E4EC]">
            @foreach($docs as $doc)
                <li class="py-3" x-data="{ name: '' }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 text-[13.5px] font-semibold text-[#060D26]">{{ $doc->label }}</p>
                        <span class="inline-flex h-6 items-center rounded-full px-2.5 text-[11px] font-bold {{ $pill[$doc->status][1] }}">{{ $pill[$doc->status][0] }}</span>
                    </div>
                    @if($doc->status === 'Rejected' && $doc->rejection_reason)
                        <p class="mt-1 text-[12px] text-[#B91C1C]">{{ $landlordFirst }} says: {{ $doc->rejection_reason }}</p>
                    @endif
                    @if($doc->file_name && $doc->hasFile())
                        <p class="mt-1 text-[12px] text-[#5B6A8E]">
                            <a href="{{ route('tenancy-documents.file', $doc) }}" target="_blank" rel="noopener" class="font-semibold text-[#060D26] underline underline-offset-2">{{ $doc->file_name }}</a>
                        </p>
                    @endif
                    @if($doc->tenantCanReplace() && $reservation->rental_status === 'Pending Rental Agreement')
                        <form method="POST" action="{{ route('tenancy-documents.upload', $doc) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf
                            <label class="inline-flex h-10 cursor-pointer items-center rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[12.5px] font-bold text-[#060D26] hover:bg-[#F7F8FC]">
                                <span x-text="name || '{{ $doc->hasFile() ? 'Choose a new file' : 'Choose a file' }}'"></span>
                                <input type="file" name="file" required accept="image/*,application/pdf" class="sr-only"
                                    @change="name = $event.target.files[0]?.name || ''">
                            </label>
                            <button type="submit" x-show="name" x-cloak class="inline-flex h-10 items-center rounded-xl bg-[#FF8A66] px-4 text-[12.5px] font-bold text-[#060D26] hover:bg-[#E96F4F]">{{ $doc->hasFile() ? 'Replace' : 'Upload' }}</button>
                        </form>
                        @error('file')<p class="mt-1 text-[12px] text-[#DC2626]">{{ $message }}</p>@enderror
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
