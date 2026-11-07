{{--
    Add tenant and Edit tenant. $dispatch('open-tenant-form', {}) adds; passing { action, first_name, last_name, email,
    contact_number } edits. Adding is one form: the tenant on the left (with the unit picker), and, once a unit is
    chosen, the contract on the right (the same fields as Add contract, via _contract-fields). With a unit the server
    creates tenant and contract in one transaction (TenantController::store). Below lg the columns become three
    steps (Tenant, Unit, Contract) so no step needs scrolling. Editing is the tenant fields only; the emergency
    contact and ID are never sent to the page, so on edit they start blank and a blank field keeps what is saved
    (TenantDetail::saveFor).
--}}
@php
    $input = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[16px] sm:text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
    $label = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';
    $err = 'mt-1 text-[11.5px] font-semibold text-[#EF4444]';
    $section = 'text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]';
    $reopen = $errors->any() && old('_form') === 'tenant';
    // Free units grouped by property, with photos, for the unit picker.
    $pickerProps = collect($unitOptions ?? [])->values();
    // After a failed save, reopen on the step that holds the first error (phones) and open "More details" if needed.
    $contractKeys = ['move_in_date', 'move_out_date', 'agreed_monthly_rent', 'rent_due_day', 'occupants_count', 'agreement_terms_notes', 'lease_file', 'initial_amount', 'payment_method', 'reference_no'];
    $startStep = ! $reopen ? 1 : ($errors->hasAny($contractKeys) ? 3 : ($errors->has('unit_id') ? 2 : 1));
    $moreOpen = $reopen && $errors->hasAny(['emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number', 'id_type', 'id_number']);
@endphp

<div x-data="{
        show: false,
        // True only until the first open after a failed save: that open keeps the typed values and shows the errors.
        pendingReopen: @js($reopen),
        showErrors: @js($reopen),
        step: @js($startStep),
        moreOpen: @js($moreOpen),
        action: @js($reopen ? old('_action') : null),
        form: {
            first_name: @js($reopen ? old('first_name') : ''),
            last_name: @js($reopen ? old('last_name') : ''),
            email: @js($reopen ? old('email') : ''),
            contact_number: @js($reopen ? old('contact_number') : ''),
            unit_id: @js($reopen ? (string) old('unit_id', '') : ''),
        },
        // Contract state: the names _contract-fields reads.
        moveIn: @js($reopen ? old('move_in_date') : null),
        moveOut: @js($reopen ? old('move_out_date') : null),
        rent: @js($reopen ? old('agreed_monthly_rent') : null),
        terms: @js($reopen ? old('agreement_terms_notes', '') : ''),
        leaseMode: @js($reopen ? old('lease_mode', 'later') : 'later'),
        recordPayment: @js($reopen && (bool) old('initial_amount')),
        method: @js($reopen ? old('payment_method', 'Cash') : 'Cash'),
        today: @js(now()->toDateString()),
        props: @js($pickerProps),
        propId: null,
        get editing() { return !!this.action; },
        get units() { return this.props.find(p => p.id === this.propId)?.units ?? []; },
        get picked() {
            for (const p of this.props) {
                const u = p.units.find(u => String(u.id) === String(this.form.unit_id));
                if (u) return { ...u, property: p.title, terms: p.terms };
            }
            return null;
        },
        get unit() { return this.picked; },
        get paymentRequired() { return !!this.moveIn && this.moveIn <= this.today; },
        get minMoveOut() {
            if (!this.moveIn) return null;
            const d = new Date(this.moveIn + 'T00:00:00');
            d.setMonth(d.getMonth() + 1);
            return d.toISOString().slice(0, 10);
        },
        // Phones: Next moves on while a step has more to do; the submit button shows on the last useful step.
        get phoneNext() { return !this.editing && (this.step === 1 || (this.step === 2 && !!this.picked)); },
        get phoneSubmit() { return this.editing || this.step === 3 || (this.step === 2 && !this.picked); },
        // Open on the property that holds the chosen unit (after a failed save), else the first one.
        showProperty() {
            const holder = this.props.find(p => p.units.some(u => String(u.id) === String(this.form.unit_id)));
            this.propId = (holder ?? this.props[0])?.id ?? null;
        },
        pickUnit() {
            if (!this.picked) return;
            this.rent = this.picked.rent;
            if (!this.terms && this.picked.terms) this.terms = this.picked.terms;
        },
        stepClass(n) {
            if (this.step === n) return 'bg-[#060D26] text-white';
            return 'bg-[#ECEEF6] ' + (n === 3 && !this.picked ? 'text-[#94A3B8]' : 'text-[#060D26]');
        },
        peso(n) { return '₱' + Number(n).toLocaleString('en-PH'); },
        // A step that is hidden on a phone cannot show a browser validation bubble: jump to the step with the problem.
        submitForm(e) {
            const f = this.$refs.form;
            if (f.checkValidity()) return;
            e.preventDefault();
            const bad = f.querySelector(':invalid');
            this.step = Number(bad?.closest('[data-step]')?.dataset.step || 1);
            this.$nextTick(() => f.reportValidity());
        },
        open(detail) {
            // Compare with the action the failed save came from BEFORE overwriting it (an add has none).
            const sameForm = (detail?.action || '') === (this.action || '');
            const keepOld = this.pendingReopen && sameForm;
            this.pendingReopen = false;
            this.action = detail?.action ?? null;
            if (!keepOld) {
                this.showErrors = false;
                this.form = {
                    first_name: detail?.first_name ?? '',
                    last_name: detail?.last_name ?? '',
                    email: detail?.email ?? '',
                    contact_number: detail?.contact_number ?? '',
                    unit_id: '',
                };
                this.moveIn = null; this.moveOut = null; this.rent = null; this.terms = '';
                this.leaseMode = 'later'; this.recordPayment = false; this.method = 'Cash';
                this.step = 1; this.moreOpen = false;
            }
            this.showProperty();
            this.show = true;
        },
        close() { this.show = false; },
     }"
     x-init="showProperty(); $watch('form.unit_id', () => pickUnit())"
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
                class="relative flex w-full max-h-[92dvh] flex-col bg-white border border-[#E2E4EC] rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]"
                :class="editing ? 'sm:max-w-xl' : 'sm:max-w-2xl lg:max-w-5xl xl:max-w-[1200px]'">

                <div class="flex shrink-0 items-start justify-between gap-4 px-6 pt-5 pb-3">
                    <div>
                        <h2 id="tenant-form-title" class="text-[20px] font-semibold text-[#060D26] leading-tight" x-text="editing ? 'Edit tenant' : 'Add tenant'"></h2>
                        <p class="text-[13px] text-[#5B6A8E] mt-1" x-text="editing
                            ? 'Changing the email sends a new invite to that address.'
                            : 'Add the person and, if you pick a unit, their contract too. With an email, we invite them to set a password.'"></p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-10 h-10 -mr-2 -mt-1.5 shrink-0 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50 transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Phones and tablets: where you are. Desktop shows everything at once. --}}
                <ol x-show="!editing" class="lg:hidden shrink-0 mx-6 mb-2 flex gap-1.5 text-[11.5px] font-bold" aria-label="Steps">
                    @foreach([1 => 'Tenant', 2 => 'Unit', 3 => 'Contract'] as $n => $name)
                        <li class="flex-1 rounded-full py-1 text-center transition-colors duration-200" :class="stepClass({{ $n }})"
                            :aria-current="step === {{ $n }} ? 'step' : null">{{ $n }} {{ $name }}</li>
                    @endforeach
                </ol>

                <form method="POST" x-ref="form" :action="action ?? @js(route('landlord.tenants.store'))" enctype="multipart/form-data"
                    @submit="submitForm($event)" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PATCH"></template>
                    <input type="hidden" name="_form" value="tenant">
                    <input type="hidden" name="_action" :value="action ?? ''">
                    {{-- The chosen unit posts from here, so it still posts while another property's tile is showing. --}}
                    <input type="hidden" name="unit_id" :value="form.unit_id">

                    <div class="min-h-0 flex-1 overflow-y-auto px-6 pb-4">
                        @if($reopen && $errors->any())
                            {{-- After a failed save the modal reopens with what was typed; this says what to fix, even when the field is on another step. --}}
                            <div x-show="showErrors" x-cloak role="alert" class="mb-4 rounded-xl border border-[#EF4444]/30 bg-[#EF4444]/[0.06] px-4 py-3 text-[12.5px] text-[#B91C1C]">
                                <p class="font-bold">Nothing was saved. Fix these and press the button again:</p>
                                <ul class="mt-1 list-disc pl-5 space-y-0.5">
                                    @foreach(collect($errors->all())->unique()->take(6) as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="grid gap-x-8 gap-y-4" :class="!editing && 'lg:grid-cols-2 xl:grid-cols-[272px_minmax(0,1fr)_340px]'">

                            {{-- Left: the tenant, then the unit --}}
                            <div class="grid content-start gap-4 xl:contents">
                                <div class="grid content-start gap-3" data-step="1" :class="step === 1 ? '' : 'max-lg:hidden'">
                                    <p class="{{ $section }}">Tenant</p>
                                    <div class="grid grid-cols-2 gap-3">
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
                                        <div class="col-span-2 sm:col-span-1 xl:col-span-2">
                                            <label for="tenant_email" class="{{ $label }}">Email <span class="font-normal text-[#5B6A8E]">(for the invite)</span></label>
                                            <input id="tenant_email" type="email" name="email" x-model="form.email" maxlength="255" class="{{ $input }}">
                                            @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="col-span-2 sm:col-span-1 xl:col-span-2">
                                            <label for="tenant_contact" class="{{ $label }}">Mobile number</label>
                                            <input id="tenant_contact" type="tel" name="contact_number" x-model="form.contact_number" maxlength="20" placeholder="09XX XXX XXXX" class="{{ $input }}">
                                            @error('contact_number')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                        </div>
                                    </div>

                                    {{-- Optional. Collapsed when adding so the form stays short; always open when editing. --}}
                                    <button type="button" x-show="!editing" @click="moreOpen = !moreOpen" :aria-expanded="moreOpen"
                                        class="flex min-h-[44px] lg:min-h-0 items-center gap-1.5 text-left text-[12.5px] font-semibold text-[#060D26] cursor-pointer">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" class="transition-transform duration-200" :class="moreOpen && 'rotate-90'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                        Emergency contact and ID <span class="font-normal text-[#5B6A8E]">(optional)</span>
                                    </button>
                                    <div x-show="editing || moreOpen" x-cloak class="grid gap-3">
                                        <p x-show="editing" class="text-[12px] text-[#5B6A8E]">Leave a field blank to keep what is saved.</p>
                                        <x-tenant-details-fields id-prefix="tenantform" tall inline />{{-- shows its own field errors --}}
                                    </div>
                                </div>

                                {{-- The unit: optional. Picking one adds the contract on the right. --}}
                                <div class="grid content-start gap-3 lg:rounded-2xl lg:border lg:border-[#FF8A66]/40 lg:bg-[#FF8A66]/[0.04] lg:p-4" data-step="2" x-show="!editing" :class="step === 2 ? '' : 'max-lg:hidden'">
                                    <p class="text-[13px] font-bold text-[#060D26]">Property and unit <span class="text-[12px] font-normal text-[#5B6A8E]">(optional)</span></p>

                                    @if($pickerProps->isEmpty())
                                        <p class="text-[12.5px] text-[#5B6A8E]">You have no free unit right now. Add the tenant and give them a contract once a unit is free.</p>
                                    @else
                                        {{-- Property: a photo tile each. One property needs no choosing. --}}
                                        <div x-show="props.length > 1" class="-mx-6 px-6 sm:mx-0 sm:px-0 flex gap-2.5 overflow-x-auto pb-1 snap-x" role="group" aria-label="Property">
                                            <template x-for="p in props" :key="p.id">
                                                <button type="button" @click="propId = p.id" :aria-pressed="propId === p.id"
                                                    class="snap-start shrink-0 w-[132px] sm:w-[168px] lg:w-[132px] xl:w-[176px] text-left rounded-2xl border bg-white p-1.5 transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]"
                                                    :class="propId === p.id ? 'border-[#060D26] ring-2 ring-[#060D26]' : 'border-[#E2E4EC] hover:bg-[#F7F8FC]'">
                                                    <span class="block h-[64px] sm:h-[84px] lg:h-[44px] xl:h-[100px] rounded-xl overflow-hidden bg-[#ECEEF6]">
                                                        <img x-show="p.photo" :src="p.photo" x-on:error="p.photo = null" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                                        <span x-show="!p.photo" class="h-full w-full flex items-center justify-center text-[#94A3B8]">
                                                            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18m16.5-18v18M7.5 7.5h2.25m-2.25 3.75h2.25m4.5-3.75h2.25m-2.25 3.75h2.25M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                                        </span>
                                                    </span>
                                                    <span class="mt-1.5 block px-0.5 text-[12px] font-bold leading-tight text-[#060D26] line-clamp-2" x-text="p.title"></span>
                                                    <span class="block px-0.5 text-[11.5px] text-[#15803D] font-semibold" x-text="p.units.length + (p.units.length === 1 ? ' free unit' : ' free units')"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <p x-show="props.length === 1" class="text-[12.5px] font-semibold text-[#060D26]" x-text="props[0]?.title"></p>

                                        {{-- Units of that property: radio rows, so the arrow keys and screen readers work. --}}
                                        <div class="rounded-2xl border border-[#E2E4EC] overflow-hidden lg:max-h-[168px] xl:max-h-[340px] lg:overflow-y-auto" role="radiogroup" aria-label="Unit">
                                            <div class="grid sm:grid-cols-2 lg:grid-cols-1">
                                                <template x-for="u in units" :key="u.id">
                                                    <label class="relative flex items-center gap-3 p-2.5 xl:p-3 border-b border-[#E2E4EC] cursor-pointer transition-colors duration-200 focus-within:ring-2 focus-within:ring-inset focus-within:ring-[#FF8A66] sm:max-lg:odd:border-r"
                                                        :class="String(form.unit_id) === String(u.id) ? 'bg-[#FF8A66]/10' : 'hover:bg-[#F7F8FC]'">
                                                        <input type="radio" name="unit_pick" :value="u.id" x-model="form.unit_id" class="sr-only">
                                                        <span class="block h-[52px] w-[64px] sm:h-[60px] sm:w-[76px] lg:h-[40px] lg:w-[54px] xl:h-[72px] xl:w-[100px] shrink-0 rounded-xl overflow-hidden bg-[#ECEEF6]">
                                                            <img x-show="u.photo" :src="u.photo" x-on:error="u.photo = null" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                                            <span x-show="!u.photo" class="h-full w-full flex items-center justify-center text-[#94A3B8]">
                                                                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18v-6.75A2.25 2.25 0 0 1 4.5 9h15a2.25 2.25 0 0 1 2.25 2.25V18M2.25 18h19.5M2.25 18v2.25m19.5-2.25v2.25M6 9V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75V9" /></svg>
                                                            </span>
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block text-[13.5px] xl:text-[15px] font-bold text-[#060D26] truncate" x-text="u.label"></span>
                                                            <span class="block text-[12px] leading-snug text-[#5B6A8E]" x-text="(u.floor ? u.floor + ' · ' : '') + u.limit + (u.limit === 1 ? ' person' : ' people') + ' max'"></span>
                                                        </span>
                                                        <span class="text-right shrink-0">
                                                            <span class="block text-[13.5px] xl:text-[15px] font-extrabold text-[#060D26] tabular-nums" x-text="peso(u.rent)"></span>
                                                            <span class="block text-[11px] text-[#5B6A8E]">per month</span>
                                                        </span>
                                                        <span class="h-5 w-5 shrink-0 rounded-full border-2 flex items-center justify-center"
                                                            :class="String(form.unit_id) === String(u.id) ? 'border-[#060D26] bg-[#060D26] text-white' : 'border-[#CBD5E1]'" aria-hidden="true">
                                                            <svg x-show="String(form.unit_id) === String(u.id)" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.4"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4.5 4.5L19 7" /></svg>
                                                        </span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[12px] text-[#5B6A8E]">
                                            <p x-show="picked" x-cloak><span class="font-semibold text-[#060D26]" x-text="picked ? picked.label + ' · ' + picked.property : ''"></span></p>
                                            <p x-show="!picked">Leave it empty to add them now and give them a contract later.</p>
                                            <button type="button" x-show="picked" x-cloak @click="form.unit_id = ''"
                                                class="font-semibold text-[#060D26] underline underline-offset-2 cursor-pointer min-h-[44px] lg:min-h-0">No unit yet</button>
                                        </div>
                                        @error('unit_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                            </div>

                            {{-- Right: the contract. Not in the page at all until a unit is chosen, so nothing in it blocks a plain Add tenant. --}}
                            <div class="grid content-start gap-4" x-show="!editing" data-step="3" :class="step === 3 ? '' : 'max-lg:hidden'">
                                <div x-show="!picked" class="max-lg:hidden flex min-h-[360px] flex-col justify-center rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F7F8FC] px-5 text-center">
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">Contract</p>
                                    <p class="mt-1 text-[12.5px] text-[#5B6A8E]">Pick a unit to set the dates, rent and terms here.<br>Or add the tenant now and give them a contract later.</p>
                                </div>
                                <template x-if="picked && !editing">
                                    <div class="grid gap-4">
                                        <div class="flex items-center gap-2 rounded-xl bg-[#ECEEF6] px-3 py-2 text-[12.5px]">
                                            <span class="font-bold text-[#060D26]">Contract for</span>
                                            <span class="min-w-0 truncate text-[#060D26]" x-text="picked.label + ' · ' + picked.property"></span>
                                        </div>
                                        @include('landlord.contracts.partials._contract-fields', ['idPrefix' => 'tc'])
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Errors from a background save that have no field above (the emergency contact and ID fields,
                             or a failed request). --}}
                        <div x-show="otherErrors.length" x-cloak role="alert" class="rounded-xl border border-[#EF4444]/25 bg-[#EF4444]/[0.06] px-3.5 py-2.5">
                            <template x-for="message in otherErrors" :key="message">
                                <p class="text-[12.5px] font-semibold text-[#B91C1C]" x-text="message"></p>
                            </template>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 border-t border-[#E2E4EC] bg-white px-6 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                        <button type="button" x-show="step > 1 && !editing" @click="step--"
                            class="lg:hidden h-11 px-4 rounded-full text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">‹ Back</button>
                        <div class="ml-auto flex items-center gap-2">
                            <button type="button" @click="close()" :class="step > 1 && !editing ? 'max-lg:hidden' : ''"
                                class="h-11 px-5 rounded-full border border-[#5B6A8E]/30 text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Cancel</button>
                            <button type="button" x-show="phoneNext" @click="step++"
                                class="lg:hidden h-11 px-6 rounded-full bg-[#FF8A66] text-[13.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer"
                                x-text="step === 1 ? 'Next: unit' : 'Next: contract'"></button>
                            <button type="submit" :class="phoneSubmit ? '' : 'max-lg:hidden'"
                                class="h-11 px-6 rounded-full bg-[#FF8A66] text-[13.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer"
                                x-text="editing ? 'Save changes' : (picked ? 'Add tenant and contract' : 'Add tenant')"></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
