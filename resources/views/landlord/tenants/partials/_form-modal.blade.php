{{--
    Add Tenant and Edit tenant (TenantRequest). $dispatch('open-tenant-form', {}) adds; passing { action, first_name,
    last_name, email, contact_number } edits. The emergency contact and ID are never sent to the page for editing:
    on edit they start blank and a blank field keeps what is saved (TenantDetail::saveFor).

    Same shell and field styles as Add lease (contracts/partials/_add-modal): fixed header and footer, only the fields
    scroll, 600px dialog or a bottom sheet on phones. Two groups (who they are → emergency contact and ID) separated by
    space and a hairline, no section labels.
--}}
@php
    $input = 'h-11 w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[14px] text-[#060D26] placeholder-[#94A3B8] hover:border-[#5B6A8E]/40 focus:outline-none focus:border-[#FF8A66]/60 focus:ring-4 focus:ring-[#FF8A66]/10 transition-all duration-200';
    $label = 'block text-[12.5px] font-semibold text-[#060D26] mb-1.5';
    $hint = 'mt-1.5 text-[12px] text-[#5B6A8E]';
    $err = 'mt-1.5 text-[12px] font-semibold text-[#DC2626]';
    $reopen = $errors->any() && old('_form') === 'tenant';
@endphp

<div x-data="{
        show: false,
        action: @js($reopen ? old('_action') : null),
        form: {
            first_name: @js($reopen ? old('first_name') : ''),
            last_name: @js($reopen ? old('last_name') : ''),
            email: @js($reopen ? old('email') : ''),
            contact_number: @js($reopen ? old('contact_number') : ''),
        },
        saving: false,
        errors: {},
        fields: ['first_name', 'last_name', 'email', 'contact_number', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number', 'id_type', 'id_number'],
        clear(name) { if (name && this.errors[name]) { const e = { ...this.errors }; delete e[name]; this.errors = e; } },
        {{-- Same rules as TenantRequest and TenantDetail::rules(false); the server stays the final check. --}}
        validate(f) {
            const e = {}, phone = /^[0-9+-s()]{7,20}$/, v = (k) => (f.get(k) || '').trim();
            if (!v('first_name')) e.first_name = ['Enter the tenant’s first name.'];
            if (!v('last_name')) e.last_name = ['Enter the tenant’s last name.'];
            if (v('email') && !/^[^s@]+@[^s@]+.[^s@]+$/.test(v('email'))) e.email = ['Enter a valid email address.'];
            if (!v('email') && !v('contact_number')) e.contact_number = ['Enter a mobile number, or an email so we can invite them.'];
            else if (v('contact_number') && !phone.test(v('contact_number'))) e.contact_number = ['Enter a valid mobile number.'];
            if (v('emergency_contact_number') && !phone.test(v('emergency_contact_number'))) e.emergency_contact_number = ['Enter a valid phone number for your emergency contact.'];
            return e;
        },
        get editing() { return !!this.action; },
        err(key) { return (this.errors[key] || [])[0] || ''; },
        get otherErrors() {
            return Object.entries(this.errors)
                .filter(([k]) => !fields.includes(k))
                .map(([, v]) => v[0]);
        },
        {{-- Add saves in the background: no page reload, the form closes into the 'Tenant added' popup and the list
             behind it refreshes. Edit keeps the plain form post. --}}
        async submit(e) {
            this.errors = this.validate(new FormData(e.target));
            const first = Object.keys(this.errors)[0];
            if (first) { this.$nextTick(() => e.target.querySelector(`[name='${first}']`)?.focus()); return; }
            if (this.editing) { e.target.submit(); return; }
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch(e.target.action, {
                    method: 'POST',
                    body: new FormData(e.target),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.status === 422) { this.errors = (await res.json()).errors || {}; return; }
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();
                this.show = false;
                e.target.reset();
                this.form = { first_name: '', last_name: '', email: '', contact_number: '' };
                window.dispatchEvent(new CustomEvent('tenant-added', { detail: data.tenant_added }));
                this.refreshList();
            } catch (err) {
                this.errors = { _: ['The tenant could not be saved. Check your connection and try again.'] };
            } finally {
                this.saving = false;
            }
        },
        async refreshList() {
            try {
                const html = await (await fetch(window.location.href, { headers: { 'Accept': 'text/html' } })).text();
                const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('tenants-list');
                const here = document.getElementById('tenants-list');
                if (fresh && here) here.innerHTML = fresh.innerHTML;
            } catch (e) { /* the list catches up on the next visit */ }
        },
        open(detail) {
            this.errors = {};
            const keepOld = detail && detail.action === this.action && @js($reopen);
            this.action = detail?.action ?? null;
            if (!keepOld) {
                this.form = {
                    first_name: detail?.first_name ?? '',
                    last_name: detail?.last_name ?? '',
                    email: detail?.email ?? '',
                    contact_number: detail?.contact_number ?? '',
                };
            }
            this.show = true;
        },
        close() { this.show = false; },
     }"
     x-on:open-tenant-form.window="open($event.detail)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="tenant-form-title">
            <div x-show="show" x-transition.opacity @click="close()" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            <div x-show="show"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-6 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                style="max-width: 600px; max-height: 94vh;"
                class="relative w-full flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)] overflow-hidden">

                <div class="shrink-0 flex items-start justify-between gap-4 px-5 sm:px-7 pt-6 pb-4 border-b border-[#E2E4EC]">
                    <div>
                        <h2 id="tenant-form-title" class="text-[20px] font-semibold text-[#060D26] leading-tight" x-text="editing ? 'Edit tenant' : 'Add tenant'"></h2>
                        <p class="text-[13px] text-[#5B6A8E] mt-1" x-text="editing
                            ? 'Changing the email sends a new invite to that address.'
                            : 'They show as Pending until you give them a lease. With an email, we invite them to set a password.'"></p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-10 h-10 -mr-2 -mt-1.5 shrink-0 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50 transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form method="POST" :action="action ?? @js(route('landlord.tenants.store'))" class="flex-1 min-h-0 flex flex-col"
                    @submit.prevent="submit($event)" @input="clear($event.target.name)" @change="clear($event.target.name)" novalidate>
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PATCH"></template>
                    <input type="hidden" name="_form" value="tenant">
                    <input type="hidden" name="_action" :value="action ?? ''">

                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 sm:px-7 py-5 grid gap-5">
                        {{-- Who they are, and how to reach them --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tenant_first_name" class="{{ $label }}">First name</label>
                                <input id="tenant_first_name" type="text" :class="err('first_name') && '!border-[#DC2626]'" name="first_name" x-model="form.first_name" required maxlength="100" autocomplete="off" class="{{ $input }}">
                                @error('first_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                <p x-show="err('first_name')" x-cloak class="{{ $err }}" x-text="err('first_name')"></p>
                            </div>
                            <div>
                                <label for="tenant_last_name" class="{{ $label }}">Last name</label>
                                <input id="tenant_last_name" type="text" :class="err('last_name') && '!border-[#DC2626]'" name="last_name" x-model="form.last_name" required maxlength="100" autocomplete="off" class="{{ $input }}">
                                @error('last_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                <p x-show="err('last_name')" x-cloak class="{{ $err }}" x-text="err('last_name')"></p>
                            </div>
                            <div>
                                <label for="tenant_email" class="{{ $label }}">Email <span class="font-normal text-[#5B6A8E]">(for the invite)</span></label>
                                <input id="tenant_email" type="email" :class="err('email') && '!border-[#DC2626]'" name="email" x-model="form.email" maxlength="255" inputmode="email" autocomplete="off" class="{{ $input }}">
                                @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                <p x-show="err('email')" x-cloak class="{{ $err }}" x-text="err('email')"></p>
                            </div>
                            <div>
                                <label for="tenant_contact" class="{{ $label }}">Mobile number</label>
                                <input id="tenant_contact" type="tel" :class="err('contact_number') && '!border-[#DC2626]'" name="contact_number" x-model="form.contact_number" maxlength="20" inputmode="tel" autocomplete="off" placeholder="0917 123 4567" class="{{ $input }} tabular-nums">
                                @error('contact_number')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                <p x-show="err('contact_number')" x-cloak class="{{ $err }}" x-text="err('contact_number')"></p>
                                <p x-show="!err('contact_number')" class="{{ $hint }}">Their Philippine mobile. Needed if there's no email.</p>
                            </div>
                        </div>

                        {{-- Emergency contact and ID: the labels say what this group is --}}
                        <div class="grid gap-4 border-t border-[#E2E4EC] pt-5">
                            <p x-show="editing" class="text-[12px] text-[#5B6A8E]">Leave a field blank to keep what is saved.</p>
                            <x-tenant-details-fields id-prefix="tenantform" tall inline />{{-- shows its own field errors --}}
                        </div>

                        {{-- Errors from a background save that have no field above (the emergency contact and ID fields,
                             or a failed request). --}}
                        <div x-show="otherErrors.length" x-cloak role="alert" class="rounded-xl border border-[#EF4444]/25 bg-[#EF4444]/[0.06] px-3.5 py-2.5">
                            <template x-for="message in otherErrors" :key="message">
                                <p class="text-[12.5px] font-semibold text-[#B91C1C]" x-text="message"></p>
                            </template>
                        </div>
                    </div>

                    {{-- Footer: always visible, so the submit never scrolls out of reach --}}
                    <div class="shrink-0 border-t border-[#E2E4EC] bg-white px-5 sm:px-7 pt-3.5" style="padding-bottom: max(14px, env(safe-area-inset-bottom));">
                        <div class="flex gap-2 w-full sm:w-auto sm:justify-end">
                            <button type="button" @click="close()" class="flex-1 sm:flex-none h-11 px-5 rounded-full text-[14px] font-semibold text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50 transition-colors duration-200 cursor-pointer">Cancel</button>
                            <button type="submit" :disabled="saving" :aria-busy="saving"
                                class="flex-[2] sm:flex-none h-11 px-7 inline-flex items-center justify-center gap-2 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 disabled:cursor-wait transition-colors duration-200 cursor-pointer">
                                <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                <span x-text="editing ? 'Save changes' : (saving ? 'Adding…' : 'Add tenant')"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
