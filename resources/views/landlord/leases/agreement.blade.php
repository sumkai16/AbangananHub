@extends('layouts.landlord')

{{--
    Generate a rental agreement: tenant (typed or picked), property, unit, dates → the printable agreement, worded by
    the unit's policy. A GET form, so the result can be reloaded or bookmarked; nothing is saved
    (Landlord\LeaseAgreementDraftController). Only the document prints (everything else is print:hidden).

    Layout: the "Generate agreement" tab of Rental policies (tabs: partials/_tabs). A short form: tenant, property, unit and
    move-in date; move-out, rent, due day and extra terms sit under "More options". After Generate the page scrolls to the document.
--}}
@section('content')
@php
    $docIcon = 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z';
    $unitLabels = $properties->flatMap(fn ($p) => $p->units)->mapWithKeys(fn ($u) => [(string) $u->unit_id => $u->unit_label])->all();
    $propertySelect = $properties->mapWithKeys(fn ($p) => [(string) $p->property_id => $p->title])->all();
    $unitSelects = $properties->mapWithKeys(fn ($p) => [(string) $p->property_id => $p->units->mapWithKeys(fn ($u) => [(string) $u->unit_id => $u->unit_label])->all()])->all();
    $unitRents = $properties->flatMap(fn ($p) => $p->units)->mapWithKeys(fn ($u) => [(string) $u->unit_id => (float) $u->rental_fee])->all();
    $input = 'h-11 w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[16px] sm:text-[14px] text-[#060D26] placeholder-[#94A3B8] hover:border-[#5B6A8E]/40 focus:outline-none focus:border-[#FF8A66]/60 focus:ring-4 focus:ring-[#FF8A66]/10 transition-all duration-200';
    $label = 'block text-[12.5px] font-semibold text-[#060D26] mb-1.5';
    $err = 'mt-1.5 text-[12px] font-semibold text-[#DC2626]';
    $h2 = 'text-[15px] font-semibold text-[#060D26]';
    $sub = 'mt-0.5 text-[13px] text-[#5B6A8E]';
    $printIcon = 'M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z';
@endphp


<div class="agr max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-16">
    <div class="agr-screen print:hidden">
        @include('landlord.leases.partials._header', ['docIcon' => $docIcon])
    </div>
    <div data-swap-region="tab">
<style>
    .agr-screen ::selection { background: rgba(255, 138, 102, .35); color: #060D26; }
    .agr-screen input, .agr-screen textarea { caret-color: #FF8A66; accent-color: #FF8A66; }
    @media print { .agr-screen { display: none !important; } }
</style>
    <div class="agr-screen print:hidden">
        @include('landlord.leases.partials._tabs', ['active' => 'agreement'])

        @if(empty($propertySelect))
            <x-card><p class="text-[13px] text-[#5B6A8E]">Add a property with a unit first, then you can generate an agreement for it.</p></x-card>
        @else
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 items-start"
            x-data="{
                tenants: @js($tenants),
                name: @js((string) request('tenant_name', '')),
                tenantId: @js((string) request('landlord_tenant_id', '')),
                suggest: false,
                active: 0,
                propertyId: @js((string) request('property_id', '')),
                unitId: @js((string) request('unit_id', '')),
                rent: @js((string) request('rent', '')),
                moveIn: @js(request('move_in_date')),
                moveOut: @js(request('move_out_date')),
                rents: @js((object) $unitRents),
                policies: @js((object) $policyNames),
                get matches() {
                    const q = this.name.trim().toLowerCase();
                    return this.tenants.filter(t => !q || t.name.toLowerCase().includes(q) || t.code.toLowerCase().includes(q)).slice(0, 6);
                },
                typed() { this.tenantId = ''; this.active = 0; this.suggest = true; },
                pick(t) { this.name = t.name; this.tenantId = String(t.id); this.suggest = false; },
                move(step) { if (!this.suggest) { this.suggest = this.matches.length > 0; return; } this.active = (this.active + step + this.matches.length) % this.matches.length; },
                get minMoveOut() { if (!this.moveIn) return null; const d = new Date(this.moveIn + 'T00:00:00'); d.setMonth(d.getMonth() + 1); return d.toISOString().slice(0, 10); },
                get policy() { return this.unitId ? (this.policies[this.unitId] ?? '') : ''; },
                titles: @js((object) $propertySelect),
                unitLabels: @js((object) $unitLabels),
                day(iso) { return iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : ''; },
                peso(n) { return '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
            }"
            x-init="$watch('propertyId', (v, old) => { if (old) unitId = ''; }); $watch('unitId', v => { if (v && rents[v] !== undefined) rent = rents[v]; })">
        <form method="GET" action="{{ route('landlord.leases.agreement') }}" class="min-w-0 grid gap-5 bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5 sm:p-6">

            <div class="relative" @click.outside="suggest = false">
                <label for="agr-tenant" class="{{ $label }}">Tenant</label>
                <input id="agr-tenant" x-ref="tenant" type="text" name="tenant_name" x-model="name" @input="typed()" @focus="suggest = tenants.length > 0"
                    @keydown.escape="suggest = false" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter="if (suggest && matches.length && !tenantId) { $event.preventDefault(); pick(matches[active]); }"
                    role="combobox" aria-autocomplete="list" :aria-expanded="suggest && matches.length > 0" aria-controls="agr-tenant-list"
                    required maxlength="150" autocomplete="off" placeholder="Type a name, or pick one of your tenants" class="{{ $input }}">
                <input type="hidden" name="landlord_tenant_id" :value="tenantId">
                <ul id="agr-tenant-list" x-show="suggest && matches.length" x-cloak role="listbox"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute z-20 mt-1.5 w-full max-h-64 overflow-y-auto rounded-xl border border-[#E2E4EC] bg-white shadow-[0_12px_32px_rgba(6,13,38,0.14)] py-1.5">
                    <template x-for="(t, i) in matches" :key="t.id">
                        <li role="option" :aria-selected="i === active">
                            <button type="button" @click="pick(t)" @mouseenter="active = i" :class="i === active && 'bg-[#ECEEF6]'"
                                class="w-full flex items-baseline gap-2.5 text-left px-4 py-2.5 min-h-11 text-[13.5px] font-medium text-[#060D26] cursor-pointer">
                                <span class="text-[12px] font-semibold text-[#5B6A8E] tabular-nums" x-text="t.code"></span>
                                <span class="truncate" x-text="t.name"></span>
                            </button>
                        </li>
                    </template>
                </ul>
                @error('tenant_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="{{ $label }}">Property</label>
                    <x-styled-select name="property_id" :options="$propertySelect" x-model="propertyId" placeholder="Choose a property" required class="{{ $input }} text-left" />
                    @error('property_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">Unit</label>
                    <p x-show="!propertyId" class="h-11 flex items-center px-3.5 rounded-xl border border-dashed border-[#E2E4EC] bg-[#F7F8FC] text-[13.5px] text-[#94A3B8]">Pick a property first</p>
                    @foreach($unitSelects as $pid => $options)
                        <template x-if="propertyId === '{{ $pid }}'">
                            <div><x-styled-select name="unit_id" :options="$options" x-model="unitId" placeholder="Choose a unit" required class="{{ $input }} text-left" /></div>
                        </template>
                    @endforeach
                    @error('unit_id')<p class="{{ $err }}">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="sm:max-w-[calc(50%-0.625rem)]">
                <label class="{{ $label }}" for="agr_move_in">Move-in date</label>
                <x-date-picker name="move_in_date" id="agr_move_in" x-model="moveIn" required />
                @error('move_in_date')<p class="{{ $err }}">{{ $message }}</p>@enderror
            </div>

            {{-- Rarely changed: rent comes from the unit, the due day from the move-in date. Open by default after an error in them. --}}
            <details class="group rounded-xl border border-[#E2E4EC] bg-white" @if($errors->hasAny(['move_out_date', 'rent', 'rent_due_day', 'extra_terms'])) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 text-[13.5px] font-semibold text-[#060D26] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 rounded-xl [&::-webkit-details-marker]:hidden">
                    <span>More options <span class="font-normal text-[#5B6A8E]">· move-out, rent, due day, extra terms</span></span>
                    <svg class="shrink-0 text-[#5B6A8E] transition-transform duration-200 group-open:rotate-180" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                </summary>
                <div class="grid gap-5 border-t border-[#E2E4EC] p-4">
                    <div class="grid gap-5 grid-cols-1 sm:grid-cols-3">
                        <div>
                            <label class="{{ $label }}" for="agr_move_out">Move-out</label>
                            <x-date-picker name="move_out_date" id="agr_move_out" x-model="moveOut" min-expr="minMoveOut" />
                            @error('move_out_date')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="agr_rent">Rent per month</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[14px] text-[#5B6A8E]" aria-hidden="true">₱</span>
                                <input id="agr_rent" type="number" name="rent" x-model="rent" min="0" step="0.01" inputmode="decimal" placeholder="From the unit" class="{{ $input }} !pl-8 tabular-nums">
                            </div>
                            @error('rent')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="agr_due">Rent due day</label>
                            <input id="agr_due" type="number" name="rent_due_day" value="{{ request('rent_due_day') }}" min="1" max="28" inputmode="numeric" placeholder="Move-in day" class="{{ $input }} tabular-nums">
                            @error('rent_due_day')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="agr_terms" class="{{ $label }}">Additional terms</label>
                        <textarea id="agr_terms" name="extra_terms" rows="3" maxlength="2000" placeholder="e.g. Parking slot B2 is included."
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 text-[16px] sm:text-[14px] text-[#060D26] placeholder-[#94A3B8] hover:border-[#5B6A8E]/40 focus:outline-none focus:border-[#FF8A66]/60 focus:ring-4 focus:ring-[#FF8A66]/10 transition-all duration-200">{{ request('extra_terms') }}</textarea>
                        @error('extra_terms')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                </div>
            </details>

            <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 sm:justify-between">
                <p class="text-[12.5px] text-[#5B6A8E] sm:flex-1">
                    <span x-show="policy" x-cloak>Worded by <span class="font-semibold text-[#060D26]" x-text="policy"></span>.</span>
                </p>
                <button type="submit" class="h-12 w-full sm:w-auto sm:px-7 rounded-xl bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 transition-colors duration-200 cursor-pointer">Generate agreement</button>
            </div>
        </form>

        {{-- Right column: the deal as typed, then what happens next (same pattern as My Complaints). --}}
        <aside class="hidden lg:flex flex-col gap-4 lg:sticky lg:top-24" aria-label="Summary">
            <div class="bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5">
                <h3 class="text-[13px] font-normal text-[#060D26] mb-3.5">Your agreement</h3>
                <dl class="grid gap-3.5 text-[13px]">
                    <div>
                        <dt class="text-[11.5px] text-[#5B6A8E]">Tenant</dt>
                        <dd class="mt-0.5 font-semibold text-[#060D26] break-words" :class="!name.trim() && '!font-normal text-[#94A3B8]'" x-text="name.trim() || 'Not set yet'"></dd>
                    </div>
                    <div>
                        <dt class="text-[11.5px] text-[#5B6A8E]">Unit</dt>
                        <dd class="mt-0.5 font-semibold text-[#060D26]" :class="!unitId && '!font-normal text-[#94A3B8]'" x-text="unitId ? (titles[propertyId] + ' · ' + unitLabels[unitId]) : 'Not set yet'"></dd>
                    </div>
                    <div>
                        <dt class="text-[11.5px] text-[#5B6A8E]">Move-in</dt>
                        <dd class="mt-0.5 font-semibold text-[#060D26]" :class="!moveIn && '!font-normal text-[#94A3B8]'" x-text="moveIn ? day(moveIn) + (moveOut ? ' to ' + day(moveOut) : '') : 'Not set yet'"></dd>
                    </div>
                    <div>
                        <dt class="text-[11.5px] text-[#5B6A8E]">Rent</dt>
                        <dd class="mt-0.5 font-semibold text-[#060D26] tabular-nums" :class="!(Number(rent) > 0) && '!font-normal text-[#94A3B8]'" x-text="Number(rent) > 0 ? peso(rent) + ' a month' : 'From the unit'"></dd>
                    </div>
                    <div class="border-t border-[#5B6A8E]/10 pt-3.5">
                        <dt class="text-[11.5px] text-[#5B6A8E]">Worded by</dt>
                        <dd class="mt-0.5 font-semibold text-[#060D26]" :class="!policy && '!font-normal text-[#94A3B8]'" x-text="policy || 'Set by the unit you pick'"></dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-2xl bg-[#060D26] p-5">
                <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center mb-3">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                </div>
                <p class="text-[12.5px] font-semibold text-white leading-snug">Print it and sign on paper</p>
                <p class="text-[11.5px] text-white/60 leading-relaxed mt-1.5">
                    Nothing is saved here. To track the rental in AbangananHub, add it from the Rentals page.
                </p>
            </div>
        </aside>
        </div>
        @endif
    </div>

    @if($terms)
        <div id="agreement" class="mt-10 print:mt-0 scroll-mt-6" x-data x-init="$nextTick(() => $el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }))">
            <div class="agr-screen print:hidden mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-[17px] font-semibold text-[#060D26]">Your agreement is ready</h2>
                    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">Read it through, then print it. Both of you sign on the lines at the end.</p>
                </div>
                <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full bg-[#060D26] text-[13.5px] font-semibold text-white hover:bg-[#0B1536] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#060D26]/25 cursor-pointer transition-colors duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $printIcon }}" /></svg>
                    Print / Save PDF
                </button>
            </div>
            <x-print-document>
                <x-card flush class="p-5 sm:p-10 print:border-none print:shadow-none print:p-0 shadow-[0_1px_2px_rgba(6,13,38,0.04),0_8px_24px_rgba(6,13,38,0.06)]">
                    @include('leases._document', ['terms' => $terms])
                    <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 gap-8 print:grid-cols-2 break-inside-avoid">
                        @foreach (['Landlord' => $terms['landlord']['name'], 'Tenant' => $terms['tenant']['name']] as $role => $name)
                            <div>
                                <div class="h-12 border-b border-[#060D26]"></div>
                                <p class="mt-1.5 text-[13px] font-bold text-[#060D26]">{{ $name ?: $role }}</p>
                                <p class="text-[11.5px] text-[#5B6A8E]">{{ $role }}: signature over printed name</p>
                                <div class="mt-5 h-8 border-b border-[#060D26] w-40"></div>
                                <p class="mt-1.5 text-[11.5px] text-[#5B6A8E]">Date</p>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </x-print-document>
        </div>
    @endif
    </div>
</div>

<script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
