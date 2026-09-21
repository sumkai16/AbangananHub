@extends('layouts.landlord')

@section('content')
<div class="py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors duration-200 w-fit mb-6">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Properties
        </a>

        @if($errors->any())
            <div class="max-w-3xl mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5" role="alert">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-11">

            <x-property-wizard-stepper current="info" :property="$property" :checklist="$checklist ?? null" />

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#B35A3D]">Step 1 of 6</p>
                <h1 class="mt-1.5 text-2xl font-semibold tracking-tight text-[#060D26]">Tell us about the property</h1>
                <p class="mt-2 text-sm text-[#5B6A8E] leading-relaxed max-w-md">Its name, type, and a description tenants will read first.</p>

                @php
                    $typeTiles = [
                        'Apartment' => ['A whole apartment unit', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                        'Condominium' => ['A condo unit in a tower', 'M8 21V4a1 1 0 011-1h6a1 1 0 011 1v17M4 21V10a1 1 0 011-1h3M20 21V12a1 1 0 00-1-1h-3M3 21h18M11 7h2m-2 4h2m-2 4h2'],
                        'House'     => ['A whole house', 'M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6'],
                        'Boarding House' => ['A building with rooms to rent', 'M4 21h16M7 21V4a1 1 0 011-1h8a1 1 0 011 1v17M14 12h.01'],
                        'Bedspace'  => ['A bed in a shared room', 'M3 7h18M3 7v10m0-10V5m18 2v10m0-10V5M3 17h18M6 12h12M5 5h14'],
                    ];
                    $utilities = [
                        ['water_included', 'Water', 'Included in rent'],
                        ['electricity_included', 'Electricity', 'Included in rent'],
                        ['internet_included', 'Internet', 'Included in rent'],
                        ['association_fees_included', 'Association fees', 'Included in rent'],
                        ['utilities_separately_metered', 'Separately metered', 'Tenant pays own usage'],
                    ];
                    $selectedType = old('property_type', $formValues['property_type'] ?? '');
                    $inputBase = 'w-full rounded-xl border text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200';
                @endphp

                <form method="POST" action="{{ $property ? route('properties.wizard.info.update', $property) : route('properties.wizard.info.store') }}"
                    class="mt-5 max-w-2xl space-y-4"
                    x-data="{
                        title: @js(old('title', $formValues['title'] ?? '')),
                        description: @js(old('description', $formValues['description'] ?? '')),
                        units: @js((int) old('number_of_units', $formValues['number_of_units'] ?? 1) ?: 1),
                        step(n) { this.units = Math.min(100, Math.max(1, (parseInt(this.units) || 1) + n)); },
                    }">
                    @csrf
                    @if($property) @method('PUT') @endif

                    {{-- Basics --}}
                    <x-card class="space-y-5 !p-4 sm:!p-5">
                        <div>
                            <div class="flex items-baseline justify-between mb-1.5">
                                <label for="title" class="text-[13px] font-semibold text-[#060D26]">Property name</label>
                                <span class="text-[11.5px] tabular-nums" :class="title.length >= 10 ? 'text-[#15803D]' : 'text-[#5B6A8E]'"
                                    x-text="title.length < 10 ? (10 - title.length) + ' more to go' : title.length + '/150'"></span>
                            </div>
                            <input type="text" id="title" name="title" x-model="title" minlength="10" maxlength="150"
                                class="{{ $inputBase }} h-11 px-4 @error('title') border-[#EF4444]/40 @else border-[#E2E4EC] @enderror"
                                placeholder="e.g., Patenio Apartment" required>
                            @error('title')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        </div>

                        <fieldset>
                            <legend class="text-[13px] font-semibold text-[#060D26] mb-2">Property type</legend>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach($typeTiles as $type => [$hint, $icon])
                                    <label class="cursor-pointer" title="{{ $hint }}">
                                        <input type="radio" name="property_type" value="{{ $type }}" class="peer sr-only" required @checked($selectedType === $type)>
                                        <span class="flex h-11 items-center gap-2.5 rounded-xl border border-[#E2E4EC] bg-white px-3.5 transition-colors duration-200 hover:border-[#060D26]/40
                                            peer-checked:border-[#060D26] peer-checked:bg-[#060D26] peer-checked:text-white peer-checked:[&_svg]:text-[#FF8A66]
                                            peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66] peer-focus-visible:ring-offset-2 text-[#060D26]">
                                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" class="shrink-0 text-[#5B6A8E]" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                            </svg>
                                            <span class="text-[14px] font-semibold">{{ $type }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('property_type')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        </fieldset>

                        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">

                        <div>
                            <label for="number_of_units" class="block text-[13px] font-semibold text-[#060D26] mb-1.5">Number of units</label>
                            <div class="inline-flex items-center rounded-xl border @error('number_of_units') border-[#EF4444]/40 @else border-[#E2E4EC] @enderror bg-white">
                                <button type="button" @click="step(-1)" :disabled="units <= 1" aria-label="Fewer units"
                                    class="h-11 w-11 flex items-center justify-center text-[#060D26] text-lg rounded-l-xl hover:bg-[#ECEEF6] disabled:opacity-40 disabled:hover:bg-transparent transition-colors duration-200 cursor-pointer">&minus;</button>
                                <input type="number" id="number_of_units" name="number_of_units" x-model.number="units" min="1" max="100" required
                                    class="h-11 w-16 border-0 border-x border-[#E2E4EC] rounded-none text-center text-[15px] font-semibold tabular-nums text-[#060D26] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#FF8A66]/40 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                <button type="button" @click="step(1)" :disabled="units >= 100" aria-label="More units"
                                    class="h-11 w-11 flex items-center justify-center text-[#060D26] text-lg rounded-r-xl hover:bg-[#ECEEF6] disabled:opacity-40 disabled:hover:bg-transparent transition-colors duration-200 cursor-pointer">+</button>
                            </div>
                            <p class="text-[11.5px] text-[#5B6A8E] mt-1.5">A rough count for tracking progress.</p>
                            @error('number_of_units')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        </div>
                    </x-card>

                    {{-- Utilities --}}
                    <x-card class="!p-4 sm:!p-5">
                        <fieldset>
                            <legend class="text-[13px] font-semibold text-[#060D26]">What's included in the rent?</legend>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5 mb-2.5">Tap what's covered. Anything left off is shown to tenants as billed separately.</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach($utilities as [$field, $label, $sub])
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="{{ $field }}" value="1" class="peer sr-only" @checked(old($field, $formValues[$field] ?? false))>
                                        <span class="flex items-center gap-2.5 rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 transition-colors duration-200 hover:border-[#060D26]/40
                                            peer-checked:border-[#FF8A66] peer-checked:bg-[#FF8A66]/[0.06] peer-checked:[&_.box]:bg-[#FF8A66] peer-checked:[&_.box]:border-[#FF8A66] peer-checked:[&_.tick]:opacity-100
                                            peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66] peer-focus-visible:ring-offset-2">
                                            <span class="box flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-[#5B6A8E]/40 bg-white transition-colors duration-200">
                                                <svg class="tick opacity-0 transition-opacity duration-200 text-[#060D26]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-[13px] font-semibold text-[#060D26] leading-tight" title="{{ $sub }}">{{ $label }}</span>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </x-card>

                    {{-- Who it's for + house rules --}}
                    <x-property-policy-fields :values="$formValues" />

                    {{-- Description + unit count --}}
                    <x-card class="space-y-5 !p-4 sm:!p-5">
                        <div>
                            <div class="flex items-baseline justify-between mb-1.5">
                                <label for="description" class="text-[13px] font-semibold text-[#060D26]">Description</label>
                                <span class="text-[11.5px] tabular-nums" :class="description.length >= 20 ? 'text-[#15803D]' : 'text-[#5B6A8E]'"
                                    x-text="description.length < 20 ? (20 - description.length) + ' more to go' : description.length + '/3000'"></span>
                            </div>
                            <textarea id="description" name="description" rows="4" x-model="description" minlength="20" maxlength="3000"
                                class="{{ $inputBase }} p-4 leading-relaxed @error('description') border-[#EF4444]/40 @else border-[#E2E4EC] @enderror"
                                placeholder="Describe the space, nearby landmarks, payment terms..." required></textarea>
                            @error('description')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        </div>                    </x-card>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 pt-1">
                        <a href="{{ route('landlord.properties.index') }}"
                            class="h-12 px-6 inline-flex items-center justify-center rounded-xl text-sm font-semibold text-[#060D26] bg-white border border-[#E2E4EC] hover:bg-[#ECEEF6] transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                            class="sm:ml-auto h-12 px-9 inline-flex items-center justify-center gap-2 rounded-xl text-sm font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer">
                            Save &amp; Continue
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
