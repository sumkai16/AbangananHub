@extends('layouts.landlord')

@section('content')
<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8" x-data="{ openType: null, addingOptional: false }">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors w-fit mb-6">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Properties
        </a>

        @if($errors->any())
            <div class="max-w-2xl mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
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

        @if(session('success'))
            <div class="max-w-2xl mb-6 px-4 py-3 rounded-xl bg-[#22C55E]/[0.08] border border-[#22C55E]/25 text-[#15803D] text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-11">

            <x-property-wizard-stepper current="documents" :property="$property" />

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Step 4 of 6</p>
                <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">Verify you're the owner</h1>
                <p class="mt-2 text-sm text-[#64748B] leading-relaxed max-w-md">Only admins see these — never shown to renters.</p>

                @php
                    $requiredTypes = ['Proof of Ownership', 'Tax Declaration', 'Business Permit'];
                    $optionalTypes = array_values(array_diff(\App\Models\PropertyDocument::TYPES, $requiredTypes));
                    $byType = $documents->keyBy('document_type');
                    $uploadedOptional = collect($optionalTypes)->filter(fn ($t) => $byType->get($t) && ! $byType->get($t)->isRequested());
                    $availableOptionalTypes = collect($optionalTypes)->diff($uploadedOptional)->mapWithKeys(fn ($t) => [$t => $t]);
                @endphp

                {{-- ── Required ─────────────────────────────────── --}}
                <div class="mt-7 max-w-2xl space-y-3">
                    @foreach($requiredTypes as $type)
                        @php $document = $byType->get($type); @endphp
                        @include('landlord.properties.wizard.partials.document-row', ['type' => $type, 'document' => $document, 'required' => true])
                    @endforeach
                </div>

                {{-- ── Optional ──────────────────────────────────── --}}
                <div class="mt-6 max-w-2xl">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-3">Other documents (optional)</p>

                    @if($uploadedOptional->isNotEmpty())
                        <div class="space-y-3 mb-3">
                            @foreach($uploadedOptional as $type)
                                @include('landlord.properties.wizard.partials.document-row', ['type' => $type, 'document' => $byType->get($type), 'required' => false])
                            @endforeach
                        </div>
                    @endif

                    @if($availableOptionalTypes->isNotEmpty())
                        <button type="button" x-show="!addingOptional" @click="addingOptional = true"
                            class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-dashed border-[#E2E8F0] hover:border-[#2AA7A1] hover:bg-[#F7FCFC] text-[12.5px] font-semibold text-[#156F8C] transition-colors duration-150">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add another document
                        </button>

                        <div x-show="addingOptional" x-cloak class="border border-[#E2E8F0] rounded-xl p-4 bg-[#F7FCFC]">
                            <form method="POST" action="{{ route('landlord.properties.documents.store', $property) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-[11.5px] font-semibold text-[#1F2937] mb-1.5">Document type</label>
                                    <x-styled-select name="document_type" required :options="$availableOptionalTypes->all()" placeholder="Choose a type"
                                        class="h-10 w-full rounded-lg border border-[#E2E8F0] px-3 text-[13px] text-[#1F2937] bg-white" />
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        class="flex-1 min-w-[180px] text-[12.5px] border border-[#E2E8F0] rounded-lg px-3 py-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-[#EEF8F8] file:text-[#156F8C] file:text-xs file:font-semibold">
                                    <button type="submit" class="h-9 px-4 rounded-xl bg-[#1F2937] text-white text-[12.5px] font-semibold hover:brightness-95 transition-all duration-200 shrink-0">
                                        Upload
                                    </button>
                                    <button type="button" @click="addingOptional = false" class="text-[12px] font-medium text-[#64748B] hover:text-[#1F2937] transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="mt-7 pt-5 border-t border-[#E2E8F0] max-w-2xl flex items-center gap-3">
                    <a href="{{ route('properties.wizard.amenities', $property) }}"
                        class="px-5 py-3 rounded-xl text-sm font-semibold text-[#1F2937] bg-white border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                        Back
                    </a>
                    <a href="{{ route('properties.wizard.units', $property) }}"
                        class="ml-auto px-9 py-3 rounded-xl text-sm font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all duration-150">
                        Save & Continue
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
