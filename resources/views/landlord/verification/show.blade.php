@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="min-h-screen bg-[#F7FCFC] py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto" x-data="{ previewImage: null }">

            {{-- Back link --}}
            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center gap-1.5 text-sm text-[#2AA7A1] hover:text-[#156F8C] mb-6 transition-colors">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to dashboard
            </a>

            {{-- Header --}}
            <h1 class="text-2xl font-bold text-[#156F8C] mb-1">Landlord verification</h1>
            <p class="text-sm text-[#64748B] mb-6">Status of your identity verification application.</p>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- Status banner                                      --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if ($verification->verification_status === 'Approved')
                <div class="rounded-lg border border-[#22C55E]/30 bg-green-50 p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-[#22C55E] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937]">You're a verified landlord</p>
                            <p class="text-sm text-[#64748B] mt-1">
                                Approved on {{ $verification->reviewed_at->format('F j, Y') }}.
                                You can now create and manage property listings on AbangananHub.
                            </p>
                            <a href="{{ route('landlord.listings.index') }}"
                               class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all">
                                Go to your listings
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

            @elseif ($verification->verification_status === 'Rejected')
                <div class="rounded-lg border border-[#EF4444]/30 bg-red-50 p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-[#EF4444] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937]">Application not approved</p>
                            <p class="text-sm text-[#64748B] mt-1">
                                Reviewed on {{ $verification->reviewed_at->format('F j, Y') }}.
                            </p>
                            @if ($verification->admin_notes)
                                <div class="mt-3 rounded-lg bg-white/60 border border-[#EF4444]/10 p-3">
                                    <p class="text-xs font-semibold text-[#64748B] mb-1">Reason</p>
                                    <p class="text-sm text-[#1F2937]">{{ $verification->admin_notes }}</p>
                                </div>
                            @endif
                            <a href="{{ route('landlord.verification.create') }}"
                               class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all">
                                Update and resubmit
                            </a>
                        </div>
                    </div>
                </div>

            @else {{-- Pending --}}
                <div class="rounded-lg border border-[#FBBF24]/30 bg-amber-50 p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-[#FBBF24] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937]">Application under review</p>
                            <p class="text-sm text-[#64748B] mt-1">
                                Submitted on {{ $verification->submitted_at->format('F j, Y') }}.
                                An admin will review your documents and notify you once a decision is made.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Two-column layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Left column (2/3) --}}
                <div class="lg:col-span-2 space-y-4">

                    {{-- ═══════════════════════════════════════════ --}}
                    {{-- Identity verification card                 --}}
                    {{-- ═══════════════════════════════════════════ --}}
                    <div class="bg-white rounded-lg border border-[#E2E8F0] p-5 sm:p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-[#64748B]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                            </svg>
                            <h2 class="text-base font-semibold text-[#1F2937]">Identity verification</h2>
                        </div>

                        {{-- ID info grid --}}
                        <div class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-2 text-sm mb-5">
                            <span class="text-xs text-[#64748B]">ID type</span>
                            <span class="text-sm text-[#1F2937]">{{ $verification->id_type }}</span>

                            @if ($verification->ocr_name)
                                <span class="text-xs text-[#64748B]">Name on ID</span>
                                <span class="text-sm text-[#1F2937]">{{ $verification->ocr_name }}</span>
                            @endif

                            @if ($verification->id_number)
                                <span class="text-xs text-[#64748B]">ID number</span>
                                <span class="text-sm text-[#1F2937]">{{ $verification->id_number }}</span>
                            @endif
                        </div>

                        {{-- OCR status checks --}}
                        @if ($verification->ocr_status)
                            <div class="flex flex-col gap-2 mb-5">
                                @if ($verification->ocr_status === 'pass')
                                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-green-50">
                                        <svg class="w-4 h-4 text-[#22C55E] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <span class="text-xs text-[#1F2937]">Name matched your account</span>
                                    </div>
                                @elseif ($verification->ocr_status === 'partial')
                                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50">
                                        <svg class="w-4 h-4 text-[#FBBF24] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                        <span class="text-xs text-[#1F2937]">Partial name match — admin will verify</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-50">
                                        <svg class="w-4 h-4 text-[#EF4444] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <span class="text-xs text-[#1F2937]">Name not verified — admin will check manually</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Photo thumbnails with inline preview --}}
                        <p class="text-xs font-semibold text-[#64748B] mb-2">Submitted photos</p>
                        <div class="grid grid-cols-3 gap-3">
                            {{-- Front --}}
                            <div>
                                <div class="rounded-lg border border-[#E2E8F0] overflow-hidden aspect-[4/3] cursor-pointer hover:border-[#2AA7A1] transition-colors"
                                     @click="previewImage = '{{ route('verifications.preview', [$verification, 'front']) }}'">
                                    <img src="{{ route('verifications.preview', [$verification, 'front']) }}"
                                         alt="ID front"
                                         class="w-full h-full object-cover">
                                </div>
                                <p class="text-xs text-[#64748B] mt-1.5 text-center">ID front</p>
                            </div>

                            {{-- Back --}}
                            @if ($verification->id_back)
                                <div>
                                    <div class="rounded-lg border border-[#E2E8F0] overflow-hidden aspect-[4/3] cursor-pointer hover:border-[#2AA7A1] transition-colors"
                                         @click="previewImage = '{{ route('verifications.preview', [$verification, 'back']) }}'">
                                        <img src="{{ route('verifications.preview', [$verification, 'back']) }}"
                                             alt="ID back"
                                             class="w-full h-full object-cover">
                                    </div>
                                    <p class="text-xs text-[#64748B] mt-1.5 text-center">ID back</p>
                                </div>
                            @else
                                <div>
                                    <div class="rounded-lg border border-dashed border-[#E2E8F0] bg-[#F7FCFC] aspect-[4/3] flex items-center justify-center">
                                        <span class="text-xs text-[#64748B]">N/A</span>
                                    </div>
                                    <p class="text-xs text-[#64748B] mt-1.5 text-center">ID back</p>
                                </div>
                            @endif

                            {{-- Selfie --}}
                            <div>
                                <div class="rounded-lg border border-[#E2E8F0] overflow-hidden aspect-[4/3] cursor-pointer hover:border-[#2AA7A1] transition-colors"
                                     @click="previewImage = '{{ route('verifications.preview', [$verification, 'selfie']) }}'">
                                    <img src="{{ route('verifications.preview', [$verification, 'selfie']) }}"
                                         alt="Selfie"
                                         class="w-full h-full object-cover">
                                </div>
                                <p class="text-xs text-[#64748B] mt-1.5 text-center">Selfie</p>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════ --}}
                    {{-- Business details card                      --}}
                    {{-- ═══════════════════════════════════════════ --}}
                    @if ($verification->business_name)
                        <div class="bg-white rounded-lg border border-[#E2E8F0] p-5 sm:p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-5 h-5 text-[#64748B]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                </svg>
                                <h2 class="text-base font-semibold text-[#1F2937]">Business details</h2>
                            </div>

                            <div class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-2 text-sm">
                                <span class="text-xs text-[#64748B]">Business name</span>
                                <span class="text-sm text-[#1F2937]">{{ $verification->business_name }}</span>

                                @if ($verification->description)
                                    <span class="text-xs text-[#64748B]">Description</span>
                                    <span class="text-sm text-[#1F2937]">{{ $verification->description }}</span>
                                @endif

                                <span class="text-xs text-[#64748B]">Contact</span>
                                <span class="text-sm text-[#1F2937]">{{ $verification->contact_number }}</span>

                                <span class="text-xs text-[#64748B]">Address</span>
                                <span class="text-sm text-[#1F2937]">{{ $verification->business_address }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Right column (1/3) — Timeline --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg border border-[#E2E8F0] p-5 sm:p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-[#64748B]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <h2 class="text-base font-semibold text-[#1F2937]">Timeline</h2>
                        </div>

                        <div class="flex flex-col pl-3 border-l-2 border-[#E2E8F0]">
                            {{-- Submitted --}}
                            <div class="relative pb-5 pl-5">
                                <div class="absolute -left-[7px] top-0.5 w-3 h-3 rounded-full bg-[#2AA7A1]"></div>
                                <p class="text-sm font-semibold text-[#1F2937]">Submitted</p>
                                <p class="text-xs text-[#64748B] mt-0.5">{{ $verification->submitted_at->format('M j, Y \a\t g:i A') }}</p>
                            </div>

                            @if ($verification->verification_status === 'Approved')
                                <div class="relative pl-5">
                                    <div class="absolute -left-[7px] top-0.5 w-3 h-3 rounded-full bg-[#22C55E]"></div>
                                    <p class="text-sm font-semibold text-[#1F2937]">Approved</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">{{ $verification->reviewed_at->format('M j, Y \a\t g:i A') }}</p>
                                </div>

                            @elseif ($verification->verification_status === 'Rejected')
                                <div class="relative pl-5">
                                    <div class="absolute -left-[7px] top-0.5 w-3 h-3 rounded-full bg-[#EF4444]"></div>
                                    <p class="text-sm font-semibold text-[#1F2937]">Not approved</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">{{ $verification->reviewed_at->format('M j, Y \a\t g:i A') }}</p>
                                    @if ($verification->admin_notes)
                                        <p class="text-xs text-[#64748B] mt-1">{{ $verification->admin_notes }}</p>
                                    @endif
                                </div>

                            @else
                                <div class="relative pl-5">
                                    <div class="absolute -left-[7px] top-0.5 w-3 h-3 rounded-full border-2 border-[#E2E8F0] bg-white"></div>
                                    <p class="text-sm font-semibold text-[#64748B]">Admin review</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">Waiting for review</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- Lightbox overlay                                   --}}
            {{-- ═══════════════════════════════════════════════════ --}}
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
    </div>
@endsection