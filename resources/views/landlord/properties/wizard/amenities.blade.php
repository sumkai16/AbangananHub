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

            <x-property-wizard-stepper current="amenities" :property="$property" />

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Step 3 of 6</p>
                <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">What does the building offer?</h1>
                <p class="mt-2 text-sm text-[#64748B] leading-relaxed max-w-md">Shared, building-wide amenities — not what's inside a specific unit. Optional, but tenants filter on these.</p>

                <form method="POST" action="{{ route('properties.wizard.amenities.store', $property) }}" class="mt-7 max-w-3xl">
                    @csrf

                    @php $selected = old('amenities', $property->amenities->pluck('amenity_id')->all()); @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                        @foreach($amenities->groupBy('category') as $category => $group)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2.5">{{ $category }}</p>
                                <div class="space-y-2">
                                    @foreach($group as $amenity)
                                        <label class="flex items-center gap-2.5 text-[14px] text-[#1F2937] cursor-pointer">
                                            <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}"
                                                @checked(collect($selected)->contains($amenity->amenity_id))
                                                class="w-[18px] h-[18px] rounded-md border-[#E2E8F0] text-[#2AA7A1] focus:ring-[#2AA7A1]/30 focus:ring-offset-0">
                                            {{ $amenity->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('amenities')<p class="text-xs text-[#EF4444] mt-3">{{ $message }}</p>@enderror

                    <div class="mt-7 pt-5 border-t border-[#E2E8F0] flex items-center gap-3">
                        <a href="{{ route('properties.wizard.location.edit', $property) }}"
                            class="px-5 py-3 rounded-xl text-sm font-semibold text-[#1F2937] bg-white border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Back
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
