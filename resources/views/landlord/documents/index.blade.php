@extends('layouts.landlord')

@section('content')
<div x-data="{ uploadOpen: false }" @keydown.escape.window="uploadOpen = false">

<div class="max-w-[880px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

    {{-- Breadcrumb --}}
    <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#64748B] mb-2">
        <a href="{{ route('landlord.properties.index') }}" class="hover:text-[#1F2937] transition-colors duration-200">Properties</a>
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <a href="{{ route('landlord.properties.show', $property) }}" class="hover:text-[#1F2937] transition-colors duration-200">{{ $property->title }}</a>
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-[#1F2937] font-medium">Documents</span>
    </div>

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Verification Documents</h1>
            <p class="text-sm text-[#64748B] mt-1 max-w-lg">Proof of ownership, tax declaration, and permits for this property. These are only ever seen by admins — never shown to renters.</p>
        </div>

        @php
            $claimedTypes = $documents->pluck('document_type')->unique();
            $availableTypes = collect(\App\Models\PropertyDocument::TYPES)->diff($claimedTypes);
        @endphp
        @if($availableTypes->isNotEmpty())
            <button type="button" @click="uploadOpen = true"
                    class="inline-flex items-center gap-1.5 h-10 px-5 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 shrink-0">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Upload a document
            </button>
        @endif
    </div>

    @php
        $requested = $documents->filter(fn ($d) => $d->isRequested());
        $submitted = $documents->reject(fn ($d) => $d->isRequested());
        $verifiedCount = $submitted->where('status', 'Verified')->count();
        $pendingCount  = $submitted->where('status', 'Pending')->count();
        $rejectedCount = $submitted->where('status', 'Rejected')->count();
    @endphp

    {{-- Status strip — only earns its place once there's more than one document to
         summarize; with a single document its own card badge already says it all. --}}
    @if($documents->count() > 1)
        @php
            $strip = [
                ['dot' => '#22C55E', 'label' => 'Verified', 'value' => $verifiedCount],
                ['dot' => '#FBBF24', 'label' => 'Pending review', 'value' => $pendingCount],
                ['dot' => '#EF4444', 'label' => 'Rejected', 'value' => $rejectedCount],
                ['dot' => '#3B82F6', 'label' => 'Awaiting upload', 'value' => $requested->count()],
            ];
        @endphp
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 mb-6">
            @foreach($strip as $s)
                <span class="inline-flex items-center gap-1.5 text-xs text-[#64748B]">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $s['dot'] }}"></span>
                    <span class="font-semibold text-[#1F2937]">{{ $s['value'] }}</span> {{ $s['label'] }}
                </span>
            @endforeach
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <div class="space-y-0.5">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if($documents->isEmpty())

        {{-- Empty state --}}
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-14 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">No documents on file yet</p>
            <p class="text-[13px] text-[#64748B] mt-1 mb-5 max-w-sm mx-auto">Upload proof of ownership or other legal documents so admins can verify this property.</p>
            <button type="button" @click="uploadOpen = true"
                    class="inline-flex items-center gap-1.5 h-10 px-5 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Upload a document
            </button>
        </div>

    @else

        <div class="space-y-8">

            {{-- Requested — admin is waiting on these --}}
            @if($requested->isNotEmpty())
                <div>
                    <h2 class="text-xs font-bold text-[#64748B] uppercase tracking-widest mb-3">Requested by admin — awaiting upload</h2>
                    <div class="space-y-3">
                        @foreach($requested as $document)
                            <div x-data="{ open: false }" class="bg-[#3B82F6]/[0.05] border border-[#3B82F6]/20 rounded-2xl p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-[#1F2937]">{{ $document->document_type }}</p>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <x-document-status-badge :document="$document" />
                                        <button type="button" @click="open = !open"
                                                class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#1F2937] text-white text-xs font-semibold hover:brightness-95 transition-all duration-200">
                                            Upload file
                                        </button>
                                    </div>
                                </div>

                                <form x-show="open" x-cloak method="POST" action="{{ route('landlord.properties.documents.replace', [$property, $document]) }}"
                                      enctype="multipart/form-data" class="mt-3 pt-3 border-t border-[#3B82F6]/20 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="document_type" value="{{ $document->document_type }}">
                                    <div class="flex-1 min-w-[180px]">
                                        <label class="block text-xs font-medium text-[#64748B] mb-1">File (PDF or image)</label>
                                        <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                                               class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-[#EEF8F8] file:text-[#156F8C] file:text-xs file:font-medium">
                                    </div>
                                    <div class="min-w-[120px]">
                                        <label class="block text-xs font-medium text-[#64748B] mb-1">Doc. no.</label>
                                        <input type="text" name="document_number" maxlength="100" class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2 bg-white">
                                    </div>
                                    <div class="min-w-[140px]">
                                        <label class="block text-xs font-medium text-[#64748B] mb-1">Expiry</label>
                                        <input type="date" name="expiry_date" class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2 bg-white">
                                    </div>
                                    <button type="submit" class="h-9 px-4 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                                        Upload
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Submitted documents --}}
            @if($submitted->isNotEmpty())
                <div>
                    <h2 class="text-xs font-bold text-[#64748B] uppercase tracking-widest mb-3">Submitted</h2>
                    <div class="space-y-4">
                        @foreach($submitted as $document)
                            @php $isPdf = str_ends_with(strtolower($document->file_name ?? ''), '.pdf'); @endphp
                            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <div>
                                        <p class="text-sm font-semibold text-[#1F2937]">{{ $document->document_type }}</p>
                                        <p class="text-xs text-[#64748B] mt-0.5">
                                            @if($document->document_number)
                                                No. {{ $document->document_number }}
                                                @if($document->expiry_date) &middot; @endif
                                            @endif
                                            @if($document->expiry_date)
                                                Expires {{ $document->expiry_date->format('M j, Y') }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-document-status-badge :document="$document" />
                                </div>

                                @if($document->status === 'Rejected' && $document->rejection_reason)
                                    <div class="mb-3 px-3 py-2 rounded-lg bg-[#EF4444]/[0.07] text-[#DC2626] text-xs">
                                        <strong>Reason:</strong> {{ $document->rejection_reason }}
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <x-document-preview
                                        :preview-url="route('landlord.properties.documents.preview', [$property, $document])"
                                        :is-pdf="$isPdf"
                                        :alt="$document->document_type"
                                        height="h-48" />
                                </div>

                                <div class="flex flex-wrap items-center gap-2" x-data="{ resubmitting: false }">
                                    <a href="{{ route('landlord.properties.documents.download', [$property, $document]) }}"
                                       class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full border border-[#64748B]/30 text-[#1F2937] text-xs font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                                        Download
                                    </a>

                                    @if($document->status === 'Rejected')
                                        <button type="button" @click="resubmitting = !resubmitting"
                                                class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#1F2937] text-white text-xs font-semibold hover:brightness-95 transition-all duration-200">
                                            Resubmit
                                        </button>
                                    @endif

                                    @if($document->status !== 'Verified')
                                        <form method="POST" action="{{ route('landlord.properties.documents.destroy', [$property, $document]) }}"
                                              data-confirm="Remove this document?" data-confirm-type="error" data-confirm-button="Remove">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-[#DC2626] text-xs font-medium hover:bg-[#EF4444]/[0.07] transition-colors duration-200">
                                                Delete
                                            </button>
                                        </form>
                                    @endif

                                    @if($document->status === 'Rejected')
                                        <form x-show="resubmitting" x-cloak method="POST" action="{{ route('landlord.properties.documents.replace', [$property, $document]) }}"
                                              enctype="multipart/form-data" class="w-full mt-3 pt-3 border-t border-[#E2E8F0] flex flex-wrap items-end gap-3">
                                            @csrf
                                            <input type="hidden" name="document_type" value="{{ $document->document_type }}">
                                            <div class="flex-1 min-w-[180px]">
                                                <label class="block text-xs font-medium text-[#64748B] mb-1">New file (PDF or image)</label>
                                                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                       class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-[#EEF8F8] file:text-[#156F8C] file:text-xs file:font-medium">
                                            </div>
                                            <div class="min-w-[120px]">
                                                <label class="block text-xs font-medium text-[#64748B] mb-1">Document no.</label>
                                                <input type="text" name="document_number" value="{{ $document->document_number }}" maxlength="100" class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2">
                                            </div>
                                            <div class="min-w-[140px]">
                                                <label class="block text-xs font-medium text-[#64748B] mb-1">Expiry date</label>
                                                <input type="date" name="expiry_date" value="{{ $document->expiry_date?->format('Y-m-d') }}" class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2">
                                            </div>
                                            <button type="submit" class="h-9 px-4 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                                                Resubmit
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

    @endif

</div>

{{-- ═══ Upload panel — slides in from the right ═══ --}}
<div x-show="uploadOpen" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[999] bg-black/40" @click.self="uploadOpen = false">
    <div x-show="uploadOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col">

        <div class="flex items-center justify-between px-6 py-5 border-b border-[#E2E8F0]">
            <h2 class="text-base font-bold text-[#1F2937]">Upload a document</h2>
            <button type="button" @click="uploadOpen = false"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-[#64748B] hover:bg-[#EEF8F8] hover:text-[#1F2937] transition-colors">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('landlord.properties.documents.store', $property) }}" enctype="multipart/form-data"
              class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-[#64748B] mb-1">Document type</label>
                @php
                    $documentTypeOptions = ['' => 'Select a document type'] + $availableTypes->mapWithKeys(fn ($t) => [$t => $t])->all();
                @endphp
                <x-styled-select name="document_type" required :options="$documentTypeOptions" :selected="old('document_type', '')"
                    class="h-10 w-full rounded-lg border border-[#E2E8F0] px-3 text-sm text-[#1F2937] bg-white" />
            </div>
            <div>
                <label class="block text-xs font-medium text-[#64748B] mb-1">File (PDF or image, max 10MB)</label>
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                       class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2.5 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-[#EEF8F8] file:text-[#156F8C] file:text-xs file:font-medium">
            </div>
            <div>
                <label class="block text-xs font-medium text-[#64748B] mb-1">Document no. (optional)</label>
                <input type="text" name="document_number" maxlength="100" class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2.5">
            </div>
            <div>
                <label class="block text-xs font-medium text-[#64748B] mb-1">Expiry date (optional)</label>
                <input type="date" name="expiry_date" class="w-full text-sm border border-[#E2E8F0] rounded-lg px-3 py-2.5">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit" class="flex-1 h-10 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                    Upload document
                </button>
                <button type="button" @click="uploadOpen = false"
                        class="h-10 px-4 rounded-full border border-[#E2E8F0] text-sm font-medium text-[#64748B] hover:text-[#1F2937] transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
