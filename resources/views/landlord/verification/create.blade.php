@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-10 sm:py-16">

        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-semibold text-[#0F172A]">Landlord Verification</h1>
            <p class="mt-2 text-sm text-[#9B9F98]">
                Tell us about your rental business and upload a valid government-issued ID so we can verify your identity before you start listing properties.
            </p>
        </div>

        @if ($verification?->isPending())
            <div class="rounded-xl bg-[#DBEAFE] border border-[#3B82F6]/30 p-5 flex gap-3">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-[#3B82F6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="font-medium text-[#0F172A]">Your application is under review</p>
                    <p class="mt-1 text-sm text-[#9B9F98]">
                        Submitted on {{ $verification->submitted_at->format('M j, Y \a\t g:i A') }}. We'll notify you once it's
                        been reviewed — this usually takes 1–2 business days.
                    </p>
                </div>
            </div>
        @else
            @if ($verification?->isRejected())
                <div class="rounded-xl bg-[#BD5434]/10 border border-[#BD5434]/30 p-5 flex gap-3 mb-6">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-[#BD5434]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-medium text-[#0F172A]">Your previous application was not approved</p>
                        <p class="mt-1 text-sm text-[#9B9F98]">{{ $verification->admin_notes }}</p>
                        <p class="mt-2 text-sm text-[#9B9F98]">Please address the issue above and resubmit your ID below.</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('landlord.verification.store') }}" method="POST" enctype="multipart/form-data"
                class="space-y-5" x-data="{ fileName: null, logoName: null }">
                @csrf

                <div>
                    <label for="business_name" class="block text-sm font-medium text-[#0F172A] mb-2">
                        Rental business name
                    </label>
                    <input type="text" name="business_name" id="business_name" value="{{ old('business_name') }}"
                        class="w-full rounded-lg border border-[#9B9F98] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:border-[#3B82F6]"
                        placeholder="e.g. Emman's Apartment">
                    @error('business_name')
                        <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-[#0F172A] mb-2">
                        Business description <span class="text-[#9B9F98]">(optional)</span>
                    </label>
                    <textarea name="description" id="description" rows="3"
                        class="w-full rounded-lg border border-[#9B9F98] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:border-[#3B82F6]"
                        placeholder="A short description tenants will see on your business profile">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-[#0F172A] mb-2">
                            Contact number
                        </label>
                        <input type="text" name="contact_number" id="contact_number" value="{{ old('contact_number') }}"
                            class="w-full rounded-lg border border-[#9B9F98] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:border-[#3B82F6]"
                            placeholder="09XX XXX XXXX">
                        @error('contact_number')
                            <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="business_address" class="block text-sm font-medium text-[#0F172A] mb-2">
                            Business address
                        </label>
                        <input type="text" name="business_address" id="business_address" value="{{ old('business_address') }}"
                            class="w-full rounded-lg border border-[#9B9F98] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:border-[#3B82F6]"
                            placeholder="e.g. Talisay City, Cebu">
                        @error('business_address')
                            <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="logo" class="block text-sm font-medium text-[#0F172A] mb-2">
                        Business logo <span class="text-[#9B9F98]">(optional)</span>
                    </label>

                    <label for="logo"
                        class="flex items-center justify-between gap-4 rounded-lg border border-dashed border-[#9B9F98] px-4 py-4 cursor-pointer hover:bg-[#DBEAFE]/40 transition">
                        <span class="flex items-center gap-3 text-sm">
                            <svg class="w-5 h-5 text-[#3B82F6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 16.5V8.25A2.25 2.25 0 015.25 6h13.5A2.25 2.25 0 0121 8.25v8.25m-18 0A2.25 2.25 0 005.25 18.75h13.5A2.25 2.25 0 0021 16.5m-18 0V12" />
                            </svg>
                            <span x-text="logoName || 'Click to choose a logo image (JPG or PNG)'"
                                :class="logoName ? 'text-[#0F172A]' : 'text-[#9B9F98]'"></span>
                        </span>
                        <span class="text-xs text-[#9B9F98] whitespace-nowrap">Max 2MB</span>
                    </label>

                    <input type="file" name="logo" id="logo" accept=".jpg,.jpeg,.png" class="hidden"
                        @change="logoName = $event.target.files[0]?.name">

                    @error('logo')
                        <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t border-[#9B9F98]/30 pt-5">
                    <label for="government_id" class="block text-sm font-medium text-[#0F172A] mb-2">
                        Government-issued ID
                    </label>

                    <label for="government_id"
                        class="flex items-center justify-between gap-4 rounded-lg border border-dashed border-[#9B9F98] px-4 py-4 cursor-pointer hover:bg-[#DBEAFE]/40 transition">
                        <span class="flex items-center gap-3 text-sm">
                            <svg class="w-5 h-5 text-[#3B82F6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12l-4.5-4.5L7.5 12M12 7.5v9" />
                            </svg>
                            <span x-text="fileName || 'Click to choose a file (JPG, PNG, or PDF)'"
                                :class="fileName ? 'text-[#0F172A]' : 'text-[#9B9F98]'"></span>
                        </span>
                        <span class="text-xs text-[#9B9F98] whitespace-nowrap">Max 5MB</span>
                    </label>

                    <input type="file" name="government_id" id="government_id" accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                        @change="fileName = $event.target.files[0]?.name">

                    @error('government_id')
                        <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-[#3B82F6] px-6 py-3 text-sm font-medium text-white hover:brightness-95 transition">
                    Submit for Review
                </button>
            </form>
        @endif

    </div>
@endsection