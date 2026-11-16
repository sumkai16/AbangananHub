{{--
    "Add rental": a tenant from the landlord's list or a new one typed here, one free unit, the dates and the rent. Posts to
    landlord.contracts.store (LeaseContractCreator). Opened with $dispatch('open-add-contract'); a failed submit
    reopens it with the old values (old('_form') === 'contract').

    Three steps: 1 Tenant (search the list and click one, or add a new person), 2 Unit and terms, 3 Agreement and
    payment. Next checks the current step before moving on. One column on phones (bottom sheet), two columns from md
    inside steps 2 and 3. Fixed header, stepper and footer; only the fields scroll. Field names are unchanged.
--}}
@php
    $tenantSelect = $tenantOptions->mapWithKeys(fn ($t) => [$t['id'] => $t['label']])->all();
    // Property first, then a unit of that property: one unit dropdown per property, only the picked one rendered.
    $propertySelect = [];
    $unitSelects = [];
    $unitInfo = [];
    foreach ($unitOptions as $property) {
        $propertySelect[$property['id']] = $property['title'].' ('.count($property['units']).' free)';
        foreach ($property['units'] as $unit) {
            $unitSelects[$property['id']][$unit['id']] = $unit['label'];
            $unitInfo[$unit['id']] = $unit + ['property' => (string) $property['id']];
        }
    }
    $propertyTitles = collect($unitOptions)->mapWithKeys(fn ($p) => [(string) $p['id'] => $p['title']])->all();
    $methods = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Check', 'Other'];

    // Three steps: 1 Tenant, 2 Unit and terms, 3 Agreement and payment. After a failed submit, reopen on the step
    // that holds the first error.
    $startStep = 1;
    if (old('_form') === 'contract') {
        $startStep = $errors->hasAny(['landlord_tenant_id', 'first_name', 'last_name', 'contact_number', 'email']) ? 1
            : ($errors->hasAny(['unit_id', 'move_in_date', 'move_out_date', 'agreed_monthly_rent', 'rent_due_day', 'occupants_count', 'agreement_terms_notes']) ? 2 : 3);
    }
    $steps = [1 => 'Tenant', 2 => 'Unit and terms', 3 => 'Agreement and payment'];

    $input = 'h-11 w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[14px] text-[#060D26] placeholder-[#94A3B8] hover:border-[#5B6A8E]/40 focus:outline-none focus:border-[#FF8A66]/60 focus:ring-4 focus:ring-[#FF8A66]/10 transition-all duration-200';
    $label = 'block text-[12.5px] font-semibold text-[#060D26] mb-1.5';
    $hint = 'mt-1.5 text-[12px] text-[#5B6A8E]';
    $err = 'mt-1.5 text-[12px] font-semibold text-[#DC2626]';
    // A group of related fields; groups after the first get a hairline and generous space above.
    $group = 'grid gap-4';
    $groupSep = 'grid gap-4 border-t border-[#E2E4EC] pt-5';
@endphp

<div x-data="{
        show: false,
        step: {{ $startStep }},
        stepError: '',
        tenants: @js($tenantOptions->values()),
        tenantQuery: '',
        newFirst: @js((string) old('first_name', '')),
        newLast: @js((string) old('last_name', '')),
        propertyTitles: @js((object) $propertyTitles),
        units: @js((object) $unitInfo),
        tenantId: @js((string) old('landlord_tenant_id', request('tenant', ''))),
        // 'existing' picks from the list; 'new' creates the tenant in the same submit. An empty list opens on 'new'.
        tenantMode: @js(old('tenant_mode', empty($tenantSelect) ? 'new' : 'existing')),
        duplicateId: @js((string) session('duplicate_tenant_id', '')),
        unitId: @js((string) old('unit_id', request('unit', ''))),
        propertyId: '',
        unitMissing: false,
        moveIn: @js(old('move_in_date')),
        moveOut: @js(old('move_out_date')),
        rent: @js(old('agreed_monthly_rent')),
        amount: @js(old('initial_amount')),
        leaseMode: @js(old('lease_mode', 'later')),
        fileName: '',
        recordPayment: @js((bool) old('initial_amount')),
        method: @js(old('payment_method', 'Cash')),
        today: @js(now()->toDateString()),
        get unit() { return this.units[this.unitId] ?? null; },
        get tenant() { return this.tenants.find(t => String(t.id) === String(this.tenantId)) ?? null; },
        get tenantMatches() {
            const q = this.tenantQuery.trim().toLowerCase();
            return q ? this.tenants.filter(t => t.label.toLowerCase().includes(q)) : this.tenants;
        },
        get recapTenant() { return this.tenantMode === 'existing' ? (this.tenant?.label ?? '') : (this.newFirst + ' ' + this.newLast).trim(); },
        pickTenant(t) { this.tenantId = String(t.id); this.tenantQuery = ''; this.stepError = ''; },
        clearTenant() { this.tenantId = ''; this.$nextTick(() => this.$refs.tenantSearch?.focus()); },
        // Checks the step the landlord is on; the first problem is shown under the form and the step stays put.
        validStep() {
            this.stepError = '';
            if (this.step === 1) {
                if (this.tenantMode === 'existing' && !this.tenant) { this.stepError = 'Search for a tenant and pick one to continue.'; return false; }
                if (this.tenantMode === 'new') {
                    for (const el of this.$refs.stepOne.querySelectorAll('input')) { if (!el.checkValidity()) { el.reportValidity(); return false; } }
                }
            } else if (this.step === 2) {
                if (!this.propertyId) { this.stepError = 'Choose a property.'; return false; }
                if (!this.unitId) { this.stepError = 'Choose a unit.'; this.unitMissing = true; return false; }
                if (!this.moveIn) { this.stepError = 'Choose a move-in date.'; return false; }
                if (this.rent === '' || this.rent == null || Number(this.rent) < 0) { this.stepError = 'Enter the monthly rent.'; return false; }
                for (const el of this.$refs.stepTwo.querySelectorAll('input[type=number], textarea')) { if (!el.checkValidity()) { el.reportValidity(); return false; } }
            }
            return true;
        },
        next() { if (this.validStep() && this.step < 3) { this.step++; this.$nextTick(() => this.$refs.body.scrollTo({ top: 0 })); } },
        back() { this.stepError = ''; if (this.step > 1) { this.step--; this.$nextTick(() => this.$refs.body.scrollTo({ top: 0 })); } },
        get propertyUnits() { return Object.entries(this.units).filter(([, u]) => u.property === this.propertyId); },
        get moveInToday() { return !!this.moveIn && this.moveIn === this.today; },
        get paymentRequired() { return !!this.moveIn && this.moveIn <= this.today; },
        get paymentOn() { return this.paymentRequired || this.recordPayment; },
        get minMoveOut() {
            if (!this.moveIn) return null;
            const d = new Date(this.moveIn + 'T00:00:00');
            d.setMonth(d.getMonth() + 1);
            return d.toISOString().slice(0, 10);
        },
        get stayLength() {
            if (!this.moveIn || !this.moveOut || this.moveOut <= this.moveIn) return '';
            const a = new Date(this.moveIn + 'T00:00:00'), b = new Date(this.moveOut + 'T00:00:00');
            let months = (b.getFullYear() - a.getFullYear()) * 12 + (b.getMonth() - a.getMonth());
            if (b.getDate() < a.getDate()) months--;
            const anchor = new Date(a); anchor.setMonth(anchor.getMonth() + months);
            const days = Math.round((b - anchor) / 86400000);
            const parts = [];
            if (months > 0) parts.push(months + (months === 1 ? ' month' : ' months'));
            if (days > 0) parts.push(days + (days === 1 ? ' day' : ' days'));
            return parts.join(', ');
        },
        get deposit() { return this.unit ? Number(this.unit.deposit) : 0; },
        get moveInDue() { return (Number(this.rent) || 0) + this.deposit; },
        peso(n) { return '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
        pickProperty() {
            if (this.unit && this.unit.property === this.propertyId) return;
            const list = this.propertyUnits;
            this.unitId = list.length === 1 ? list[0][0] : '';
        },
        pickUnit() {
            if (!this.unit) return;
            this.rent = this.unit.rent;
        },
        // $dispatch('open-add-contract', { tenant: <landlord_tenant_id> }) opens it with that tenant picked.
        open(detail) { if (detail && detail.tenant) { this.tenantId = String(detail.tenant); this.tenantMode = 'existing'; if (this.tenant) this.step = 2; } this.show = true; if (this.unitId && !this.rent) this.pickUnit(); },
        useDuplicate() { this.tenantId = this.duplicateId; this.tenantMode = 'existing'; this.duplicateId = ''; },
        close() { this.show = false; },
     }"
     x-init="if (unit) propertyId = unit.property; $watch('unitId', () => pickUnit()); $watch('propertyId', () => pickProperty())"
     x-on:open-add-contract.window="open($event.detail)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="add-contract-title">
            <div x-show="show" x-transition.opacity @click="close()" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            <div x-show="show"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-6 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                style="max-width: 760px; max-height: 94vh;"
                class="relative w-full flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)] overflow-hidden">

                <div class="shrink-0 flex items-start justify-between gap-4 px-5 sm:px-7 pt-6 pb-4 border-b border-[#E2E4EC]">
                    <div>
                        <h2 id="add-contract-title" class="text-[20px] font-semibold text-[#060D26] leading-tight">Add a rental</h2>
                        <p class="text-[13px] text-[#5B6A8E] mt-1">For a tenant you already agreed with. The unit is held for them until they move in.</p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-10 h-10 -mr-2 -mt-1.5 shrink-0 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Progress: you can go back to a finished step, forward only with Next (each step is checked first). --}}
                @if(! empty($unitInfo))
                    <ol class="shrink-0 flex items-center gap-2 px-5 sm:px-7 py-3 border-b border-[#E2E4EC] bg-[#F7F8FC]" aria-label="Steps">
                        @foreach($steps as $n => $name)
                            @if($n > 1)<li aria-hidden="true" class="flex-1 h-px bg-[#E2E4EC] min-w-3"></li>@endif
                            <li class="flex items-center">
                                <button type="button" @click="if ({{ $n }} < step) { stepError = ''; step = {{ $n }}; }" :disabled="{{ $n }} >= step"
                                    :aria-current="step === {{ $n }} ? 'step' : null"
                                    class="flex items-center gap-2 rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 disabled:cursor-default" :class="{{ $n }} < step && 'cursor-pointer'">
                                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-[12.5px] font-bold tabular-nums transition-colors duration-200"
                                        :class="step === {{ $n }} ? 'bg-[#060D26] text-white' : ({{ $n }} < step ? 'bg-[#FF8A66] text-[#060D26]' : 'bg-[#ECEEF6] text-[#5B6A8E]')">
                                        <svg x-show="{{ $n }} < step" x-cloak width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        <span x-show="{{ $n }} >= step">{{ $n }}</span>
                                    </span>
                                    <span class="text-[13px] font-semibold whitespace-nowrap" :class="step === {{ $n }} ? 'text-[#060D26]' : 'text-[#5B6A8E] hidden sm:inline'">{{ $name }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if(empty($unitInfo))
                    <div class="px-5 sm:px-7 pt-5 pb-7">
                        <p class="rounded-xl bg-[#ECEEF6] px-4 py-3 text-[13.5px] text-[#060D26]">
                            None of your approved units is free right now.
                        </p>
                    </div>
                @else
                <form method="POST" action="{{ route('landlord.contracts.store') }}" enctype="multipart/form-data"
                    class="flex-1 min-h-0 flex flex-col"
                    x-on:submit="if (step < 3) { $event.preventDefault(); next(); return; } if (propertyId && !unitId) { $event.preventDefault(); unitMissing = true; step = 2; }"
                    x-on:keydown.enter="if ($event.target.tagName === 'INPUT' && step < 3) { $event.preventDefault(); next(); }">
                    @csrf
                    <input type="hidden" name="_form" value="contract">
                    <input type="hidden" name="tenant_mode" :value="tenantMode">

                    <div x-ref="body" class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden overscroll-contain px-5 sm:px-7 py-5">

                    {{-- Step 1. Tenant: search the landlord's list and click one, or add a new person. Only the picked side is rendered, so the other side posts nothing. --}}
                    <div x-show="step === 1" x-ref="stepOne" class="grid gap-4 max-w-xl mx-auto">
                        @if(! empty($tenantSelect))
                            <div class="flex gap-1 rounded-xl bg-[#ECEEF6] p-1" role="group" aria-label="Tenant">
                                <button type="button" @click="tenantMode = 'existing'; stepError = ''" :aria-pressed="tenantMode === 'existing'"
                                    :class="tenantMode === 'existing' ? 'bg-white text-[#060D26] shadow-[0_1px_3px_rgba(6,13,38,0.12)]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                                    class="flex-1 h-10 rounded-[9px] text-[13.5px] font-semibold transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">Existing tenant</button>
                                <button type="button" @click="tenantMode = 'new'; stepError = ''" :aria-pressed="tenantMode === 'new'"
                                    :class="tenantMode === 'new' ? 'bg-white text-[#060D26] shadow-[0_1px_3px_rgba(6,13,38,0.12)]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                                    class="flex-1 h-10 rounded-[9px] text-[13.5px] font-semibold transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">New tenant</button>
                            </div>
                        @endif

                        <template x-if="tenantMode === 'existing'">
                            <div class="grid gap-2">
                                <input type="hidden" name="landlord_tenant_id" :value="tenantId">

                                {{-- Picked: a card with a Change button. Not picked yet: a search box and the matching tenants to click. --}}
                                <div x-show="tenant" x-cloak class="flex items-center gap-3 rounded-xl border border-[#FF8A66] bg-[#ECEEF6] px-4 py-3">
                                    <span class="w-9 h-9 rounded-full bg-white flex items-center justify-center shrink-0 text-[#B35A3D]" aria-hidden="true">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[12px] text-[#5B6A8E]">Tenant</p>
                                        <p class="text-[14px] font-semibold text-[#060D26] truncate" x-text="tenant?.label"></p>
                                    </div>
                                    <button type="button" @click="clearTenant()" class="h-10 px-3 text-[13px] font-semibold text-[#B35A3D] hover:underline underline-offset-2 cursor-pointer">Change</button>
                                </div>

                                <div x-show="!tenant" class="grid gap-2">
                                    <label for="contract-tenant-search" class="{{ $label }} mb-0">Find a tenant</label>
                                    <div class="relative">
                                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E] pointer-events-none" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z" /></svg>
                                        <input id="contract-tenant-search" type="search" x-ref="tenantSearch" x-model="tenantQuery" autocomplete="off" placeholder="Search by name or code"
                                            @keydown.enter.prevent.stop="if (tenantMatches.length) pickTenant(tenantMatches[0])"
                                            class="{{ $input }} !pl-10">
                                    </div>
                                    <ul class="max-h-64 overflow-y-auto rounded-xl border border-[#E2E4EC] divide-y divide-[#E2E4EC]" role="listbox" aria-label="Tenants">
                                        <template x-for="t in tenantMatches" :key="t.id">
                                            <li role="option" :aria-selected="String(t.id) === tenantId">
                                                <button type="button" @click="pickTenant(t)" class="w-full text-left px-4 py-3 min-h-11 text-[13.5px] font-medium text-[#060D26] hover:bg-[#ECEEF6] focus:outline-none focus-visible:bg-[#ECEEF6] transition-colors duration-150 cursor-pointer" x-text="t.label"></button>
                                            </li>
                                        </template>
                                        <li x-show="!tenantMatches.length" class="px-4 py-3 text-[13px] text-[#5B6A8E]">
                                            No tenant matches "<span x-text="tenantQuery"></span>". <button type="button" @click="tenantMode = 'new'" class="font-semibold text-[#B35A3D] hover:underline underline-offset-2 cursor-pointer">Add them as a new tenant</button>
                                        </li>
                                    </ul>
                                </div>
                                @error('landlord_tenant_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                        </template>

                        <template x-if="tenantMode === 'new'">
                            <div class="grid gap-3">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="contract-first-name" class="{{ $label }}">First name</label>
                                        <input id="contract-first-name" type="text" name="first_name" x-model="newFirst" maxlength="100" required autocomplete="off" class="{{ $input }}">
                                        @error('first_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="contract-last-name" class="{{ $label }}">Last name</label>
                                        <input id="contract-last-name" type="text" name="last_name" x-model="newLast" maxlength="100" required autocomplete="off" class="{{ $input }}">
                                        @error('last_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="contract-phone" class="{{ $label }}">Mobile number</label>
                                    <input id="contract-phone" type="tel" name="contact_number" value="{{ old('contact_number') }}" maxlength="20" inputmode="tel" autocomplete="off" placeholder="0917 123 4567" class="{{ $input }}">
                                    @error('contact_number')
                                        <p class="{{ $err }}">{{ $message }}</p>
                                        <button type="button" x-show="duplicateId" x-cloak @click="useDuplicate()"
                                            class="mt-2 h-11 px-5 rounded-full border border-[#060D26]/35 bg-white text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Use that tenant</button>
                                    @enderror
                                </div>
                                <div>
                                    <label for="contract-email" class="{{ $label }}">Email <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                                    <input id="contract-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="off" placeholder="name@email.com" class="{{ $input }}">
                                    @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                    <p class="{{ $hint }}">With an email, we send them an invite to set a password. Add their ID and address later on the Tenants page.</p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Step 2. Where and when: property and unit on the left, dates and terms on the right. --}}
                    <div x-show="step === 2" x-cloak x-ref="stepTwo" class="md:min-h-[27rem] grid gap-5 md:grid-cols-2 md:gap-x-8 md:items-start">
                        <div class="grid gap-3" x-ref="unitBlock">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Property and unit</p>
                            <div>
                                <label class="{{ $label }}">Property</label>
                                <x-styled-select name="_property" :options="$propertySelect" x-model="propertyId" placeholder="Choose a property" required
                                    class="{{ $input }} text-left" />
                            </div>
                            <div>
                                <label class="{{ $label }}">Unit</label>
                                <p x-show="!propertyId" class="h-11 flex items-center px-3.5 rounded-xl border border-dashed border-[#E2E4EC] text-[13.5px] text-[#94A3B8]">Pick a property first</p>
                                @foreach($unitSelects as $pid => $options)
                                    <template x-if="propertyId === '{{ $pid }}'">
                                        <div>
                                            <x-styled-select name="unit_id" :options="$options" x-model="unitId" placeholder="Choose a unit" required
                                                class="{{ $input }} text-left" />
                                        </div>
                                    </template>
                                @endforeach
                                @error('unit_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                <p x-show="unitMissing && !unitId" x-cloak class="{{ $err }}">Choose a unit.</p>
                            </div>
                        </div>
                        <div class="grid gap-5 md:border-l md:border-[#E2E4EC] md:pl-8">
                            @include('landlord.contracts.partials._contract-fields', ['idPrefix' => 'contract', 'parts' => ['dates', 'terms']])
                        </div>
                    </div>

                    {{-- Step 3. Paperwork and money, with a one-line recap of who and where. --}}
                    <div x-show="step === 3" x-cloak class="grid gap-5">
                        <div class="rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] px-4 py-3 text-[13px] text-[#5B6A8E]">
                            <span class="font-semibold text-[#060D26]" x-text="recapTenant"></span>
                            <span x-show="unit"> · <span x-text="propertyTitles[propertyId]"></span>, <span x-text="unit?.label"></span></span>
                            <span x-show="moveIn"> · moves in <span x-text="moveIn"></span></span>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2 md:gap-x-8 md:items-start">
                            <div class="grid gap-5">
                                @include('landlord.contracts.partials._contract-fields', ['idPrefix' => 'contract', 'parts' => ['agreement']])
                            </div>
                            <div class="grid gap-5 md:border-l md:border-[#E2E4EC] md:pl-8">
                                @include('landlord.contracts.partials._contract-fields', ['idPrefix' => 'contract', 'parts' => ['payment']])
                            </div>
                        </div>
                    </div>
                    </div>

                    {{-- Footer: always visible. Back on the left; the running total, Cancel and Next (or Create rental on the last step) on the right. --}}
                    <div class="shrink-0 border-t border-[#E2E4EC] bg-white px-5 sm:px-7 pt-3.5" style="padding-bottom: max(14px, env(safe-area-inset-bottom));">
                        <p x-show="stepError" x-cloak x-text="stepError" role="alert" class="mb-2.5 text-[12.5px] font-semibold text-[#DC2626]"></p>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <p class="text-[13px] text-[#5B6A8E] sm:min-w-0 sm:truncate" x-show="step > 1 && Number(rent) > 0" x-cloak>
                                <span class="font-semibold text-[#060D26] tabular-nums" x-text="peso(rent)"></span>/mo<span x-show="stayLength"> for <span x-text="stayLength"></span></span>
                            </p>
                            <div class="flex gap-2 w-full sm:w-auto sm:ml-auto">
                                <button type="button" x-show="step === 1" @click="close()" class="flex-1 sm:flex-none h-11 px-5 rounded-full border border-[#060D26]/35 bg-white text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Cancel</button>
                                <button type="button" x-show="step > 1" x-cloak @click="back()" class="flex-1 sm:flex-none h-11 px-5 rounded-full border border-[#060D26]/35 bg-white text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Back</button>
                                <button type="button" x-show="step < 3" @click="next()" class="flex-[2] sm:flex-none h-11 px-7 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 transition-colors duration-200 cursor-pointer">Next</button>
                                <button type="submit" x-show="step === 3" x-cloak class="flex-[2] sm:flex-none h-11 px-7 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 transition-colors duration-200 cursor-pointer">Create rental</button>
                            </div>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </template>
</div>
