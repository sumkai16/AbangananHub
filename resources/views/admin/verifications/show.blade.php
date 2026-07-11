@extends('layouts.admin')

@section('page-title', 'Verification Review')

@section('content')
<div x-data="{ previewImage: null }">

    {{-- Back --}}
    <a href="{{ route('admin.verifications.index') }}"
       class="inline-flex items-center gap-2 text-[13px] font-bold text-gray-400 hover:text-[#156F8C] transition-colors mb-6">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to verifications
    </a>

    {{-- Applicant header --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm px-5 sm:px-7 py-5 mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                <span class="text-[#156F8C] text-[16px] font-extrabold">
                    {{ strtoupper(substr($verification->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($verification->user->last_name ?? '', 0, 1)) }}
                </span>
            </div>
            <div>
                <h1 class="text-[18px] font-extrabold text-[#1A1A2E] leading-tight">
                    {{ $verification->user->first_name }} {{ $verification->user->last_name }}
                </h1>
                <p class="text-[13px] text-gray-400 mt-0.5">{{ $verification->user->email }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right mr-2">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Submitted</p>
                <p class="text-[13px] font-semibold text-[#1A1A2E]">{{ $verification->submitted_at->format('M d, Y') }}</p>
            </div>
            <x-verification-status-badge :status="$verification->verification_status" />
        </div>
    </div>

    {{-- Two-column: Evidence + Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-4">

        {{-- Left column (3/5) — Photos --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- Government ID photos --}}
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                    </svg>
                    <h2 class="text-[14px] font-bold text-[#1A1A2E]">Government ID</h2>
                    <span class="ml-auto text-[12px] font-semibold text-gray-400">{{ $verification->id_type }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    {{-- Front --}}
                    <div>
                        <div class="rounded-xl border border-gray-100 overflow-hidden aspect-[4/3] cursor-pointer hover:border-[#2AA7A1] transition-colors"
                             @click="previewImage = '{{ route('verifications.preview', [$verification, 'front']) }}'">
                            <img src="{{ route('verifications.preview', [$verification, 'front']) }}"
                                 alt="ID front" class="w-full h-full object-cover">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1.5 text-center font-semibold">Front</p>
                    </div>

                    {{-- Back --}}
                    @if ($verification->id_back)
                        <div>
                            <div class="rounded-xl border border-gray-100 overflow-hidden aspect-[4/3] cursor-pointer hover:border-[#2AA7A1] transition-colors"
                                 @click="previewImage = '{{ route('verifications.preview', [$verification, 'back']) }}'">
                                <img src="{{ route('verifications.preview', [$verification, 'back']) }}"
                                     alt="ID back" class="w-full h-full object-cover">
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1.5 text-center font-semibold">Back</p>
                        </div>
                    @else
                        <div>
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/50 aspect-[4/3] flex items-center justify-center">
                                <span class="text-[12px] text-gray-400">Not required</span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1.5 text-center font-semibold">Back</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Selfie --}}
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <h2 class="text-[14px] font-bold text-[#1A1A2E]">Selfie</h2>
                </div>
                <div class="max-w-[200px]">
                    <div class="rounded-xl border border-gray-100 overflow-hidden aspect-[3/4] cursor-pointer hover:border-[#2AA7A1] transition-colors"
                         @click="previewImage = '{{ route('verifications.preview', [$verification, 'selfie']) }}'">
                        <img src="{{ route('verifications.preview', [$verification, 'selfie']) }}"
                             alt="Selfie" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column (2/5) — OCR + ID details + Business --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- OCR results --}}
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5" />
                    </svg>
                    <h2 class="text-[14px] font-bold text-[#1A1A2E]">OCR results</h2>
                </div>

                <div class="space-y-2">
                    {{-- Name match --}}
                    @if ($verification->ocr_status)
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12px]
                            {{ $verification->ocr_status === 'pass' ? 'bg-green-50' : ($verification->ocr_status === 'partial' ? 'bg-amber-50' : 'bg-red-50') }}">
                            @if ($verification->ocr_status === 'pass')
                                <svg class="w-4 h-4 text-[#22C55E] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                <span class="text-[#1A1A2E]">Name: <strong>{{ $verification->ocr_name }}</strong> ({{ $verification->ocr_confidence }}%)</span>
                            @elseif ($verification->ocr_status === 'partial')
                                <svg class="w-4 h-4 text-[#FBBF24] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                <span class="text-[#1A1A2E]">Partial: <strong>{{ $verification->ocr_name }}</strong> ({{ $verification->ocr_confidence }}%)</span>
                            @else
                                <svg class="w-4 h-4 text-[#EF4444] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                <span class="text-[#1A1A2E]">Name not found on ID</span>
                            @endif
                        </div>
                    @endif

                    {{-- ID number --}}
                    @if ($verification->id_number)
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 text-[12px]">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5" />
                            </svg>
                            <span class="text-[#1A1A2E]">ID: <strong>{{ $verification->id_number }}</strong></span>
                        </div>
                    @endif

                    @if (! $verification->ocr_status && ! $verification->id_number)
                        <p class="text-[12px] text-gray-400 px-1">OCR data not available — review ID photos manually.</p>
                    @endif
                </div>
            </div>

            {{-- ID details --}}
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    <h2 class="text-[14px] font-bold text-[#1A1A2E]">ID details</h2>
                </div>

                <div class="space-y-3 text-[13px]">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">ID type</p>
                        <p class="font-semibold text-[#1A1A2E]">{{ $verification->id_type }}</p>
                    </div>
                    @if ($verification->ocr_name)
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Name on ID</p>
                            <p class="font-semibold text-[#1A1A2E]">{{ $verification->ocr_name }}</p>
                        </div>
                    @endif
                    @if ($verification->id_number)
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">ID number</p>
                            <p class="font-semibold text-[#1A1A2E]">{{ $verification->id_number }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Business details --}}
            @if ($verification->business_name)
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                        </svg>
                        <h2 class="text-[14px] font-bold text-[#1A1A2E]">Business details</h2>
                    </div>

                    <div class="space-y-3 text-[13px]">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Business name</p>
                            <p class="font-semibold text-[#1A1A2E]">{{ $verification->business_name }}</p>
                        </div>
                        @if ($verification->contact_number)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Contact</p>
                                <p class="font-semibold text-[#1A1A2E]">{{ $verification->contact_number }}</p>
                            </div>
                        @endif
                        @if ($verification->business_address)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Address</p>
                                <p class="font-semibold text-[#1A1A2E]">{{ $verification->business_address }}</p>
                            </div>
                        @endif
                        @if ($verification->description)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Description</p>
                                <p class="text-gray-600 leading-relaxed">{{ $verification->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Rejection note (if rejected) --}}
    @if ($verification->verification_status === 'Rejected' && $verification->admin_notes)
        <div class="bg-red-50 border border-red-100 rounded-2xl p-5 mb-4 flex gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
            </svg>
            <div>
                <p class="text-[12px] font-bold uppercase tracking-wider text-red-600 mb-1">Rejection reason</p>
                <p class="text-[13.5px] text-[#1A1A2E]">{{ $verification->admin_notes }}</p>
            </div>
        </div>
    @endif

    {{-- Approved note --}}
    @if ($verification->verification_status === 'Approved')
        <div class="bg-green-50 border border-green-100 rounded-2xl p-5 mb-4 flex gap-3">
            <svg class="w-5 h-5 text-[#22C55E] shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div>
                <p class="text-[12px] font-bold uppercase tracking-wider text-green-700 mb-1">Approved</p>
                <p class="text-[13.5px] text-[#1A1A2E]">
                    Approved on {{ $verification->reviewed_at->format('M d, Y \a\t g:i A') }}
                    @if ($verification->reviewer)
                        by {{ $verification->reviewer->first_name }} {{ $verification->reviewer->last_name }}
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Actions (pending only) --}}
    @if ($verification->verification_status === 'Pending')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6" x-data="{ showReject: false }">
            <h3 class="text-[14px] font-bold text-[#1A1A2E] mb-4">Admin action</h3>

            <div class="flex gap-3 mb-4">
                <form method="POST" action="{{ route('admin.verifications.approve', $verification) }}"
                      onsubmit="return confirm('Approve this landlord application?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 h-11 px-6 rounded-xl bg-[#22C55E] hover:brightness-95 text-white text-[13.5px] font-bold transition-all shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Approve
                    </button>
                </form>

                <button type="button" @click="showReject = !showReject"
                        class="inline-flex items-center gap-2 h-11 px-6 rounded-xl bg-red-500 hover:bg-red-600 text-white text-[13.5px] font-bold transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reject
                </button>
            </div>

            <div x-show="showReject" x-cloak x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 class="border-t border-gray-100 pt-4">
                <form method="POST" action="{{ route('admin.verifications.reject', $verification) }}"
                      onsubmit="return confirm('Reject this application? This cannot be undone.');">
                    @csrf
                    <label for="admin_notes" class="block text-[12px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                        Reason for rejection
                    </label>
                    <textarea name="admin_notes" id="admin_notes" rows="3" required
                              class="w-full rounded-xl border border-gray-200 px-4 py-3 text-[14px] text-[#1A1A2E] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all resize-none"
                              placeholder="Explain why — the applicant will see this message."></textarea>
                    @error('admin_notes')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="mt-3 flex gap-2">
                        <button type="submit"
                                class="h-10 px-5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-[13px] font-bold transition-colors">
                            Confirm rejection
                        </button>
                        <button type="button" @click="showReject = false"
                                class="h-10 px-5 rounded-xl border border-gray-200 text-[13px] font-semibold text-gray-500 hover:text-[#1A1A2E] transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Lightbox --}}
    <div x-show="previewImage"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="previewImage = null"
         @keydown.escape.window="previewImage = null"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 cursor-pointer"
         style="display: none;">
        <button type="button" @click="previewImage = null"
                class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
            <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
        <img :src="previewImage" alt="Document preview" @click.stop class="max-w-full max-h-[85vh] rounded-lg cursor-default">
    </div>

</div>
@endsection