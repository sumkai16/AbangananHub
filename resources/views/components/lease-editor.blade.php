@props([
    // Form field the edited wording posts under. Empty value = "use the policy as it is".
    'name' => 'lease_body',
    // Wording already chosen for this form (the policy's saved wording, or nothing).
    'value' => '',
    // What the editor opens on when nothing is chosen yet.
    'initial' => '',
    // The standard clauses, for "Reset to standard wording".
    'starter' => '',
    // POST endpoint that renders the tenant's view. Null hides the Preview tab.
    'previewUrl' => null,
    // Name shown in the editor's title bar.
    'title' => 'Policy wording',
    'button' => 'Edit policy',
    'usingLabel' => 'Using the policy as it is',
    'editedLabel' => 'Edited for this tenant',
    'useTemplateLabel' => 'Use the policy as it is',
    // A settings-row layout (title, live summary, "Open editor") for the policy page, instead of a bare button.
    'row' => false,
    'rowTitle' => 'Wording',
])

{{--
    Full-screen, Docs-style wording editor (plans/editable-lease-document.md): a white page on a grey desk, with the
    toolbar above it. The editor is TipTap, loaded the first time it opens (resources/js/lease-editor.js). The summary
    of key terms and the house rules are system-made and not editable here; names, amounts and dates go in as merge
    fields, filled in when the agreement is sent.
--}}
<div x-data="leaseEditor({
        value: @js($value),
        initial: @js($initial),
        starter: @js($starter),
        previewUrl: @js($previewUrl),
        fields: @js(\App\Support\LeaseBody::FIELDS),
    })"
    @keydown.escape.window="open && close()">

    <input type="hidden" name="{{ $name }}" :value="value">

    @if($row)
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <span class="block text-[14px] font-semibold text-[#060D26]">{{ $rowTitle }}</span>
                <span class="block text-[12.5px] text-[#5B6A8E]" x-text="summary"></span>
            </div>
            <button type="button" @click="openEditor()"
                class="inline-flex h-9 shrink-0 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[12.5px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">{{ $button }}</button>
        </div>
    @else
        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <button type="button" @click="openEditor()"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                {{ $button }}
            </button>
            <span class="text-[11.5px] text-[#5B6A8E]" x-text="edited ? @js($editedLabel) : @js($usingLabel)"></span>
        </div>
    @endif

    {{-- The whole screen, like Docs: title bar, toolbar, then the page on a grey desk --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex flex-col bg-[#F1F3F9]" role="dialog" aria-modal="true" aria-label="Edit {{ $title }}">
        <header class="flex flex-shrink-0 items-center gap-2 border-b border-[#E2E4EC] bg-white px-2 py-2 sm:gap-3 sm:px-4">
            <button type="button" @click="close()" aria-label="Close without saving"
                class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-[#5B6A8E] hover:bg-[#F1F3F9] hover:text-[#060D26]">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[15px] font-semibold text-[#060D26]">{{ $title }}</p>
                <p class="hidden text-[11.5px] text-[#5B6A8E] sm:block">Wording &middot; the summary of key terms and house rules are added automatically</p>
            </div>
            @if($previewUrl)
                <div class="flex shrink-0 items-center gap-1 rounded-lg bg-[#ECEEF6] p-1" role="tablist">
                    <button type="button" role="tab" :aria-selected="tab === 'edit'" @click="tab = 'edit'"
                        :class="tab === 'edit' ? 'bg-white shadow-sm' : 'text-[#5B6A8E]'" class="rounded-md px-3 py-1.5 text-[12px] font-semibold text-[#060D26]">Edit</button>
                    <button type="button" role="tab" :aria-selected="tab === 'preview'" @click="showPreview()"
                        :class="tab === 'preview' ? 'bg-white shadow-sm' : 'text-[#5B6A8E]'" class="rounded-md px-3 py-1.5 text-[12px] font-semibold text-[#060D26]">Preview</button>
                </div>
            @endif
            <button type="button" @click="use()" class="h-10 shrink-0 rounded-xl bg-[#FF8A66] px-5 text-[13px] font-bold text-[#060D26] hover:bg-[#E96F4F]">Done</button>
        </header>

        @include('components.lease-editor-toolbar', ['name' => $name])

        <div class="min-h-0 flex-1 overflow-y-auto sm:px-6 sm:py-8">
            <div x-show="tab === 'edit'" class="mx-auto w-full max-w-[816px]">
                <div class="min-h-[70vh] bg-white px-5 py-7 sm:min-h-[1056px] sm:rounded-sm sm:px-16 sm:py-14 sm:shadow-[0_1px_4px_rgba(6,13,38,0.18)]" x-ref="surface"></div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-[12px] sm:px-0">
                    <button type="button" x-show="!confirmReset" @click="confirmReset = true" class="font-semibold text-[#5B6A8E] underline underline-offset-2">Reset to standard wording</button>
                    <span x-show="confirmReset" x-cloak class="flex flex-wrap items-center gap-2 text-[#060D26]">
                        Replace everything with the standard wording?
                        <button type="button" @click="reset()" class="font-bold text-[#DC2626] underline underline-offset-2">Yes, reset</button>
                        <button type="button" @click="confirmReset = false" class="font-semibold text-[#5B6A8E] underline underline-offset-2">No</button>
                    </span>
                    <button type="button" x-show="edited" @click="useTemplate()" class="font-semibold text-[#5B6A8E] underline underline-offset-2">{{ $useTemplateLabel }}</button>
                </div>
            </div>
            <div x-show="tab === 'preview'" x-cloak class="mx-auto w-full max-w-[816px] bg-white px-5 py-7 sm:min-h-[1056px] sm:rounded-sm sm:px-16 sm:py-14 sm:shadow-[0_1px_4px_rgba(6,13,38,0.18)]">
                <p x-show="previewBusy" class="text-[13px] text-[#5B6A8E]">Loading preview…</p>
                <p x-show="previewError" x-text="previewError" class="text-[13px] font-semibold text-[#DC2626]"></p>
                <div x-show="!previewBusy && !previewError" x-html="previewHtml"></div>
            </div>
        </div>
    </div>
</div>
