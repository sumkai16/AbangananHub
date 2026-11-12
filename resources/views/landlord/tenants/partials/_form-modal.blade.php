{{--
    Add Tenant and Edit tenant (TenantRequest). $dispatch('open-tenant-form', {}) adds; passing { action, first_name,
    last_name, email, contact_number } edits. The emergency contact and ID are never sent to the page for editing:
    on edit they start blank and a blank field keeps what is saved (TenantDetail::saveFor).
--}}
@php
    $input = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
    $label = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';
    $err = 'mt-1 text-[11.5px] font-semibold text-[#EF4444]';
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
        get editing() { return !!this.action; },
        open(detail) {
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
                class="relative w-full sm:max-w-xl max-h-[92vh] overflow-y-auto bg-white border border-[#E2E4EC] rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div>
                        <h2 id="tenant-form-title" class="text-[17px] font-normal text-[#060D26]" x-text="editing ? 'Edit tenant' : 'Add tenant'"></h2>
                        <p class="text-[12.5px] text-[#5B6A8E] mt-0.5" x-text="editing
                            ? 'Changing the email sends a new invite to that address.'
                            : 'They show as Pending until you give them a contract. With an email, we invite them to set a password.'"></p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form method="POST" :action="action ?? @js(route('landlord.tenants.store'))" class="px-6 pb-6 grid gap-5">
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PATCH"></template>
                    <input type="hidden" name="_form" value="tenant">
                    <input type="hidden" name="_action" :value="action ?? ''">

                    <div class="grid gap-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Basic information</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="tenant_first_name" class="{{ $label }}">First name</label>
                                <input id="tenant_first_name" type="text" name="first_name" x-model="form.first_name" required maxlength="100" class="{{ $input }}">
                                @error('first_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="tenant_last_name" class="{{ $label }}">Last name</label>
                                <input id="tenant_last_name" type="text" name="last_name" x-model="form.last_name" required maxlength="100" class="{{ $input }}">
                                @error('last_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="tenant_email" class="{{ $label }}">Email <span class="font-normal text-[#5B6A8E]">(for the invite)</span></label>
                                <input id="tenant_email" type="email" name="email" x-model="form.email" maxlength="255" class="{{ $input }}">
                                @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="tenant_contact" class="{{ $label }}">Mobile number</label>
                                <input id="tenant_contact" type="tel" name="contact_number" x-model="form.contact_number" maxlength="20" placeholder="09XX XXX XXXX" class="{{ $input }}">
                                @error('contact_number')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Emergency contact and ID</p>
                        <p x-show="editing" class="-mt-1 text-[12px] text-[#5B6A8E]">Leave a field blank to keep what is saved.</p>
                        <x-tenant-details-fields id-prefix="tenantform" />{{-- shows its own field errors --}}
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="close()" class="h-11 px-5 rounded-full border border-[#5B6A8E]/30 text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Cancel</button>
                        <button type="submit" class="h-11 px-6 rounded-full bg-[#FF8A66] text-[13.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer"
                            x-text="editing ? 'Save changes' : 'Add tenant'"></button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
