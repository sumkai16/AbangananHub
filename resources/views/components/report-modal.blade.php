@props(['targetType' => 'user', 'targetId', 'heading' => 'Report this user', 'subtitle' => ''])

{{--
    Report a user or listing without leaving the page. Open it with `$dispatch('open-report')` from any
    button on the page. Posts JSON to reports.store (which returns JSON when the request expects it, and
    keeps its redirect for the standalone /reports page), then confirms through the global x-confirm-modal.
    Same flow as the listing modal on properties/show. One per page, since it listens on `window`.
--}}
<template x-teleport="body">
    <div x-data="{
            open: false,
            category: '',
            details: '',
            sending: false,
            error: '',
            async submit() {
                if (this.sending) return;
                this.sending = true;
                this.error = '';
                try {
                    const res = await fetch('{{ route('reports.store') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({
                            target_type: @js($targetType),
                            {{ $targetType === 'user' ? 'reported_user_id' : 'property_id' }}: @js($targetId),
                            category: this.category,
                            details: this.details,
                        }),
                    });
                    if (!res.ok) {
                        const body = await res.json().catch(() => ({}));
                        this.error = body.errors ? Object.values(body.errors).flat()[0] : 'Could not submit your report. Please try again.';
                        return;
                    }
                    const data = await res.json();
                    this.open = false;
                    this.category = '';
                    this.details = '';
                    window.dispatchEvent(new CustomEvent('show-modal', { detail: { type: 'success', title: 'Report submitted', message: data.message } }));
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.sending = false;
                }
            },
        }"
        x-on:open-report.window="open = true"
        x-show="open" x-cloak class="fixed inset-0 z-[200] overflow-y-auto bg-black/40"
        x-on:keydown.escape.window="open = false">
        <div class="min-h-full flex items-end sm:items-center justify-center sm:p-6">
            <div x-on:click.outside="open = false" x-show="open" x-transition role="dialog" aria-modal="true" aria-labelledby="report-modal-title"
                class="w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-white shadow-xl">

                <div class="px-5 sm:px-6 py-4 border-b border-[#E2E4EC] flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <h2 id="report-modal-title" class="text-[16px] font-bold text-[#060D26]">{{ $heading }}</h2>
                        @if($subtitle)<p class="mt-0.5 text-[12px] text-[#5B6A8E] truncate">{{ $subtitle }}</p>@endif
                    </div>
                    <button type="button" x-on:click="open = false" aria-label="Close"
                        class="shrink-0 -mr-2 w-11 h-11 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form x-on:submit.prevent="submit" class="px-5 sm:px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-1.5">What's wrong?</label>
                        <x-styled-select x-model="category"
                            :options="array_combine(\App\Http\Controllers\ReportController::CATEGORIES, \App\Http\Controllers\ReportController::CATEGORIES)"
                            placeholder="Choose a reason"
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 text-sm text-[#060D26]" />
                    </div>

                    <div>
                        <label for="report-details" class="block text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-1.5">
                            Details <span class="normal-case tracking-normal font-semibold">· optional</span></label>
                        <textarea id="report-details" x-model="details" rows="4" maxlength="1000"
                            placeholder="Tell us what happened. The more specific, the faster we can act."
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 text-[16px] sm:text-sm text-[#060D26] placeholder:text-[#5B6A8E]/60 focus:border-[#FF8A66] focus:ring-4 focus:ring-[#FF8A66]/10 outline-none transition-all resize-none"></textarea>
                        <p class="mt-1 text-[11px] font-semibold text-[#5B6A8E]/70 text-right"><span x-text="details.length"></span>/1000</p>
                    </div>

                    <template x-if="error">
                        <p class="text-[12px] font-semibold text-[#DC2626]" x-text="error"></p>
                    </template>

                    <p class="text-[12px] text-[#5B6A8E] leading-relaxed">Reports go to the AbangananHub team. The person you report won't be told who filed it.</p>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" :disabled="!category || sending"
                            class="flex-1 sm:flex-none h-11 px-6 rounded-xl bg-[#EF4444] text-white text-sm font-bold hover:brightness-95 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                            x-text="sending ? 'Sending…' : 'Submit report'"></button>
                        <button type="button" x-on:click="open = false"
                            class="h-11 px-4 rounded-xl text-sm font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors cursor-pointer">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
