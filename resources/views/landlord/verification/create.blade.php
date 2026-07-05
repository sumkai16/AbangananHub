@extends('layouts.app', ['searchBar' => false])

@section('content')
<div class="min-h-screen bg-gray-50/50 py-12">
    <div class="max-w-6xl mx-auto px-6 lg:px-8">

        {{-- Page header --}}
        <div class="flex flex-col gap-4 border-b border-gray-100 pb-6 mb-8">
            <a href="{{ route('properties.index') }}"
                class="inline-flex items-center gap-2 text-[13px] font-bold text-gray-400 hover:text-[#FF8A65] transition-colors w-fit">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Back
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-[#1A1A2E] tracking-tight">Landlord Verification</h1>
                <p class="text-[14px] text-gray-500 mt-1.5">
                    Tell us about your rental business and upload a valid government-issued ID so we can verify your
                    identity before you start listing properties.
                </p>
            </div>
        </div>

        @if ($verification?->isPending())
            <div class="rounded-3xl bg-blue-50/60 border border-blue-100 p-6 flex gap-4">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 flex items-center justify-center shrink-0 text-[#FF8A65]">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="font-bold text-[#1A1A2E] text-[14px]">Your application is under review</p>
                    <p class="mt-1 text-[13px] text-gray-500 leading-relaxed">
                        Submitted on {{ $verification->submitted_at->format('M j, Y \a\t g:i A') }}. We'll notify you
                        once it's been reviewed — this usually takes 1–2 business days.
                    </p>
                </div>
            </div>
        @else
            @if ($verification?->isRejected())
                <div class="rounded-3xl bg-red-50 border border-red-100 p-6 flex gap-4 mb-8">
                    <div class="w-10 h-10 rounded-2xl bg-red-100 flex items-center justify-center shrink-0 text-red-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold text-[#1A1A2E] text-[14px]">Your previous application was not approved</p>
                        <p class="mt-1 text-[13px] text-gray-500">{{ $verification->admin_notes }}</p>
                        <p class="mt-2 text-[13px] text-gray-500">Please address the issue above and resubmit your ID below.</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('landlord.verification.store') }}" method="POST" enctype="multipart/form-data"
                x-data="{ fileName: null, logoName: null }">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                    {{-- Left: Business information --}}
                    <div class="lg:col-span-7 bg-white border border-gray-100 rounded-3xl p-8 shadow-sm space-y-6">

                        <h3 class="text-[16px] font-bold text-[#1A1A2E] border-b border-gray-50 pb-4">Business
                            information</h3>

                        <div>
                            <label for="business_name"
                                class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                                Rental business name
                            </label>
                            <input type="text" name="business_name" id="business_name"
                                value="{{ old('business_name') }}" placeholder="e.g. Emman's Apartment"
                                class="w-full h-12 px-4 rounded-2xl border {{ $errors->has('business_name') ? 'border-red-300' : 'border-gray-200' }} text-[14px] font-medium text-[#1A1A2E] focus:outline-none focus:ring-2 focus:ring-[#FF8A65]/20 focus:border-[#FF8A65] transition-all">
                            @error('business_name')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description"
                                class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                                Business description <span class="normal-case font-normal">(optional)</span>
                            </label>
                            <textarea name="description" id="description" rows="4"
                                placeholder="A short description tenants will see on your business profile"
                                class="w-full p-4 rounded-2xl border {{ $errors->has('description') ? 'border-red-300' : 'border-gray-200' }} text-[14px] text-[#1A1A2E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#FF8A65]/20 focus:border-[#FF8A65] transition-all">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label for="contact_number"
                                    class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                                    Contact number
                                </label>
                                <input type="text" name="contact_number" id="contact_number"
                                    value="{{ old('contact_number') }}" placeholder="09XX XXX XXXX"
                                    class="w-full h-12 px-4 rounded-2xl border {{ $errors->has('contact_number') ? 'border-red-300' : 'border-gray-200' }} text-[14px] font-medium text-[#1A1A2E] focus:outline-none focus:ring-2 focus:ring-[#FF8A65]/20 focus:border-[#FF8A65] transition-all">
                                @error('contact_number')
                                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="business_address"
                                    class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                                    Business address
                                </label>
                                <input type="text" name="business_address" id="business_address"
                                    value="{{ old('business_address') }}" placeholder="e.g. Talisay City, Cebu"
                                    class="w-full h-12 px-4 rounded-2xl border {{ $errors->has('business_address') ? 'border-red-300' : 'border-gray-200' }} text-[14px] font-medium text-[#1A1A2E] focus:outline-none focus:ring-2 focus:ring-[#FF8A65]/20 focus:border-[#FF8A65] transition-all">
                                @error('business_address')
                                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="logo"
                                class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                                Business logo <span class="normal-case font-normal">(optional)</span>
                            </label>

                            <label for="logo"
                                class="flex items-center justify-between gap-4 rounded-2xl border-2 border-dashed border-gray-200 px-5 py-4 cursor-pointer hover:border-[#FF8A65] transition-colors group">
                                <span class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-gray-400 group-hover:text-[#FF8A65] transition-colors shrink-0"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-[13.5px]"
                                        x-text="logoName || 'Click to choose a logo image (JPG or PNG)'"
                                        :class="logoName ? 'text-[#1A1A2E] font-medium' : 'text-gray-400'"></span>
                                </span>
                                <span class="text-xs text-gray-400 whitespace-nowrap shrink-0">Max 2MB</span>
                            </label>

                            <input type="file" name="logo" id="logo" accept=".jpg,.jpeg,.png" class="hidden"
                                @change="logoName = $event.target.files[0]?.name">

                            @error('logo')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    {{-- Right: ID upload + summary (sticky) --}}
                    <div class="lg:col-span-5">
                        <div class="sticky top-8 space-y-5">

                            {{-- Government ID card --}}
                            <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm space-y-4">
                                <h3 class="text-[14px] font-bold text-[#1A1A2E] border-b border-gray-50 pb-3">
                                    Government-issued ID</h3>

                                <div class="rounded-2xl bg-blue-50/50 border border-blue-100 p-4 flex gap-3">
                                    <svg class="w-4 h-4 text-[#FF8A65] shrink-0 mt-0.5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <p class="text-[12.5px] text-gray-500 leading-relaxed">
                                        Upload a clear photo or scan of a valid ID — PhilSys, Driver's License,
                                        Passport, or UMID. All details must be legible.
                                    </p>
                                </div>

                                <label for="government_id"
                                    class="flex flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-gray-200 px-6 py-8 cursor-pointer hover:border-[#FF8A65] transition-colors group text-center">
                                    <div
                                        class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-400 group-hover:text-[#FF8A65] transition-all">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12l-4.5-4.5L7.5 12M12 7.5v9" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[13.5px] font-bold text-[#1A1A2E] group-hover:text-[#FF8A65] transition-colors"
                                            x-text="fileName || 'Select ID to upload'"></p>
                                        <p class="text-xs text-gray-400 mt-1">JPG, PNG, or PDF &middot; Max 5MB</p>
                                    </div>
                                </label>

                                <input type="file" name="government_id" id="government_id"
                                    accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                                    @change="fileName = $event.target.files[0]?.name">

                                @error('government_id')
                                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- What happens next --}}
                            <div class="rounded-3xl bg-blue-50/50 border border-blue-100 p-5 space-y-3">
                                <p class="text-sm font-bold text-[#1A1A2E]">What happens next?</p>
                                <ul class="space-y-2.5">
                                    @foreach ([
                                        ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Our team reviews your submission within 1–2 business days.'],
                                        ['icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'text' => "You'll get a notification once your account is approved."],
                                        ['icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'text' => 'Start listing properties and managing tenants right away.'],
                                    ] as $step)
                                        <li class="flex items-start gap-2.5">
                                            <svg class="w-4 h-4 text-[#FF8A65] shrink-0 mt-0.5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="{{ $step['icon'] }}" />
                                            </svg>
                                            <span class="text-[12.5px] text-gray-500 leading-relaxed">{{ $step['text'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-3">
                                <a href="{{ route('properties.index') }}"
                                    class="w-1/3 h-12 rounded-full border border-gray-200 bg-white hover:bg-gray-50 font-bold text-[13.5px] text-gray-700 flex items-center justify-center transition-colors">
                                    Cancel
                                </a>
                                <button type="submit"
                                    class="flex-1 h-12 rounded-full bg-[#FF8A65] text-white font-bold text-[13.5px] shadow-sm hover:bg-[#1e5bb8] transition-all duration-300">
                                    Submit for Review
                                </button>
                            </div>

                        </div>
                    </div>

                </div>
            </form>
        @endif

    </div>
</div>
@endsection
