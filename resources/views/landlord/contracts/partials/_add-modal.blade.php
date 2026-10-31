{{--
    "Add contract": a tenant from the landlord's list, one free unit, the dates and the terms. Posts to
    landlord.contracts.store (LeaseContractCreator). Opened with $dispatch('open-add-contract'); a failed submit
    reopens it with the old values (old('_form') === 'contract').
--}}
@php
    $tenantSelect = $tenantOptions->mapWithKeys(fn ($t) => [$t['id'] => $t['label']])->all();
    $unitSelect = [];
    $unitInfo = [];
    foreach ($unitOptions as $property) {
        foreach ($property['units'] as $unit) {
            $unitSelect[$unit['id']] = $property['title'].' · '.$unit['label'];
            $unitInfo[$unit['id']] = $unit + ['terms' => $property['terms']];
        }
    }
    $input = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
    $label = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';
    $err = 'mt-1 text-[11.5px] font-semibold text-[#EF4444]';
    $section = 'text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]';
@endphp

<div x-data="{
        show: false,
        units: @js((object) $unitInfo),
        tenantId: @js((string) old('landlord_tenant_id', request('tenant', ''))),
        unitId: @js((string) old('unit_id', request('unit', ''))),
        moveIn: @js(old('move_in_date')),
        moveOut: @js(old('move_out_date')),
        rent: @js(old('agreed_monthly_rent')),
        terms: @js(old('agreement_terms_notes', '')),
        leaseMode: @js(old('lease_mode', 'later')),
        recordPayment: @js((bool) old('initial_amount')),
        method: @js(old('payment_method', 'Cash')),
        today: @js(now()->toDateString()),
        get unit() { return this.units[this.unitId] ?? null; },
        get paymentRequired() { return !!this.moveIn && this.moveIn <= this.today; },
        get minMoveOut() {
            if (!this.moveIn) return null;
            const d = new Date(this.moveIn + 'T00:00:00');
            d.setMonth(d.getMonth() + 1);
            return d.toISOString().slice(0, 10);
        },
        pickUnit() {
            if (!this.unit) return;
            this.rent = this.unit.rent;
            if (!this.terms && this.unit.terms) this.terms = this.unit.terms;
        },
        open() { this.show = true; },
        close() { this.show = false; },
     }"
     x-init="$watch('unitId', () => pickUnit())"
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
                class="relative w-full sm:max-w-xl max-h-[92vh] overflow-y-auto bg-white border border-[#E2E4EC] rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div>
                        <h2 id="add-contract-title" class="text-[17px] font-normal text-[#060D26]">Add contract</h2>
                        <p class="text-[12.5px] text-[#5B6A8E] mt-0.5">A lease you agreed with a tenant on your list. The unit is reserved until they move in.</p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @if(empty($tenantSelect) || empty($unitSelect))
                    <div class="px-6 pb-6">
                        <p class="rounded-xl bg-[#ECEEF6] px-4 py-3 text-[13px] text-[#060D26]">
                            @if(empty($tenantSelect))
                                Add the tenant on the <a href="{{ route('landlord.tenants.index') }}" class="font-semibold underline underline-offset-2">Tenants page</a> first.
                            @else
                                None of your approved units is free right now.
                            @endif
                        </p>
                    </div>
                @else
                <form method="POST" action="{{ route('landlord.contracts.store') }}" enctype="multipart/form-data" class="px-6 pb-6 grid gap-5">
                    @csrf
                    <input type="hidden" name="_form" value="contract">

                    <div class="grid gap-3">
                        <p class="{{ $section }}">Tenant and unit</p>
                        <div>
                            <label class="{{ $label }}">Tenant</label>
                            <x-styled-select name="landlord_tenant_id" :options="$tenantSelect" x-model="tenantId" placeholder="Choose a tenant" required
                                class="{{ $input }} text-left" />
                            @error('landlord_tenant_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Property and unit</label>
                            <x-styled-select name="unit_id" :options="$unitSelect" x-model="unitId" placeholder="Choose a free unit" required
                                class="{{ $input }} text-left" />
                            @error('unit_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <p class="{{ $section }}">Dates</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                    </div>

                    <div class="grid gap-3">
                        <p class="{{ $section }}">Terms</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $label }}" for="contract_rent">Monthly rent (₱)</label>
                                <input id="contract_rent" type="number" name="agreed_monthly_rent" x-model="rent" min="0" step="0.01" required class="{{ $input }}">
                                @error('agreed_monthly_rent')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="contract_due">Rent due day</label>
                                <input id="contract_due" type="number" name="rent_due_day" value="{{ old('rent_due_day') }}" min="1" max="28" placeholder="Move-in day" class="{{ $input }}">
                                @error('rent_due_day')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="contract_occupants">Occupants</label>
                                <input id="contract_occupants" type="number" name="occupants_count" value="{{ old('occupants_count', 1) }}" min="1" :max="unit?.limit ?? 20" class="{{ $input }}">
                                @error('occupants_count')<p class="{{ $err }}">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-end">
                                <p class="text-[12px] text-[#5B6A8E] pb-2" x-show="unit">Deposit for this unit: <span class="font-semibold text-[#060D26]" x-text="unit && unit.deposit > 0 ? '₱' + Number(unit.deposit).toLocaleString() : 'None'"></span></p>
                            </div>
                        </div>
                        <div>
                            <label class="{{ $label }}" for="contract_terms">Additional terms <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                            <textarea id="contract_terms" name="agreement_terms_notes" x-model="terms" rows="3" maxlength="2000"
                                class="w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 py-2.5 text-[13.5px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30"></textarea>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <p class="{{ $section }}">Signed lease</p>
                        <div class="flex gap-2">
                            <label class="flex-1 flex items-center gap-2 rounded-xl border border-[#E2E4EC] px-3 py-2.5 text-[13px] cursor-pointer" :class="leaseMode === 'later' && 'border-[#FF8A66] bg-[#ECEEF6]'">
                                <input type="radio" name="lease_mode" value="later" x-model="leaseMode" class="text-[#060D26] focus:ring-[#FF8A66]"> Upload later
                            </label>
                            <label class="flex-1 flex items-center gap-2 rounded-xl border border-[#E2E4EC] px-3 py-2.5 text-[13px] cursor-pointer" :class="leaseMode === 'now' && 'border-[#FF8A66] bg-[#ECEEF6]'">
                                <input type="radio" name="lease_mode" value="now" x-model="leaseMode" class="text-[#060D26] focus:ring-[#FF8A66]"> Upload now
                            </label>
                        </div>
                        <input x-show="leaseMode === 'now'" type="file" name="lease_file" accept=".pdf,.jpg,.jpeg,.png" class="text-[13px]">
                        @error('lease_file')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="{{ $section }}">Move-in payment</p>
                            <span x-show="paymentRequired" class="text-[11px] font-bold text-[#B45309]">Required: the move-in date has arrived</span>
                            <label x-show="!paymentRequired" class="flex items-center gap-2 text-[12.5px] text-[#060D26] cursor-pointer">
                                <input type="checkbox" x-model="recordPayment" class="rounded text-[#060D26] focus:ring-[#FF8A66]"> I already received money
                            </label>
                        </div>
                        <template x-if="paymentRequired || recordPayment">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $label }}" for="contract_amount">Amount received (₱)</label>
                                    <input id="contract_amount" type="number" name="initial_amount" value="{{ old('initial_amount') }}" min="1" step="0.01" class="{{ $input }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Method</label>
                                    <x-styled-select name="payment_method" :options="array_combine($methods = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Check', 'Other'], $methods)" x-model="method"
                                        class="{{ $input }} text-left" />
                                </div>
                                <div x-show="method !== 'Cash'" class="col-span-2">
                                    <label class="{{ $label }}" for="contract_ref">Reference no.</label>
                                    <input id="contract_ref" type="text" name="reference_no" value="{{ old('reference_no') }}" maxlength="255" class="{{ $input }}">
                                </div>
                                <p class="col-span-2 text-[12px] text-[#5B6A8E]">Allocated to the deposit first, then rent from the move-in month.</p>
                            </div>
                        </template>
                        @foreach(['initial_amount', 'payment_method', 'reference_no'] as $field)
                            @error($field)<p class="{{ $err }}">{{ $message }}</p>@enderror
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="close()" class="h-11 px-5 rounded-full border border-[#5B6A8E]/30 text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Cancel</button>
                        <button type="submit" class="h-11 px-6 rounded-full bg-[#FF8A66] text-[13.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer">Create contract</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </template>
</div>
