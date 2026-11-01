{{--
    A modal whose body is fetched when it opens, so emergency contacts are not embedded in the page for every row.
    Used by the Contracts page (event 'open-contract') and the Tenants page (event 'open-tenant'):
    $dispatch(event, { url }). Optional include vars: $event, $label.
--}}
@php($event = $event ?? 'open-contract')
@php($label = $label ?? 'Contract details')
<div x-data="{
        show: false,
        loading: false,
        failed: false,
        html: '',
        async open(url) {
            this.show = true;
            this.loading = true;
            this.failed = false;
            this.html = '';
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                if (!res.ok) throw new Error(res.status);
                this.html = await res.text();
            } catch (e) {
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },
        close() { this.show = false; },
     }"
     x-on:{{ $event }}.window="open($event.detail.url)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-label="{{ $label }}">
            <div x-show="show" x-transition.opacity @click="close()" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            <div x-show="show"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-6 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="relative w-full sm:max-w-xl max-h-[92vh] overflow-y-auto bg-white border border-[#E2E4EC] rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <button type="button" @click="close()" aria-label="Close"
                    class="absolute right-4 top-4 w-8 h-8 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-colors duration-200 cursor-pointer z-10">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>

                <div x-show="loading" class="px-6 py-16 text-center text-[13px] text-[#5B6A8E]">Loading…</div>
                <div x-show="failed" class="px-6 py-16 text-center text-[13px] text-[#B91C1C]">This could not be loaded. Close this and try again.</div>
                <div x-show="!loading && !failed" x-html="html"></div>
            </div>
        </div>
    </template>
</div>
