{{-- Documents tab of the landlord property page. Upload, resubmit and delete all happen here; the controller redirects back to #documents. --}}
<div x-show="tab === 'documents'" x-cloak x-data="{ uploadOpen: {{ $errors->hasAny(['file', 'document_type', 'document_number', 'expiry_date']) ? 'true' : 'false' }} }">
    @php
        $docs = $property->documents->sortBy(fn ($d) => $d->isRequested() ? 0 : 1);
        $availableTypes = collect(\App\Models\PropertyDocument::TYPES)->diff($docs->pluck('document_type'));
        $docTypeOptions = ['' => 'Select a document type'] + $availableTypes->mapWithKeys(fn ($t) => [$t => $t])->all();
        $fileInput = 'w-full text-sm border border-[#E2E4EC] rounded-lg px-3 py-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-[#ECEEF6] file:text-[#060D26] file:text-xs file:font-medium';
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <p class="text-xs text-[#5B6A8E]">Only admins see these. They are never shown to renters.</p>
        @if($availableTypes->isNotEmpty())
            <button type="button" @click="uploadOpen = !uploadOpen"
                    class="inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Upload a document
            </button>
        @endif
    </div>

    @if($errors->any())
        <div class="mb-3 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium space-y-0.5">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Upload form --}}
    @if($availableTypes->isNotEmpty())
        <form x-show="uploadOpen" x-cloak method="POST" action="{{ route('landlord.properties.documents.store', $property) }}" enctype="multipart/form-data"
              class="mb-4 rounded-2xl border border-[#E2E4EC] bg-white p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">Document type</label>
                <x-styled-select name="document_type" required :options="$docTypeOptions" :selected="old('document_type', '')"
                    class="h-10 w-full rounded-lg border border-[#E2E4EC] px-3 text-sm text-[#060D26] bg-white" />
            </div>
            <div>
                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">File (PDF or image, max 10MB)</label>
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="{{ $fileInput }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">Document no. (optional)</label>
                <input type="text" name="document_number" maxlength="100" value="{{ old('document_number') }}" class="w-full text-[16px] sm:text-sm border border-[#E2E4EC] rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">Expiry date (optional)</label>
                <input type="date" name="expiry_date" value="{{ old('expiry_date') }}" class="w-full text-[16px] sm:text-sm border border-[#E2E4EC] rounded-lg px-3 py-2">
            </div>
            <div class="sm:col-span-2 flex gap-2">
                <button type="submit" class="h-9 px-5 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200">Upload document</button>
                <button type="button" @click="uploadOpen = false" class="h-9 px-4 rounded-full border border-[#E2E4EC] text-sm font-medium text-[#5B6A8E] hover:text-[#060D26] transition-colors">Cancel</button>
            </div>
        </form>
    @endif

    @if($docs->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-[#ECEEF6]/40 flex flex-col items-center justify-center py-14 text-center">
            <p class="text-sm font-semibold text-[#060D26]">No documents uploaded</p>
            <p class="text-xs text-[#5B6A8E] mt-1">Upload proof of ownership or permits so admins can verify this property.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($docs as $document)
                <x-card flush class="px-4 py-3.5" x-data="{ replacing: false }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#060D26] truncate">{{ $document->document_type }}</p>
                                <p class="text-xs text-[#5B6A8E] mt-0.5 truncate">
                                    @if($document->isRequested())
                                        Requested by admin, no file yet
                                    @else
                                        {{ $document->file_name }}
                                        @if($document->document_number) &middot; No. {{ $document->document_number }} @endif
                                        @if($document->expiry_date) &middot; Expires {{ $document->expiry_date->format('M j, Y') }} @endif
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <x-document-status-badge :document="$document" />
                            @unless($document->isRequested())
                                <a href="{{ route('landlord.properties.documents.preview', [$property, $document]) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center h-8 px-3 rounded-full bg-[#FF8A66] text-[#060D26] text-xs font-semibold hover:bg-[#E96F4F] transition-all duration-200">View</a>
                                <a href="{{ route('landlord.properties.documents.download', [$property, $document]) }}"
                                   class="inline-flex items-center h-8 px-3 rounded-full border border-[#5B6A8E]/30 text-[#060D26] text-xs font-medium hover:bg-[#ECEEF6] transition-colors duration-200">Download</a>
                            @endunless
                            @if($document->isRequested() || $document->status === 'Rejected')
                                <button type="button" @click="replacing = !replacing"
                                        class="inline-flex items-center h-8 px-3 rounded-full border border-[#FF8A66] text-[#B35A3D] text-xs font-semibold hover:bg-[#FF8A66]/10 transition-colors duration-200">
                                    {{ $document->isRequested() ? 'Upload file' : 'Resubmit' }}
                                </button>
                            @endif
                            @if($document->status !== 'Verified')
                                <form method="POST" action="{{ route('landlord.properties.documents.destroy', [$property, $document]) }}"
                                      data-confirm="Remove this document?" data-confirm-type="error" data-confirm-button="Remove">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center h-8 px-3 rounded-full text-[#DC2626] text-xs font-medium hover:bg-[#EF4444]/[0.07] transition-colors duration-200">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if($document->status === 'Rejected' && $document->rejection_reason)
                        <p class="mt-3 px-3 py-2 rounded-lg bg-[#EF4444]/[0.07] text-[#DC2626] text-xs">
                            <strong>Reason:</strong> {{ $document->rejection_reason }}
                        </p>
                    @endif

                    @if($document->isRequested() || $document->status === 'Rejected')
                        <form x-show="replacing" x-cloak method="POST" action="{{ route('landlord.properties.documents.replace', [$property, $document]) }}"
                              enctype="multipart/form-data" class="mt-3 pt-3 border-t border-[#E2E4EC] flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="document_type" value="{{ $document->document_type }}">
                            <div class="flex-1 min-w-[180px]">
                                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">{{ $document->isRequested() ? 'File' : 'New file' }} (PDF or image)</label>
                                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="{{ $fileInput }}">
                            </div>
                            <div class="min-w-[120px]">
                                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">Doc. no.</label>
                                <input type="text" name="document_number" value="{{ $document->document_number }}" maxlength="100" class="w-full text-[16px] sm:text-sm border border-[#E2E4EC] rounded-lg px-3 py-2 bg-white">
                            </div>
                            <div class="min-w-[140px]">
                                <label class="block text-xs font-medium text-[#5B6A8E] mb-1">Expiry</label>
                                <input type="date" name="expiry_date" value="{{ $document->expiry_date?->format('Y-m-d') }}" class="w-full text-[16px] sm:text-sm border border-[#E2E4EC] rounded-lg px-3 py-2 bg-white">
                            </div>
                            <button type="submit" class="h-9 px-4 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200">Submit</button>
                        </form>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
