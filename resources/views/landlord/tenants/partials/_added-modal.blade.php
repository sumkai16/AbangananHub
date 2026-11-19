{{--
    Shown once after Add tenant saves: the tenant is on the list as Pending, and the landlord can go straight to Add
    rental with this tenant already picked (lease_url = landlord.contracts.index?new=1&tenant={id}), or leave it.
    Opened by the 'tenant-added' window event (the form saves in the background, _form-modal), or from the
    'tenant_added' flash after a plain form post. Data shape: TenantController::store.
    Sizes are inline so the popup never depends on a rebuilt stylesheet.
--}}
@php($chip = 'inline-flex items-center gap-1 h-6 px-2.5 rounded-full text-[11.5px] font-semibold')
<div x-data="{
        open: false,
        t: { name: '', code: '', invited: false, lease_url: '#' },
        get first() { return this.t.name.split(' ')[0] || this.t.name; },
        show(data) { this.t = data; this.open = true; },
     }"
     x-init="@if(session('tenant_added')) show(@js(session('tenant_added'))) @endif"
     x-on:tenant-added.window="show($event.detail)"
     x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4"
            role="dialog" aria-modal="true" aria-labelledby="tenant-added-title">
            <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            <div x-show="open"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-6 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                style="max-width: 420px; padding: 28px 24px max(24px, env(safe-area-inset-bottom));"
                class="relative w-full bg-white rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <button type="button" @click="open = false" aria-label="Close"
                    class="absolute right-3 top-3 w-9 h-9 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>

                {{-- Who was added, and where they stand --}}
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 shrink-0 rounded-full bg-[#22C55E]/[0.12] text-[#15803D] flex items-center justify-center" aria-hidden="true">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </div>
                    <div class="min-w-0 pr-8">
                        <h2 id="tenant-added-title" class="text-[18px] font-semibold leading-tight text-[#060D26] truncate" x-text="t.name"></h2>
                        <p class="mt-0.5 text-[13px] text-[#5B6A8E]">Added to your tenants</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-1.5">
                    <span class="{{ $chip }} bg-[#ECEEF6] text-[#060D26] tabular-nums" x-text="t.code"></span>
                    <span class="{{ $chip }} bg-[#FBBF24]/[0.14] text-[#92400E]">Pending</span>
                    <span x-show="t.invited" class="{{ $chip }} bg-[#22C55E]/[0.10] text-[#15803D]">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                        Invite sent
                    </span>
                    <span x-show="!t.invited" class="{{ $chip }} bg-[#ECEEF6] text-[#5B6A8E]">No email yet</span>
                </div>

                {{-- The next step --}}
                <div class="mt-5 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC]" style="padding: 14px 16px;">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Next step</p>
                    <p class="mt-1 text-[14px] font-semibold text-[#060D26]">Give <span x-text="first"></span> a rental</p>
                    <p class="mt-0.5 text-[12.5px] leading-relaxed text-[#5B6A8E]">Pick the unit, dates and rent. <span x-text="first"></span> turns Active once the rental starts.</p>
                </div>

                <div class="mt-5 flex flex-col-reverse sm:flex-row gap-2">
                    <button type="button" @click="open = false"
                        class="sm:flex-1 h-11 rounded-full border border-[#060D26]/35 bg-white text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] cursor-pointer transition-colors duration-200">
                        Not now
                    </button>
                    <a :href="t.lease_url"
                        class="sm:flex-1 h-11 inline-flex items-center justify-center gap-1.5 rounded-full bg-[#FF8A66] text-[13.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200">
                        Add rental
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </template>
</div>
