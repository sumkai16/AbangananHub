@extends('layouts.landlord')

@section('content')
<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors w-fit mb-6">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Properties
        </a>

        @if($errors->any())
            <div class="max-w-3xl mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
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
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Step 1 of 6</p>
                <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">Tell us about the property</h1>
                <p class="mt-2 text-sm text-[#64748B] leading-relaxed max-w-md">Its name, type, and a description tenants will read first.</p>

                <form method="POST" action="{{ $property ? route('properties.wizard.info.update', $property) : route('properties.wizard.info.store') }}"
                    class="mt-7 max-w-2xl space-y-6">
                    @csrf
                    @if($property) @method('PUT') @endif

                    <div>
                        <label for="title" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Property name</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $formValues['title'] ?? '') }}" minlength="10" maxlength="150"
                            class="w-full h-12 px-4 rounded-xl border @error('title') border-[#EF4444]/40 @else border-[#E2E8F0] @enderror text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/25 focus:border-[#2AA7A1] transition-all"
                            placeholder="e.g., Patenio Apartment" required>
                        @error('title')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Property type</label>
                        <x-styled-select name="property_type" :options="['Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House']"
                            :selected="old('property_type', $formValues['property_type'] ?? '')" placeholder="Select type" required
                            class="w-full h-12 px-4 rounded-xl border {{ $errors->has('property_type') ? 'border-[#EF4444]/40' : 'border-[#E2E8F0]' }} text-[14px] text-[#1F2937]" />
                        @error('property_type')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Description</label>
                        <textarea name="description" rows="6" minlength="20" maxlength="3000"
                            class="w-full p-4 rounded-xl border @error('description') border-[#EF4444]/40 @else border-[#E2E8F0] @enderror text-[14px] text-[#1F2937] leading-relaxed placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/25 focus:border-[#2AA7A1] transition-all"
                            placeholder="Describe the space, amenities, nearby landmarks, house rules, payment terms..." required>{{ old('description', $formValues['description'] ?? '') }}</textarea>
                        @error('description')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div class="max-w-[220px]">
                        <label for="number_of_units" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Number of units</label>
                        <input type="number" id="number_of_units" name="number_of_units" value="{{ old('number_of_units', $formValues['number_of_units'] ?? '') }}" min="1" max="100"
                            class="w-full h-12 px-4 rounded-xl border @error('number_of_units') border-[#EF4444]/40 @else border-[#E2E8F0] @enderror text-[14px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/25 focus:border-[#2AA7A1] transition-all" required>
                        <p class="text-[11.5px] text-[#94A3B8] mt-1.5">A rough count, just to track your progress later — add more or fewer as you go.</p>
                        @error('number_of_units')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-7 pt-5 border-t border-[#E2E8F0] flex items-center gap-3">
                        <a href="{{ route('landlord.properties.index') }}"
                            class="px-5 py-3 rounded-xl text-sm font-semibold text-[#1F2937] bg-white border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Cancel
                        </a>
                        <button type="submit"
                            class="ml-auto px-9 py-3 rounded-xl text-sm font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all duration-150">
                            Save & Continue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
