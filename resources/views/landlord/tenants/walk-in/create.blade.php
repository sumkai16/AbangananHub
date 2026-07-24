@extends('layouts.landlord')

@section('page-title', 'Add Walk-in Tenant')

@section('content')
    @php
        // Properties with their available units, for the visual picker. Only
        // approved, genuinely free units reach here — the controller already
        // filtered out anything with a live reservation against it. Each unit
        // carries its own photo, falling back to a property photo, so a landlord
        // recognises the unit by sight rather than by label alone.
        $galleryProperties = $properties->map(fn ($property) => [
            'id'      => $property->property_id,
            'title'   => $property->title,
            'address' => $property->address,
            'units'   => $property->units->map(fn ($unit) => [
                'id'    => $unit->unit_id,
                'label' => $unit->unit_label,
                'rent'  => (float) $unit->rental_fee,
                'cap'   => $unit->occupancy_limit,
                'photo' => optional($unit->media->firstWhere('media_type', 'Image'))->media_url
                    ?? optional($property->media->firstWhere('media_type', 'Image'))->media_url,
            ])->values(),
        ])->values();

        $existingOptions = $existingTenants->map(fn ($tenant) => [
            'id'      => $tenant->user_id,
            'name'    => trim($tenant->first_name . ' ' . $tenant->last_name),
            'contact' => $tenant->contact_number ?: ($tenant->email ?: 'No contact on file'),
        ])->values();

        $inputClass = 'h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition';
        $labelClass = 'block text-[12px] font-semibold text-[#1F2937] mb-1.5';
        $errorClass = 'text-[11.5px] text-[#EF4444] mt-1';
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#64748B] mb-2">
            <a href="{{ route('landlord.tenants.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">My Tenants</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">Add Walk-in Tenant</span>
        </div>

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Add Walk-in Tenant</h1>
            <p class="text-sm text-[#64748B] mt-1">
                Record a tenant who arranged the rental with you directly. The unit is marked occupied straight away — there is no
                inquiry, agreement or online payment step.
            </p>
        </div>

        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @if($properties->isEmpty())
            <x-card class="flex flex-col items-center justify-center py-12 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                </div>
                <p class="text-[14px] font-semibold text-[#1F2937]">No available units</p>
                <p class="text-[13px] text-[#64748B] mt-1 max-w-md">
                    A walk-in tenant needs an admin-approved unit that is currently vacant and has no active reservation against it.
                </p>
                <a href="{{ route('landlord.units.index') }}"
                    class="mt-5 inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                    Go to my units
                </a>
            </x-card>
        @else
            <form method="POST" action="{{ route('landlord.tenants.walkIn.store') }}"
                data-confirm="Add walk-in tenant?"
                data-confirm-message="The unit will be marked Occupied straight away and will stop appearing to tenants browsing the site."
                data-confirm-button="Add tenant"
                x-data="{
                    mode: @js(old('existing_tenant_id') ? 'existing' : 'new'),
                    existingTenantId: @js(old('existing_tenant_id', '')),
                    firstName: @js(old('first_name', '')),
                    lastName: @js(old('last_name', '')),
                    unitId: @js(old('unit_id', '')),
                    moveIn: @js(old('move_in_date', now()->toDateString())),
                    rent: @js(old('agreed_monthly_rent', '')),
                    dueDay: @js(old('rent_due_day', '')),
                    hasPayment: @js((bool) old('initial_amount')),
                    initialAmount: @js(old('initial_amount', '')),
                    galleryProperties: @js($galleryProperties),
                    existingTenants: @js($existingOptions),
                    pickerOpen: false,
                    unitSearch: '',

                    openPicker() { this.unitSearch = ''; this.pickerOpen = true; },
                    // Selecting is the whole action — no separate confirm step.
                    pick(id) { this.unitId = String(id); this.pickerOpen = false; },
                    // Flattened once so the summary and rent fallback don't walk
                    // the grouped structure on every keystroke.
                    get allUnits() { return this.galleryProperties.flatMap(p => p.units); },
                    get unit() { return this.allUnits.find(u => String(u.id) === String(this.unitId)) ?? null; },
                    get selectedProperty() {
                        return this.galleryProperties.find(p => p.units.some(u => String(u.id) === String(this.unitId))) ?? null;
                    },
                    // Properties whose title or any unit label matches the search,
                    // with non-matching units dropped so the modal narrows as typed.
                    get filteredProperties() {
                        const q = this.unitSearch.trim().toLowerCase();
                        if (!q) return this.galleryProperties;
                        return this.galleryProperties
                            .map(p => {
                                const propMatch = p.title.toLowerCase().includes(q);
                                const units = propMatch ? p.units : p.units.filter(u => u.label.toLowerCase().includes(q));
                                return { ...p, units };
                            })
                            .filter(p => p.units.length > 0);
                    },
                    get effectiveRent() {
                        const typed = parseFloat(this.rent);
                        if (!isNaN(typed) && typed > 0) return typed;
                        return this.unit ? this.unit.rent : 0;
                    },
                    get effectiveDueDay() {
                        const typed = parseInt(this.dueDay);
                        if (!isNaN(typed) && typed >= 1 && typed <= 28) return typed;
                        if (!this.moveIn) return 1;
                        return Math.min(28, parseInt(this.moveIn.split('-')[2] ?? '1'));
                    },
                    get tenantName() {
                        if (this.mode !== 'existing') return null;
                        const t = this.existingTenants.find(t => String(t.id) === String(this.existingTenantId));
                        return t ? t.name : null;
                    },
                    peso(value) {
                        return '₱' + (value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    get summaryName() {
                        if (this.mode === 'existing') return this.tenantName || 'Not selected';
                        return (this.firstName + ' ' + this.lastName).trim() || 'Not entered';
                    },
                }">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

                    {{-- ── Form column ────────────────────────────── --}}
                    <div class="lg:col-span-2 space-y-5">

                        {{-- Tenant details --}}
                        <x-card>
                            <div class="flex items-center gap-3 mb-5">
                                <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-[15px] font-bold text-[#1F2937]">Tenant details</h2>
                                    <p class="text-[12px] text-[#64748B]">Who is moving in.</p>
                                </div>
                            </div>

                            @if($existingTenants->isNotEmpty())
                                <div class="flex gap-2 mb-5 p-1 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0]">
                                    <button type="button" @click="mode = 'new'; existingTenantId = ''"
                                        :class="mode === 'new' ? 'bg-white text-[#1F2937] shadow-[0_1px_3px_rgba(15,23,42,0.06)]' : 'text-[#64748B] hover:text-[#1F2937]'"
                                        class="flex-1 h-9 rounded-lg text-[13px] font-semibold transition-all duration-200 cursor-pointer">
                                        New tenant
                                    </button>
                                    <button type="button" @click="mode = 'existing'"
                                        :class="mode === 'existing' ? 'bg-white text-[#1F2937] shadow-[0_1px_3px_rgba(15,23,42,0.06)]' : 'text-[#64748B] hover:text-[#1F2937]'"
                                        class="flex-1 h-9 rounded-lg text-[13px] font-semibold transition-all duration-200 cursor-pointer">
                                        Someone I've recorded before
                                    </button>
                                </div>

                                <div x-show="mode === 'existing'" x-cloak class="mb-1">
                                    <label for="existing_tenant_id" class="{{ $labelClass }}">
                                        Existing walk-in tenant <span class="text-[#EF4444]">*</span>
                                    </label>
                                    <select id="existing_tenant_id" name="existing_tenant_id" x-model="existingTenantId"
                                        class="{{ $inputClass }} bg-white cursor-pointer">
                                        <option value="">Select a tenant…</option>
                                        @foreach($existingTenants as $tenant)
                                            <option value="{{ $tenant->user_id }}" @selected(old('existing_tenant_id') == $tenant->user_id)>
                                                {{ trim($tenant->first_name . ' ' . $tenant->last_name) }}
                                                — {{ $tenant->contact_number ?: ($tenant->email ?: 'no contact') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-[11.5px] text-[#64748B] mt-1.5">
                                        Reuses the person you already recorded instead of creating a duplicate.
                                    </p>
                                    @error('existing_tenant_id')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            <div x-show="mode === 'new'" x-cloak>
                                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="first_name" class="{{ $labelClass }}">
                                            First name <span class="text-[#EF4444]">*</span>
                                        </label>
                                        <input type="text" id="first_name" name="first_name" x-model="firstName"
                                            maxlength="100" placeholder="e.g. Juan" class="{{ $inputClass }}">
                                        @error('first_name')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="last_name" class="{{ $labelClass }}">
                                            Last name <span class="text-[#EF4444]">*</span>
                                        </label>
                                        <input type="text" id="last_name" name="last_name" x-model="lastName"
                                            maxlength="100" placeholder="e.g. Dela Cruz" class="{{ $inputClass }}">
                                        @error('last_name')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="contact_number" class="{{ $labelClass }}">Contact number</label>
                                        <input type="text" id="contact_number" name="contact_number"
                                            value="{{ old('contact_number') }}" maxlength="20" placeholder="e.g. 0917 123 4567"
                                            class="{{ $inputClass }}">
                                        @error('contact_number')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="email" class="{{ $labelClass }}">Email <span class="text-[#64748B] font-normal">(optional)</span></label>
                                        <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255"
                                            placeholder="e.g. juan@email.com" class="{{ $inputClass }}">
                                        @error('email')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mt-4 flex items-start gap-2.5 rounded-xl bg-[#EEF8F8]/60 border border-[#2AA7A1]/20 px-3.5 py-3">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2"
                                        class="shrink-0 mt-0.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <p class="text-[12px] text-[#156F8C] leading-relaxed">
                                        At least one of contact number or email is required. This creates a record only — the tenant
                                        gets no login, and everywhere they appear they are labelled <strong>Walk-in</strong> because
                                        their identity has not been verified by AbangananHub.
                                    </p>
                                </div>
                            </div>
                        </x-card>

                        {{-- Unit and terms --}}
                        <x-card>
                            <div class="flex items-center gap-3 mb-5">
                                <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-[15px] font-bold text-[#1F2937]">Unit &amp; terms</h2>
                                    <p class="text-[12px] text-[#64748B]">Only approved, vacant units are listed.</p>
                                </div>
                            </div>

                            {{-- Compact unit picker: a trigger that opens the gallery in
                                 a modal, keeping the form short. Selecting a unit sets
                                 unit_id; the property is derived from it. --}}
                            <div class="mb-5">
                                <label class="{{ $labelClass }}">
                                    Unit <span class="text-[#EF4444]">*</span>
                                </label>

                                {{-- The one value that actually posts. --}}
                                <input type="hidden" name="unit_id" x-model="unitId">

                                {{-- Empty state --}}
                                <button type="button" x-show="!unit" @click="openPicker()"
                                    class="w-full flex items-center gap-3 rounded-xl border border-dashed border-[#64748B]/40 bg-[#F7FCFC] px-4 py-3.5 text-left hover:border-[#2AA7A1]/60 hover:bg-[#EEF8F8]/40 transition-all duration-200 cursor-pointer">
                                    <span class="w-9 h-9 rounded-lg bg-white ring-1 ring-[#64748B]/10 flex items-center justify-center shrink-0">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-[13.5px] font-semibold text-[#1F2937]">Choose a unit</span>
                                        <span class="block text-[12px] text-[#64748B]">Browse your available units by photo</span>
                                    </span>
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="2" class="shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>

                                {{-- Selected state --}}
                                <div x-show="unit" x-cloak
                                    class="flex items-center gap-3 rounded-xl border border-[#2AA7A1]/30 bg-[#EEF8F8]/40 p-2.5">
                                    <div class="w-14 h-14 rounded-lg bg-white overflow-hidden shrink-0 ring-1 ring-[#64748B]/10">
                                        <template x-if="unit && unit.photo">
                                            <img :src="unit ? unit.photo : ''" :alt="unit ? unit.label : ''" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="unit && !unit.photo">
                                            <div class="w-full h-full flex items-center justify-center">
                                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                                </svg>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[13.5px] font-bold text-[#1F2937] truncate" x-text="unit ? unit.label : ''"></p>
                                        <p class="text-[11.5px] text-[#64748B] truncate" x-text="selectedProperty ? selectedProperty.title : ''"></p>
                                        <div class="flex items-center gap-2.5 mt-0.5">
                                            <span class="text-[12.5px] font-bold text-[#2AA7A1]" x-text="unit ? '₱' + unit.rent.toLocaleString('en-PH') : ''"></span>
                                            <span class="text-[11px] text-[#64748B]" x-show="unit && unit.cap" x-text="unit ? unit.cap + ' pax' : ''"></span>
                                        </div>
                                    </div>
                                    <button type="button" @click="openPicker()"
                                        class="h-9 px-4 shrink-0 rounded-full border border-[#2AA7A1] text-[#2AA7A1] text-[12px] font-semibold hover:bg-white transition-colors duration-200 cursor-pointer">
                                        Change
                                    </button>
                                </div>

                                @error('unit_id')
                                    <p class="{{ $errorClass }}">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="move_in_date" class="{{ $labelClass }}">
                                        Move-in date <span class="text-[#EF4444]">*</span>
                                    </label>
                                    <input type="date" id="move_in_date" name="move_in_date" x-model="moveIn"
                                        class="{{ $inputClass }} cursor-pointer">
                                    @error('move_in_date')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="move_out_date" class="{{ $labelClass }}">
                                        Move-out date <span class="text-[#64748B] font-normal">(optional)</span>
                                    </label>
                                    <input type="date" id="move_out_date" name="move_out_date" value="{{ old('move_out_date') }}"
                                        class="{{ $inputClass }} cursor-pointer">
                                    <p class="text-[11.5px] text-[#64748B] mt-1.5">Leave blank for an open-ended stay.</p>
                                    @error('move_out_date')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="agreed_monthly_rent" class="{{ $labelClass }}">Agreed monthly rent (₱)</label>
                                    <input type="number" id="agreed_monthly_rent" name="agreed_monthly_rent" x-model="rent"
                                        min="0" max="1000000" step="0.01" :placeholder="unit ? unit.rent : 'Unit rate'"
                                        class="{{ $inputClass }}">
                                    <p class="text-[11.5px] text-[#64748B] mt-1.5">Blank uses the unit's listed rate.</p>
                                    @error('agreed_monthly_rent')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rent_due_day" class="{{ $labelClass }}">Rent due day</label>
                                    <select id="rent_due_day" name="rent_due_day" x-model="dueDay"
                                        class="{{ $inputClass }} bg-white cursor-pointer">
                                        <option value="">Same day as move-in</option>
                                        @for($d = 1; $d <= 28; $d++)
                                            <option value="{{ $d }}" @selected(old('rent_due_day') == $d)>{{ $d }}</option>
                                        @endfor
                                    </select>
                                    @error('rent_due_day')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="occupants_count" class="{{ $labelClass }}">Occupants</label>
                                    <select id="occupants_count" name="occupants_count"
                                        class="{{ $inputClass }} bg-white cursor-pointer">
                                        <option value="">Not specified</option>
                                        @for($i = 1; $i <= 20; $i++)
                                            <option value="{{ $i }}" @selected(old('occupants_count') == $i)>
                                                {{ $i }} {{ $i === 1 ? 'person' : 'persons' }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('occupants_count')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="notes" class="{{ $labelClass }}">Notes <span class="text-[#64748B] font-normal">(optional)</span></label>
                                <textarea id="notes" name="notes" rows="2" maxlength="1000"
                                    placeholder="Anything worth remembering about this arrangement…"
                                    class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-2.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition resize-y">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="{{ $errorClass }}">{{ $message }}</p>
                                @enderror
                            </div>
                        </x-card>

                        {{-- Initial payment --}}
                        <x-card>
                            <div class="flex items-start justify-between gap-4 mb-1">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-[15px] font-bold text-[#1F2937]">Initial payment</h2>
                                        <p class="text-[12px] text-[#64748B]">Deposit or advance collected at move-in.</p>
                                    </div>
                                </div>
                                <label for="has_payment" class="flex items-center gap-2 cursor-pointer shrink-0 pt-1">
                                    <input type="checkbox" id="has_payment" x-model="hasPayment"
                                        class="w-4 h-4 rounded border-[#64748B]/40 text-[#2AA7A1] focus:ring-[#2AA7A1]/30 cursor-pointer">
                                    <span class="text-[12.5px] font-semibold text-[#1F2937]">Record one now</span>
                                </label>
                            </div>

                            <div x-show="hasPayment" x-cloak class="mt-5">
                                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="initial_amount" class="{{ $labelClass }}">
                                            Amount (₱) <span class="text-[#EF4444]">*</span>
                                        </label>
                                        <input type="number" id="initial_amount" name="initial_amount" x-model="initialAmount"
                                            min="1" max="1000000" step="0.01" placeholder="e.g. 9000" class="{{ $inputClass }}">
                                        @error('initial_amount')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="initial_type" class="{{ $labelClass }}">
                                            What it was for <span class="text-[#EF4444]">*</span>
                                        </label>
                                        <select id="initial_type" name="initial_type" class="{{ $inputClass }} bg-white cursor-pointer">
                                            <option value="Initial" @selected(old('initial_type', 'Initial') === 'Initial')>Initial payment (deposit + advance)</option>
                                            <option value="Deposit" @selected(old('initial_type') === 'Deposit')>Security deposit only</option>
                                        </select>
                                        @error('initial_type')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-3 gap-4">
                                    <div>
                                        <label for="payment_method" class="{{ $labelClass }}">
                                            Method <span class="text-[#EF4444]">*</span>
                                        </label>
                                        <select id="payment_method" name="payment_method" class="{{ $inputClass }} bg-white cursor-pointer">
                                            @foreach(['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'] as $method)
                                                <option value="{{ $method }}" @selected(old('payment_method', 'Cash') === $method)>{{ $method }}</option>
                                            @endforeach
                                        </select>
                                        @error('payment_method')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="payment_date" class="{{ $labelClass }}">Date received</label>
                                        <input type="date" id="payment_date" name="payment_date"
                                            value="{{ old('payment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                                            class="{{ $inputClass }} cursor-pointer">
                                        @error('payment_date')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="reference_no" class="{{ $labelClass }}">Reference no.</label>
                                        <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no') }}"
                                            maxlength="255" placeholder="OR / GCash ref" class="{{ $inputClass }}">
                                        @error('reference_no')
                                            <p class="{{ $errorClass }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <p class="text-[11.5px] text-[#64748B] mt-4 leading-relaxed">
                                    Recorded as money you have already received — it is not held in escrow and nothing is released to
                                    you by AbangananHub. Monthly rent is recorded later from the tenancy page.
                                </p>
                            </div>
                        </x-card>
                    </div>

                    {{-- ── Summary column ─────────────────────────── --}}
                    <div class="lg:sticky lg:top-6">
                        <x-card>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-4">Summary</p>

                            <div class="space-y-3.5 text-[13px]">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">Tenant</span>
                                    <span class="font-semibold text-[#1F2937] text-right" x-text="summaryName"></span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">Unit</span>
                                    <span class="font-semibold text-[#1F2937] text-right">
                                        <span x-text="unit ? unit.label : 'Not selected'"></span>
                                        <span x-show="selectedProperty" x-cloak
                                            class="block text-[11px] font-normal text-[#64748B]"
                                            x-text="selectedProperty ? selectedProperty.title : ''"></span>
                                    </span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">Move-in</span>
                                    <span class="font-semibold text-[#1F2937] text-right" x-text="moveIn || '—'"></span>
                                </div>

                                <div class="h-px bg-[#E2E8F0]"></div>

                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">Monthly rent</span>
                                    <span class="font-bold text-[#2AA7A1] text-right" x-text="peso(effectiveRent)"></span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">Rent due</span>
                                    <span class="font-semibold text-[#1F2937] text-right"
                                        x-text="'Day ' + effectiveDueDay + ' of each month'"></span>
                                </div>
                                <template x-if="hasPayment && parseFloat(initialAmount) > 0">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-[#64748B]">Collected now</span>
                                        <span class="font-semibold text-[#1F2937] text-right"
                                            x-text="peso(parseFloat(initialAmount))"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-5 flex items-start gap-2.5 rounded-xl bg-[#FBBF24]/[0.08] border border-[#FBBF24]/25 px-3.5 py-3">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2"
                                    class="shrink-0 mt-0.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                                <p class="text-[12px] text-[#B45309] leading-relaxed">
                                    Saving marks the unit <strong>Occupied</strong> immediately and it will stop appearing to tenants
                                    browsing the site.
                                </p>
                            </div>

                            <button type="submit"
                                class="mt-5 w-full h-11 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                                Add tenant &amp; occupy unit
                            </button>
                            <a href="{{ route('landlord.tenants.index') }}"
                                class="mt-2.5 w-full h-11 flex items-center justify-center rounded-full border border-[#E2E8F0] text-[#64748B] text-sm font-semibold hover:bg-[#F7FCFC] hover:text-[#1F2937] transition-all duration-200 cursor-pointer">
                                Cancel
                            </a>
                        </x-card>
                    </div>
                </div>

                {{-- Unit picker modal — teleported to body so no card's overflow or
                     transform can clip it (RULES.md → Modals & Overlays). It keeps
                     the form's Alpine scope, so picking a card sets unitId on the
                     hidden input still living inside the form. --}}
                <template x-teleport="body">
                    <div x-show="pickerOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog" aria-modal="true" aria-labelledby="unit-picker-title"
                        x-on:keydown.escape.window="pickerOpen = false">

                        {{-- Backdrop --}}
                        <div x-show="pickerOpen" x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0" @click="pickerOpen = false"
                            class="absolute inset-0 bg-[#0F172A]/40 backdrop-blur-sm"></div>

                        {{-- Panel --}}
                        <div x-show="pickerOpen"
                            x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                            class="relative w-full max-w-3xl max-h-[85vh] flex flex-col bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_20px_60px_rgba(15,23,42,0.18)]">

                            {{-- Header + search --}}
                            <div class="px-6 pt-6 pb-4 border-b border-[#E2E8F0]">
                                <div class="flex items-start justify-between gap-4 mb-4">
                                    <div>
                                        <h2 id="unit-picker-title" class="text-[17px] font-bold text-[#1F2937]">Choose a unit</h2>
                                        <p class="text-[12.5px] text-[#64748B] mt-0.5">Only approved, currently vacant units are shown.</p>
                                    </div>
                                    <button type="button" @click="pickerOpen = false" aria-label="Close"
                                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#F7FCFC] hover:text-[#1F2937] transition-colors duration-200 cursor-pointer">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="relative">
                                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                                    </svg>
                                    <label for="unit-search" class="sr-only">Search units</label>
                                    <input type="text" id="unit-search" x-model="unitSearch"
                                        placeholder="Search by unit or property…"
                                        class="w-full h-11 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] focus:bg-white transition-all duration-200">
                                </div>
                            </div>

                            {{-- Gallery --}}
                            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                                <template x-for="property in filteredProperties" :key="property.id">
                                    <div>
                                        <div class="flex items-baseline gap-2 mb-2.5">
                                            <p class="text-[12.5px] font-bold text-[#1F2937]" x-text="property.title"></p>
                                            <span class="text-[11px] text-[#94A3B8]"
                                                x-text="property.units.length + (property.units.length === 1 ? ' unit' : ' units')"></span>
                                        </div>

                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                            <template x-for="u in property.units" :key="u.id">
                                                <button type="button" @click="pick(u.id)"
                                                    :class="String(unitId) === String(u.id)
                                                        ? 'border-[#2AA7A1] ring-2 ring-[#2AA7A1]/30'
                                                        : 'border-[#E2E8F0] hover:border-[#2AA7A1]/50 hover:shadow-[0_4px_16px_rgba(15,23,42,0.08)]'"
                                                    class="group relative text-left rounded-xl border overflow-hidden transition-all duration-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/40">

                                                    <span x-show="String(unitId) === String(u.id)" x-cloak
                                                        class="absolute top-2 right-2 z-10 w-5 h-5 rounded-full bg-[#2AA7A1] flex items-center justify-center shadow">
                                                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </span>

                                                    <div class="aspect-[4/3] bg-[#F7FCFC] overflow-hidden">
                                                        <template x-if="u.photo">
                                                            <img :src="u.photo" :alt="u.label" class="w-full h-full object-cover">
                                                        </template>
                                                        <template x-if="!u.photo">
                                                            <div class="w-full h-full flex items-center justify-center">
                                                                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="1.5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                                                </svg>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <div class="p-2.5">
                                                        <p class="text-[12.5px] font-bold text-[#1F2937] truncate" x-text="u.label"></p>
                                                        <div class="flex items-center justify-between gap-2 mt-1">
                                                            <span class="text-[12.5px] font-bold text-[#2AA7A1]"
                                                                x-text="'₱' + u.rent.toLocaleString('en-PH')"></span>
                                                            <span class="inline-flex items-center gap-1 text-[11px] text-[#64748B]" x-show="u.cap">
                                                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                                                </svg>
                                                                <span x-text="u.cap"></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- No search matches --}}
                                <div x-show="filteredProperties.length === 0" x-cloak class="py-10 text-center">
                                    <p class="text-[13.5px] font-semibold text-[#1F2937]">No units match "<span x-text="unitSearch"></span>"</p>
                                    <p class="text-[12.5px] text-[#64748B] mt-1">Try a different unit label or property name.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </form>
        @endif
    </div>
@endsection
