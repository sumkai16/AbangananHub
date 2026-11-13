{{--
    "Add rental": a tenant from the landlord's list, one free unit, the dates and the rent. Posts to
    landlord.contracts.store (LeaseContractCreator). Opened with $dispatch('open-add-contract'); a failed submit
    reopens it with the old values (old('_form') === 'contract').

    Layout: one column (bottom sheet on phones, 600px dialog above), three groups separated by space and a hairline:
    who and where → term and rent → paperwork and money. Fixed header and footer; only the fields scroll. No extra
    terms field: the rental's wording and house rules come from the unit's policy (Policies page). Field names are
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
        open(detail) { if (detail && detail.tenant) this.tenantId = String(detail.tenant); this.show = true; if (this.unitId && !this.rent) this.pickUnit(); },
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
                style="max-width: 600px; max-height: 94vh;"
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

                    {{-- Who and where: any tenant on the list (Pending or Active), then a property, then a free unit of it. --}}
                    <div class="grid gap-3" x-ref="unitBlock">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Tenant and unit</p>
                        <div>
                            <label class="{{ $label }}">Tenant</label>
                            <x-styled-select name="landlord_tenant_id" :options="$tenantSelect" x-model="tenantId" placeholder="Choose a tenant" required
                                class="{{ $input }} text-left" />
                            @error('landlord_tenant_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $label }}">Property</label>
                                <x-styled-select name="_property" :options="$propertySelect" x-model="propertyId" placeholder="Choose a property"
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
                                <p x-show="unitMissing" x-cloak class="{{ $err }}">Choose a unit.</p>
                            </div>
                        </div>
                    </div>

                    @include('landlord.contracts.partials._contract-fields', ['idPrefix' => 'contract'])
                    </div>

                    {{-- Footer: always visible, so the submit never scrolls out of reach. The running total sits beside it. --}}
                    <div class="shrink-0 border-t border-[#E2E4EC] bg-white px-5 sm:px-7 pt-3.5" style="padding-bottom: max(14px, env(safe-area-inset-bottom));">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <p class="text-[13px] text-[#5B6A8E] sm:min-w-0 sm:truncate" x-show="Number(rent) > 0" x-cloak>
                                <span class="font-semibold text-[#060D26] tabular-nums" x-text="peso(rent)"></span>/mo<span x-show="stayLength"> for <span x-text="stayLength"></span></span>
                            </p>
                            <div class="flex gap-2 w-full sm:w-auto sm:ml-auto">
                                <button type="button" @click="close()" class="flex-1 sm:flex-none h-11 px-5 rounded-full border border-[#060D26]/35 bg-white text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">Cancel</button>
                                <button type="submit" class="flex-[2] sm:flex-none h-11 px-7 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 transition-colors duration-200 cursor-pointer">Create rental</button>
                            </div>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </template>
</div>
