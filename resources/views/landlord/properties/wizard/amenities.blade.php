@extends('layouts.landlord')

@section('content')
<div class="py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors w-fit mb-6">
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

            <x-property-wizard-stepper current="amenities" :property="$property" :checklist="$checklist ?? null" />

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#B35A3D]">Step 3 of 6</p>
                <h1 class="mt-1.5 text-2xl font-semibold tracking-tight text-[#060D26]">What does the property offer?</h1>
                <p class="mt-2 text-sm text-[#5B6A8E] leading-relaxed max-w-md">Amenities shared across the whole property — not what's inside a specific unit. Optional, but tenants filter on these.</p>

                @php $selected = collect(old('amenities', $property->amenities->pluck('amenity_id')->all()))->map(fn ($id) => (int) $id); @endphp

                <form method="POST" action="{{ route('properties.wizard.amenities.store', $property) }}" class="mt-7 max-w-3xl"
                    x-data="{ n: {{ $selected->count() }} }">
                    @csrf

                    <div class="md:columns-2 md:gap-5">
                        @foreach($amenities->groupBy('category') as $category => $group)
                            <x-card class="mb-5 break-inside-avoid !p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <h2 class="text-[13px] font-semibold text-[#060D26]">{{ $category }}</h2>
                                    <span class="text-[11.5px] text-[#5B6A8E] tabular-nums">{{ $group->count() }}</span>
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    @foreach($group as $amenity)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}" class="peer sr-only"
                                                @checked($selected->contains((int) $amenity->amenity_id))
                                                @change="n += $event.target.checked ? 1 : -1">
                                            <span class="flex items-center gap-3 rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[#060D26] transition-colors duration-200 hover:border-[#060D26]/40
                                                peer-checked:border-[#FF8A66] peer-checked:bg-[#FF8A66]/[0.06] peer-checked:[&_.box]:bg-[#FF8A66] peer-checked:[&_.box]:border-[#FF8A66] peer-checked:[&_.tick]:opacity-100 peer-checked:[&_.ico]:text-[#B35A3D]
                                                peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66] peer-focus-visible:ring-offset-2">
                                                <x-amenity-icon :name="$amenity->name" class="ico w-4 h-4 shrink-0 text-[#5B6A8E] transition-colors duration-200" />
                                                <span class="flex-1 min-w-0 text-[13.5px] font-medium truncate">{{ $amenity->name }}</span>
                                                <span class="box flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-md border border-[#5B6A8E]/40 bg-white transition-colors duration-200">
                                                    <svg class="tick opacity-0 transition-opacity duration-200 text-[#060D26]" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                    @error('amenities')<p class="text-xs text-[#DC2626] mt-1">{{ $message }}</p>@enderror

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 pt-1">
                        <a href="{{ route('properties.wizard.location.edit', $property) }}"
                            class="h-12 px-6 inline-flex items-center justify-center rounded-xl text-sm font-semibold text-[#060D26] bg-white border border-[#E2E4EC] hover:bg-[#ECEEF6] transition-colors duration-200">
                            Back
                        </a>
                        <p class="sm:ml-auto text-[13px] text-[#5B6A8E] text-center tabular-nums" aria-live="polite">
                            <span class="font-semibold text-[#060D26]" x-text="n"></span> selected
                        </p>
                        <button type="submit"
                            class="h-12 px-9 inline-flex items-center justify-center gap-2 rounded-xl text-sm font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer">
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
