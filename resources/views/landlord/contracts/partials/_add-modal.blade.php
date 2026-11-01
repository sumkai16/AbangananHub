{{--
    "Add lease": a tenant from the landlord's list, one free unit, the dates and the rent. Posts to
    landlord.contracts.store (LeaseContractCreator). Opened with $dispatch('open-add-contract'); a failed submit
    reopens it with the old values (old('_form') === 'contract').

    Layout: one column (bottom sheet on phones, 600px dialog above), three groups separated by space and a hairline:
    who and where → term and rent → paperwork and money. Fixed header and footer; only the fields scroll. No extra
    terms field: the lease's wording and house rules come from the unit's policy (Policies page). Field names are
    unchanged.
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
    $methods = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Check', 'Other'];

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
        units: @js((object) $unitInfo),
        tenantId: @js((string) old('landlord_tenant_id', request('tenant', ''))),
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
        get propertyUnits() { return Object.entries(this.units).filter(([, u]) => u.property === this.propertyId); },
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
        open() { this.show = true; },
        close() { this.show = false; },
     }"
     x-init="propertyId = units[unitId]?.property ?? ''; $watch('unitId', () => pickUnit()); $watch('propertyId', () => pickProperty())"
     x-on:open-add-contract.window="open()"
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
                style="max-width: 600px; max-height: 94vh;"
                class="relative w-full flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)] overflow-hidden">

                <div class="shrink-0 flex items-start justify-between gap-4 px-5 sm:px-7 pt-6 pb-4 border-b border-[#E2E4EC]">
                    <div>
                        <h2 id="add-contract-title" class="text-[20px] font-semibold text-[#060D26] leading-tight">Add a lease</h2>
                        <p class="text-[13px] text-[#5B6A8E] mt-1">For a tenant you already agreed with. The unit is held for them until they move in.</p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-10 h-10 -mr-2 -mt-1.5 shrink-0 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @if(empty($tenantSelect) || empty($unitInfo))
                    <div class="px-5 sm:px-7 pt-5 pb-7">
                        <p class="rounded-xl bg-[#ECEEF6] px-4 py-3 text-[13.5px] text-[#060D26]">
                            @if(empty($tenantSelect))
                                Add the tenant on the <a href="{{ route('landlord.tenants.index') }}" class="font-semibold underline underline-offset-2">Tenants page</a> first.
                            @else
                                None of your approved units is free right now.
                            @endif
                        </p>
                    </div>
                @else
                <form method="POST" action="{{ route('landlord.contracts.store') }}" enctype="multipart/form-data"
                    class="flex-1 min-h-0 flex flex-col"
                    x-on:submit="if (propertyId && !unitId) { $event.preventDefault(); unitMissing = true; $refs.unitBlock.scrollIntoView({ behavior: 'smooth', block: 'center' }); }">
                    @csrf
                    <input type="hidden" name="_form" value="contract">

                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 sm:px-7 py-5 grid gap-5">

                        {{-- Who and where --}}
                        <div class="{{ $group }}">
                            <div>
                                <label class="{{ $label }}">Tenant</label>
                                <x-styled-select name="landlord_tenant_id" :options="$tenantSelect" x-model="tenantId" placeholder="Choose a tenant" required
                                    class="{{ $input }} text-left" />
                                @error('landlord_tenant_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>

                            <div x-ref="unitBlock">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="{{ $label }}">Property</label>
                                        <x-styled-select :options="$propertySelect" x-model="propertyId" placeholder="Choose a property" required
                                            class="{{ $input }} text-left" />
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Unit</label>
                                        <input type="hidden" name="unit_id" :value="unitId">
                                        {{-- No `required` here: these are swapped by x-if, and a removed dropdown's submit check would linger. The form checks unitId instead. --}}
                                        <template x-if="!propertyId">
                                            <x-styled-select :options="[]" placeholder="Pick a property first" disabled class="{{ $input }} text-left" />
                                        </template>
                                        @foreach($unitSelects as $optionPropertyId => $options)
                                            <template x-if="propertyId === @js((string) $optionPropertyId)">
                                                <x-styled-select :options="$options" x-model="unitId" placeholder="Choose a unit" class="{{ $input }} text-left" />
                                            </template>
                                        @endforeach
                                    </div>
                                </div>
                                <p x-show="unitMissing && !unitId" x-cloak class="{{ $err }}">Choose a unit.</p>
                                @error('unit_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                {{-- What the listing says, so the rent below can be checked against it --}}
                                <p x-show="unit" x-cloak class="{{ $hint }} tabular-nums">
                                    Listed at <span class="font-semibold text-[#060D26]" x-text="unit ? peso(unit.rent) + '/mo' : ''"></span>
                                    · Deposit <span class="font-semibold text-[#060D26]" x-text="deposit > 0 ? peso(deposit) : 'none'"></span>
                                    <span x-show="unit?.limit">· Fits <span x-text="unit?.limit"></span></span>
                                </p>
                            </div>
                        </div>

                        {{-- Term and rent --}}
                        <div class="{{ $groupSep }}">
                            <div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="{{ $label }}" for="contract_move_in">Move-in</label>
                                        <x-date-picker name="move_in_date" id="contract_move_in" x-model="moveIn" :max="now()->addYear()->toDateString()" />
                                        @error('move_in_date')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="{{ $label }}" for="contract_move_out">Move-out</label>
                                        <x-date-picker name="move_out_date" id="contract_move_out" x-model="moveOut" min-expr="minMoveOut" />
                                        @error('move_out_date')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <p x-show="stayLength" x-cloak class="{{ $hint }}">A <span class="font-semibold text-[#060D26]" x-text="stayLength"></span> lease.</p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="{{ $label }}" for="contract_rent">Monthly rent</label>
                                    <div class="relative">
                                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[14px] text-[#5B6A8E] pointer-events-none">₱</span>
                                        <input id="contract_rent" type="number" name="agreed_monthly_rent" x-model="rent" min="0" step="0.01" required inputmode="decimal" class="{{ $input }} pl-8 tabular-nums">
                                    </div>
                                    @error('agreed_monthly_rent')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}" for="contract_due">Due day</label>
                                    <input id="contract_due" type="number" name="rent_due_day" value="{{ old('rent_due_day') }}" min="1" max="28" placeholder="Move-in day" inputmode="numeric" class="{{ $input }}">
                                    @error('rent_due_day')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}" for="contract_occupants">Occupants</label>
                                    <input id="contract_occupants" type="number" name="occupants_count" value="{{ old('occupants_count', 1) }}" min="1" :max="unit?.limit ?? 20" inputmode="numeric" class="{{ $input }}">
                                    @error('occupants_count')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Paperwork and money --}}
                        <div class="{{ $groupSep }}">
                            <div>
                                <p class="{{ $label }}" id="lease-mode-label">Signed agreement</p>
                                <div class="grid grid-cols-2 p-1 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC]" role="radiogroup" aria-labelledby="lease-mode-label">
                                    @foreach(['later' => 'Upload later', 'now' => 'Upload now'] as $mode => $title)
                                        <label class="h-9 flex items-center justify-center rounded-lg text-[13px] font-semibold cursor-pointer transition-colors duration-200 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#FF8A66]/50"
                                            :class="leaseMode === '{{ $mode }}' ? 'bg-white text-[#060D26] shadow-[0_1px_2px_rgba(6,13,38,0.08)] ring-1 ring-[#E2E4EC]' : 'text-[#5B6A8E] hover:text-[#060D26]'">
                                            <input type="radio" name="lease_mode" value="{{ $mode }}" x-model="leaseMode" class="sr-only">{{ $title }}
                                        </label>
                                    @endforeach
                                </div>
                                <p x-show="leaseMode === 'later'" class="{{ $hint }}">Print it from the lease once it's created, sign it together, then upload it there.</p>
                                <label x-show="leaseMode === 'now'" x-cloak
                                    class="mt-2 h-11 flex items-center gap-2.5 rounded-xl border border-dashed border-[#5B6A8E]/40 px-3.5 cursor-pointer hover:bg-[#F7F8FC] transition-colors duration-200">
                                    <svg class="w-4 h-4 shrink-0 text-[#5B6A8E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                                    <span class="text-[13px] truncate" :class="fileName ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]'" x-text="fileName || 'Choose file (PDF, JPG, PNG)'"></span>
                                    <input type="file" name="lease_file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" @change="fileName = $event.target.files[0]?.name ?? ''">
                                </label>
                                @error('lease_file')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>

                            {{-- Move-in money the tenant already handed over outside the app. Required once the move-in date has arrived. --}}
                            <div class="rounded-xl border border-[#E2E4EC]" :class="paymentOn ? 'bg-[#F7F8FC]' : 'bg-white'" style="padding: 12px 14px;">
                                <label class="flex items-center justify-between gap-4" :class="!paymentRequired && 'cursor-pointer'" @click="paymentRequired && $event.preventDefault()">
                                    <span class="min-w-0">
                                        <span class="block text-[13.5px] font-semibold text-[#060D26]">Tenant already paid me</span>
                                        <span x-show="!paymentRequired" class="block text-[12px] text-[#5B6A8E]">Record move-in money they gave you outside the app.</span>
                                        <span x-show="paymentRequired" x-cloak class="block text-[12px] font-semibold text-[#B45309]">Required: the move-in date has passed.</span>
                                    </span>
                                    <span class="relative shrink-0" x-show="!paymentRequired">
                                        <input type="checkbox" x-model="recordPayment" class="peer sr-only" aria-label="Tenant already paid me">
                                        <span class="block w-10 h-6 rounded-full bg-[#5B6A8E]/30 peer-checked:bg-[#FF8A66] peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66]/50 transition-colors duration-200"></span>
                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow-[0_1px_2px_rgba(6,13,38,0.2)] transition-transform duration-200 peer-checked:translate-x-4"></span>
                                    </span>
                                </label>

                                <template x-if="paymentOn">
                                    <div class="mt-3.5 pt-3.5 border-t border-[#E2E4EC] grid gap-3">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="col-span-2 sm:col-span-1">
                                                <label class="{{ $label }}" for="contract_amount">Amount</label>
                                                <div class="relative">
                                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[14px] text-[#5B6A8E] pointer-events-none">₱</span>
                                                    <input id="contract_amount" type="number" name="initial_amount" x-model="amount" min="1" step="0.01" required inputmode="decimal" class="{{ $input }} pl-8 tabular-nums">
                                                </div>
                                            </div>
                                            <div class="col-span-2 sm:col-span-1">
                                                <label class="{{ $label }}">Method</label>
                                                <x-styled-select name="payment_method" :options="array_combine($methods, $methods)" x-model="method" class="{{ $input }} text-left" />
                                            </div>
                                            <div x-show="method !== 'Cash'" class="col-span-2">
                                                <label class="{{ $label }}" for="contract_ref">Reference no.</label>
                                                <input id="contract_ref" type="text" name="reference_no" value="{{ old('reference_no') }}" maxlength="255" class="{{ $input }}">
                                            </div>
                                        </div>
                                        <p class="text-[12px] text-[#5B6A8E]">
                                            Recorded as paid, nothing is charged. Goes to the deposit first, then rent.
                                            <button type="button" x-show="moveInDue > 0 && Number(amount) !== moveInDue" @click="amount = moveInDue"
                                                class="font-semibold text-[#060D26] underline underline-offset-2 hover:text-[#B35A3D] cursor-pointer">Use <span x-text="peso(moveInDue)"></span> (deposit + 1 month)</button>
                                        </p>
                                    </div>
                                </template>
                            </div>
                            @foreach(['initial_amount', 'payment_method', 'reference_no'] as $field)
                                @error($field)<p class="{{ $err }} -mt-2">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    </div>

                    {{-- Footer: always visible, so the submit never scrolls out of reach. The running total sits beside it. --}}
                    <div class="shrink-0 border-t border-[#E2E4EC] bg-white px-5 sm:px-7 pt-3.5" style="padding-bottom: max(14px, env(safe-area-inset-bottom));">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <p class="text-[13px] text-[#5B6A8E] sm:min-w-0 sm:truncate" x-show="Number(rent) > 0" x-cloak>
                                <span class="font-semibold text-[#060D26] tabular-nums" x-text="peso(rent)"></span>/mo<span x-show="stayLength"> for <span x-text="stayLength"></span></span>
                            </p>
                            <div class="flex gap-2 w-full sm:w-auto sm:ml-auto">
                                <button type="button" @click="close()" class="flex-1 sm:flex-none h-11 px-5 rounded-full text-[14px] font-semibold text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Cancel</button>
                                <button type="submit" class="flex-[2] sm:flex-none h-11 px-7 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 transition-colors duration-200 cursor-pointer">Create lease</button>
                            </div>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </template>
</div>
