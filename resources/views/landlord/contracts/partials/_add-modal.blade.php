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
        // $dispatch('open-add-contract', { tenant: <landlord_tenant_id> }) opens it with that tenant picked.
        open(detail) { if (detail && detail.tenant) this.tenantId = String(detail.tenant); this.show = true; if (this.unitId && !this.rent) this.pickUnit(); },
        close() { this.show = false; },
     }"
     x-init="$watch('unitId', () => pickUnit())"
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
                class="relative w-full sm:max-w-xl max-h-[92vh] overflow-y-auto bg-white border border-[#E2E4EC] rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div>
                        <h2 id="add-contract-title" class="text-[17px] font-normal text-[#060D26]">Add contract</h2>
                        <p class="text-[12.5px] text-[#5B6A8E] mt-0.5">A contract you agreed with a tenant on your list. The unit is reserved until they move in.</p>
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

                    @include('landlord.contracts.partials._contract-fields', ['idPrefix' => 'contract'])

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
