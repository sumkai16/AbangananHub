{{--
    The read-only policy reader on the Policies page. It fills the work area, not the whole window: from `lg` up it starts
    at the sidebar's edge (left-64, or 5rem when the sidebar is collapsed, see layouts/landlord) and sits under the sidebar
    like the page panel (z 45 against 50), so the sidebar stays visible and usable. On a phone it is the whole screen. Any button can open it:
        $dispatch('open-policy-reader', { url: '<landlord.lease-templates.read url>', title: 'Policy name', editUrl: '<edit url or empty>' })
    It fetches the wording (a lease filled in with one of the landlord's properties and a sample tenant, nothing saved),
    shows it over the page, and gives it back on close: focus returns to the button that opened it. Esc and the Close
    button leave; Tab stays inside. Lives inside the page's swap region, so a tab swap re-creates it cleanly.
--}}
<div x-data="{
        show: false,
        loading: false,
        failed: false,
        html: '',
        title: '',
        editUrl: '',
        opener: null,
        async open(detail) {
            this.opener = document.activeElement;
            this.title = detail.title || 'Policy';
            this.editUrl = detail.editUrl || '';
            this.html = '';
            this.failed = false;
            this.loading = true;
            this.show = true;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.dialog && this.$refs.dialog.focus());
            try {
                const res = await fetch(detail.url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(res.status);
                this.html = (await res.json()).html;
            } catch (e) {
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },
        close() {
            if (!this.show) return;
            this.show = false;
            document.documentElement.classList.remove('overflow-hidden');
            this.$nextTick(() => this.opener && this.opener.isConnected && this.opener.focus());
        },
        trap(e) {
            const items = [...this.$refs.dialog.querySelectorAll('a[href], button:not([disabled])')].filter(el => el.offsetParent !== null);
            if (!items.length) return;
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.dialog)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        },
     }"
     x-on:open-policy-reader.window="open($event.detail)"
     x-on:keydown.escape.window="close()"
     x-on:beforeunload.window="document.documentElement.classList.remove('overflow-hidden')">

    <template x-teleport="body">
        <div x-show="show" x-cloak x-ref="dialog" tabindex="-1" role="dialog" aria-modal="true" :aria-label="'Read policy: ' + title"
            @keydown.tab="trap($event)"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 motion-reduce:translate-y-0"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            data-sidebar-offset
            class="fixed inset-0 z-[45] flex flex-col bg-[#F7F8FC] outline-none lg:left-64">

            <div class="flex shrink-0 items-center gap-3 border-b border-[#E2E4EC] bg-white px-4 py-3 sm:px-6">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#060D26]" aria-hidden="true">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Reading policy</p>
                    <h2 class="truncate text-[16px] font-bold leading-tight tracking-tight text-[#060D26] sm:text-[18px]" x-text="title"></h2>
                </div>
                <a x-show="editUrl" x-cloak :href="editUrl" data-page-swap="nav" @click="close()"
                    class="hidden h-10 shrink-0 items-center rounded-xl border border-[#060D26]/35 bg-white px-4 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#ECEEF6] sm:inline-flex">Edit policy</a>
                <button type="button" @click="close()"
                    class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-xl bg-[#FF8A66] px-4 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    Close
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
                    <p class="mb-4 text-[12.5px] leading-relaxed text-[#5B6A8E]">This is how a tenant reads it. Names, rent and dates are samples from one of your properties; nothing here is sent or saved.</p>
                    <div x-show="loading" class="rounded-2xl border border-[#E2E4EC] bg-white px-6 py-16 text-center text-[13px] text-[#5B6A8E]">Loading…</div>
                    <div x-show="failed" x-cloak class="rounded-2xl border border-[#E2E4EC] bg-white px-6 py-16 text-center text-[13px] text-[#B91C1C]">This could not be loaded. Close this and try again.</div>
                    <article x-show="!loading && !failed" x-cloak x-html="html" class="rounded-2xl border border-[#E2E4EC] bg-white p-5 shadow-[0_1px_3px_rgba(6,13,38,0.06)] sm:p-9"></article>
                    <a x-show="editUrl" x-cloak :href="editUrl" data-page-swap="nav" @click="close()"
                        class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-xl border border-[#060D26]/35 bg-white text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#ECEEF6] sm:hidden">Edit policy</a>
                </div>
            </div>
        </div>
    </template>
</div>
