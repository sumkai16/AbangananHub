{{-- "Edit business page" on My Profile: the business name, logo, description, contact and address that tenants see,
     edited on the page that shows them (they used to sit on Settings). Opened by any button that does
     $dispatch('open-business-sheet'). Posts to Landlord\ProfileController::update with return_to=profile, which carries
     no GCash keys, so the payout account is left as it was. A validation error on these fields reopens the sheet. --}}
@php
    $business = $user->rentalBusiness;
    $sheetErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'business_') || $key === 'logo');
    $label = 'mb-1.5 block text-[13px] font-semibold text-[#060D26]';
    $field = 'w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[16px] sm:text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 transition-colors focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20';
    $error = 'mt-1.5 text-[12px] font-medium text-[#DC2626]';
@endphp

<template x-teleport="body">
    <div x-data="{ open: @js($sheetErrors), preview: null }"
        x-on:open-business-sheet.window="open = true; $nextTick(() => $refs.sheet.focus())"
        x-on:keydown.escape.window="open = false"
        x-show="open" x-cloak class="fixed inset-0 z-[200] bg-[#060D26]/45"
        x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex h-full items-end justify-center sm:items-center sm:p-6">
            <form method="POST" action="{{ route('landlord.profile.update') }}" enctype="multipart/form-data" x-ref="sheet" tabindex="-1"
                x-on:click.outside="open = false" role="dialog" aria-modal="true" aria-labelledby="business-sheet-title"
                x-show="open"
                x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-[0.98]" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                class="flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-[0_24px_48px_-12px_rgba(6,13,38,0.35)] outline-none sm:max-h-[85vh] sm:max-w-lg sm:rounded-2xl">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="profile">

                <div class="flex items-start gap-3 border-b border-[#E2E4EC] px-5 py-4 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <h2 id="business-sheet-title" class="text-[16px] font-semibold text-[#060D26]">Edit business page</h2>
                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">Tenants see this on your page and your listings. Your own name, photo, email and number stay private.</p>
                    </div>
                    <button type="button" x-on:click="open = false" aria-label="Close"
                        class="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-[#5B6A8E] transition-colors hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                    {{-- Logo --}}
                    <div class="flex items-center gap-4">
                        <template x-if="preview">
                            <img :src="preview" alt="New logo preview" class="h-16 w-16 shrink-0 rounded-xl border border-[#E2E4EC] object-cover">
                        </template>
                        <template x-if="!preview">
                            @if($business?->logo_url)
                                <img loading="lazy" decoding="async" src="{{ $business->logo_url }}" alt="Current logo" class="h-16 w-16 shrink-0 rounded-xl border border-[#E2E4EC] object-cover">
                            @else
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-[#ECEEF6] text-[18px] font-bold text-[#060D26]">{{ $user->publicInitials() }}</div>
                            @endif
                        </template>
                        <div class="min-w-0">
                            <label class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-full border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC] focus-within:ring-2 focus-within:ring-[#FF8A66]/40">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                {{ $business?->logo_url ? 'Change logo' : 'Upload logo' }}
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                    x-on:change="if ($event.target.files[0]) preview = URL.createObjectURL($event.target.files[0])">
                            </label>
                            <p class="mt-1.5 text-[12px] text-[#5B6A8E]">JPG, PNG or WebP, up to 2 MB.</p>
                            @error('logo') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="business_name" class="{{ $label }}">Business name</label>
                        <input type="text" id="business_name" name="business_name" maxlength="255" placeholder="e.g. Santos Rentals"
                            value="{{ old('business_name', $business?->business_name) }}" class="{{ $field }} h-11">
                        @error('business_name') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="business_description" class="{{ $label }}">About your rentals</label>
                        <textarea id="business_description" name="business_description" rows="4" maxlength="1000"
                            placeholder="What you rent, where, and what tenants can expect."
                            class="{{ $field }} py-3 resize-none">{{ old('business_description', $business?->description) }}</textarea>
                        @error('business_description') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-4">
                        <div>
                            <label for="business_contact" class="{{ $label }}">Business contact</label>
                            <input type="text" id="business_contact" name="business_contact" inputmode="tel" maxlength="20" placeholder="09XX XXX XXXX"
                                value="{{ old('business_contact', $business?->contact_number) }}" class="{{ $field }} h-11">
                            @error('business_contact') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="business_address" class="{{ $label }}">Business address</label>
                            <input type="text" id="business_address" name="business_address" maxlength="500" placeholder="Cebu City, Cebu"
                                value="{{ old('business_address', $business?->business_address) }}" class="{{ $field }} h-11">
                            @error('business_address') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-[#E2E4EC] px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" x-on:click="open = false"
                        class="inline-flex h-11 items-center justify-center rounded-full px-5 text-[14px] font-semibold text-[#5B6A8E] transition-colors hover:text-[#060D26] cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-full bg-[#FF8A66] px-6 text-[14px] font-semibold text-[#060D26] transition-colors hover:bg-[#E96F4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26]/30 cursor-pointer">
                        Save business page
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
