{{--
    The Lease policies modal on the Tenants page. Opened with $dispatch('open-lease-policies', { url }), where url is
    landlord.leases.panel. The body (_panel.blade.php) is fetched when it opens and fetched again after each save, so
    a dropdown or switch changes the policy without leaving the page. Open rows and the scroll position are kept
    across a save. The full page (landlord.leases.index) is still there for the links from the property and unit pages.
--}}
<div x-data="{
        show: false,
        loading: false,
        failed: false,
        html: '',
        url: '',
        tab: 'assign',
        status: '',
        bad: false,
        timer: null,
        async fetchPanel(openIds) {
            const u = new URL(this.url, window.location.origin);
            // null = first load (the server opens the first row). An empty list means 'all closed', sent as an empty `open`.
            if (openIds !== null) {
                if (openIds.length === 0) { u.searchParams.append('open', ''); } else { openIds.forEach(id => u.searchParams.append('open[]', id)); }
            }
            const res = await fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
            if (!res.ok) throw new Error(res.status);
            return res.text();
        },
        async open(url) {
            this.url = url;
            this.show = true;
            this.tab = 'assign';
            this.loading = true;
            this.failed = false;
            this.status = '';
            this.html = '';
            try { this.html = await this.fetchPanel(null); } catch (e) { this.failed = true; } finally { this.loading = false; }
            this.$nextTick(() => this.$refs.dialog && this.$refs.dialog.focus());
        },
        say(message, bad) {
            this.status = message;
            this.bad = bad;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.status = '', 4000);
        },
        async save(form) {
            const body = this.$refs.body;
            const openIds = [...body.querySelectorAll('details[open]')].map(d => d.dataset.id);
            const top = body.scrollTop;
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();
                this.html = await this.fetchPanel(openIds);
                this.$nextTick(() => { body.scrollTop = top; });
                this.say(data.message, false);
            } catch (e) {
                this.say('That did not save. Try again.', true);
            }
        },
        close() { this.show = false; },
     }"
     x-on:open-lease-policies.window="open($event.detail.url)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-label="Lease policies">
            <div x-show="show" x-transition.opacity @click="close()" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            <div x-show="show" x-ref="dialog" tabindex="-1"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-6 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="relative flex w-full max-h-[92dvh] flex-col overflow-hidden rounded-t-2xl border border-[#E2E4EC] bg-white shadow-[0_20px_60px_rgba(6,13,38,0.22)] outline-none sm:max-w-5xl sm:rounded-2xl">

                <div class="flex shrink-0 items-start gap-3 px-5 pb-3 pt-5 sm:px-6">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#060D26]">
                        <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-[18px] font-bold leading-tight tracking-tight text-[#060D26]">Lease policies</h2>
                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">The rules and wording your tenants agree to, per property and unit.</p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#5B6A8E] transition-colors hover:bg-[#F7F8FC] hover:text-[#060D26]">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Every form in the body saves in place: the submit is caught here and sent with fetch --}}
                <div x-ref="body" class="min-h-0 flex-1 overflow-y-auto border-t border-[#E2E4EC]" @submit.prevent="save($event.target)">
                    <div x-show="loading" class="px-6 py-16 text-center text-[13px] text-[#5B6A8E]">Loading…</div>
                    <div x-show="failed" class="px-6 py-16 text-center text-[13px] text-[#B91C1C]">This could not be loaded. Close this and try again.</div>
                    <div x-show="!loading && !failed" x-html="html"></div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-t border-[#E2E4EC] bg-white px-5 py-3.5 sm:px-6">
                    <a href="{{ route('landlord.leases.index') }}" class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-[#5B6A8E] underline-offset-2 hover:text-[#060D26] hover:underline">
                        Open full page
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    </a>
                    <p role="status" aria-live="polite" x-text="status" x-show="status" x-cloak
                        :class="bad ? 'text-[#B91C1C]' : 'text-[#15803D]'" class="min-w-0 flex-1 text-[12.5px] font-semibold sm:text-right"></p>
                    <span x-show="!status" class="flex-1"></span>
                    <a href="{{ route('landlord.lease-templates.create') }}"
                        class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-[#FF8A66] px-4 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F]">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New policy
                    </a>
                </div>
            </div>
        </div>
    </template>
</div>
