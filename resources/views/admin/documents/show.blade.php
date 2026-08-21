@extends('layouts.admin')

@section('page-title', 'Document Review')

@section('content')
<div class="max-w-3xl mx-auto" x-data="{ showReject: false }">

    {{-- Back --}}
    <a href="{{ route('admin.documents.index') }}"
        class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors mb-5">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Property Documents
    </a>

    {{-- Header --}}
    <x-card flush class="px-5 py-4 mb-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-[#1F2937] leading-tight">{{ $document->document_type }}</h1>
            <p class="text-[12px] text-[#94A3B8] mt-0.5">
                <a href="{{ route('admin.catalogue.properties.show', $document->property) }}" class="hover:text-[#156F8C] hover:underline">
                    {{ $document->property->title }}
                </a>
                — {{ trim(($document->property->landlord->first_name ?? '') . ' ' . ($document->property->landlord->last_name ?? '')) ?: '—' }}
            </p>
        </div>
        <x-document-status-badge :document="$document" />
    </x-card>

    {{-- Meta --}}
    <x-card class="mb-4">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div>
                <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Document no.</dt>
                <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">{{ $document->document_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Expiry date</dt>
                <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">{{ $document->expiry_date?->format('M j, Y') ?? '—' }}</dd>
            </div>
            @if($document->status === 'Verified' && $document->verifier)
                <div>
                    <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Verified by</dt>
                    <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">
                        {{ trim($document->verifier->first_name . ' ' . $document->verifier->last_name) }}
                        <span class="text-[#94A3B8] font-normal">{{ $document->verified_at?->format('M j, Y') }}</span>
                    </dd>
                </div>
            @endif
            @if($document->requester)
                <div>
                    <dt class="text-[11px] text-[#94A3B8] font-semibold uppercase tracking-wide">Requested by</dt>
                    <dd class="text-[14px] text-[#1F2937] font-medium mt-0.5">
                        {{ trim($document->requester->first_name . ' ' . $document->requester->last_name) }}
                    </dd>
                </div>
            @endif
        </dl>
    </x-card>

    {{-- Rejection reason --}}
    @if($document->status === 'Rejected' && $document->rejection_reason)
        <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/20 rounded-xl p-4 mb-4 flex gap-3">
            <svg class="w-5 h-5 text-[#DC2626] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
            </svg>
            <div>
                <p class="text-[12px] font-bold uppercase tracking-wider text-[#DC2626] mb-1">Rejection reason</p>
                <p class="text-[13px] text-[#1F2937]">{{ $document->rejection_reason }}</p>
            </div>
        </div>
    @endif

    {{-- Preview --}}
    <x-card class="mb-4">
        <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#94A3B8] mb-3">Document</h2>
        @if($document->file_path)
            @php $isPdf = str_ends_with(strtolower($document->file_name ?? ''), '.pdf'); @endphp
            <x-document-preview
                :preview-url="route('admin.documents.preview', $document)"
                :is-pdf="$isPdf"
                :alt="$document->document_type"
                height="h-[28rem]" />
            <a href="{{ route('admin.documents.download', $document) }}"
               class="inline-flex items-center gap-1.5 mt-3 h-8 px-3 rounded-full border border-[#64748B]/30 text-[#1F2937] text-xs font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                Download
            </a>
        @else
            <p class="text-[13.5px] text-[#94A3B8] italic">Awaiting the landlord's upload — nothing to review yet.</p>
        @endif
    </x-card>

    {{-- Admin action --}}
    @if($document->file_path && $document->status === 'Pending')
        <x-card>
            <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Admin action</h2>

            <div class="flex gap-2 mb-3">
                <form method="POST" action="{{ route('admin.properties.documents.verify', [$document->property, $document]) }}"
                    data-confirm="Verify this document?" data-confirm-type="confirm" data-confirm-button="Verify" class="flex-1">
                    @csrf
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-lg bg-[#22C55E] hover:brightness-95 text-white text-[13px] font-bold transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Verify
                    </button>
                </form>

                <button type="button" @click="showReject = !showReject"
                    class="flex-1 inline-flex items-center justify-center gap-2 h-10 rounded-lg bg-[#EF4444] hover:bg-[#EF4444] text-white text-[13px] font-bold transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reject
                </button>
            </div>

            <div x-show="showReject" x-cloak x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0" class="border-t border-[#E2E8F0] pt-3">
                <form method="POST" action="{{ route('admin.properties.documents.reject', [$document->property, $document]) }}"
                    data-confirm="Reject this document?" data-confirm-type="warning"
                    data-confirm-message="The landlord will see your reason." data-confirm-button="Reject">
                    @csrf
                    <label for="rejection_reason" class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1.5">
                        Reason for rejection
                    </label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="3" required
                        class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2.5 text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all resize-none"
                        placeholder="Explain why — the landlord will see this."></textarea>
                    @error('rejection_reason')
                        <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                    @enderror
                    <div class="mt-2.5 flex gap-2">
                        <button type="submit"
                            class="h-9 px-4 rounded-lg bg-[#EF4444] hover:bg-[#EF4444] text-white text-[12px] font-bold transition-colors">
                            Confirm rejection
                        </button>
                        <button type="button" @click="showReject = false"
                            class="h-9 px-4 rounded-lg border border-[#E2E8F0] text-[12px] font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </x-card>
    @endif

</div>
@endsection
