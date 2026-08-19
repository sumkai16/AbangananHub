{{--
    One document-type row for the wizard's Documents step.
    $type: string document_type, $document: PropertyDocument|null, $required: bool

    Not-yet-uploaded state is a compact single-click dropzone that auto-submits
    on file selection (this.form.requestSubmit()) — no separate "Upload"
    button, matching how a click-to-pick affordance behaves everywhere else
    a real drop-target is drawn in this app (the property/unit photo
    dropzones), instead of a bare native file input.
--}}

<div class="border border-[#E2E8F0] rounded-xl p-4 {{ $document && !$document->isRequested() ? 'bg-white' : ($required ? 'bg-[#F7FCFC]' : 'bg-white') }}">
    <div class="flex items-center justify-between gap-3">
        <p class="text-[13.5px] font-semibold text-[#1F2937]">
            {{ $type }}
            @if($required)
                <span class="text-[#EF4444]">*</span>
            @endif
        </p>
        @if($document)
            <x-document-status-badge :document="$document" />
        @endif
    </div>

    @if($document && ! $document->isRequested())
        <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-[#E2E8F0]">
            <a href="{{ route('landlord.properties.documents.preview', [$property, $document]) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-[12.5px] font-medium text-[#156F8C] hover:text-[#0E5670] transition-colors truncate">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                {{ $document->file_name }}
            </a>
            <div class="flex items-center gap-3 shrink-0">
                <button type="button" class="text-[11.5px] font-semibold text-[#156F8C] hover:text-[#0E5670] transition-colors" @click="openType = openType === '{{ $type }}' ? null : '{{ $type }}'">View</button>
                @if($document->status !== 'Verified')
                    <form method="POST" action="{{ route('landlord.properties.documents.destroy', [$property, $document]) }}"
                        data-confirm="Remove this document?" data-confirm-type="error" data-confirm-button="Remove">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[11.5px] font-semibold text-[#EF4444] hover:text-[#DC2626] transition-colors">Remove</button>
                    </form>
                @endif
            </div>
        </div>
        @if($document->status === 'Rejected' && $document->rejection_reason)
            <p class="mt-2 text-[11.5px] text-[#DC2626]"><strong>Reason:</strong> {{ $document->rejection_reason }}</p>
        @endif
        <div x-show="openType === '{{ $type }}'" x-cloak class="mt-3">
            @php $isPdf = str_ends_with(strtolower($document->file_name ?? ''), '.pdf'); @endphp
            <x-document-preview :preview-url="route('landlord.properties.documents.preview', [$property, $document])" :is-pdf="$isPdf" :alt="$type" height="h-56" />
        </div>
    @else
        <form method="POST" action="{{ route('landlord.properties.documents.store', $property) }}" enctype="multipart/form-data" class="mt-3 pt-3 border-t border-[#E2E8F0]">
            @csrf
            <input type="hidden" name="document_type" value="{{ $type }}">
            <label class="flex items-center gap-3 px-3.5 py-3 rounded-xl border-2 border-dashed border-[#E2E8F0] hover:border-[#2AA7A1] bg-[#F7FCFC] cursor-pointer transition-colors duration-150 group">
                <div class="w-9 h-9 rounded-lg bg-white shadow-sm border border-[#E2E8F0] flex items-center justify-center shrink-0 text-[#94A3B8] group-hover:text-[#156F8C] transition-colors duration-150">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-[12.5px] text-[#1F2937] min-w-0">
                    <span class="font-semibold text-[#156F8C]">Click to upload</span>
                    <span class="text-[#64748B]"> — PDF, JPG, or PNG</span>
                </span>
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden" onchange="this.form.requestSubmit()">
            </label>
        </form>
    @endif
</div>
