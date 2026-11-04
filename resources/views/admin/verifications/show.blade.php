@extends('layouts.admin')

@section('page-title', 'Verification Review')

@section('content')
    <div class="max-w-5xl mx-auto" x-data="{ previewImage: null }">

        {{-- Back --}}
        <x-back-link href="{{ route('admin.verifications.index') }}" to="verifications" class="mb-5" />

        <x-page-header :title="$verification->user->first_name.' '.$verification->user->last_name" :subtitle="$verification->user->email">
            <x-slot:badge><x-admin.status :status="$verification->verification_status" /></x-slot:badge>
            <x-slot:actions>
                <p class="text-[13px] text-[#5B6A8E]">Submitted {{ $verification->submitted_at->format('M j, Y') }}</p>
            </x-slot:actions>
        </x-page-header>

        {{-- Status banners --}}
        @if ($verification->verification_status === 'Rejected' && $verification->admin_notes)
            <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/20 rounded-xl p-4 mb-4 flex gap-3">
                <svg class="w-5 h-5 text-[#DC2626] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="text-[12px] font-bold uppercase tracking-wider text-[#DC2626] mb-1">Rejection reason</p>
                    <p class="text-[13px] text-[#060D26]">{{ $verification->admin_notes }}</p>
                </div>
            </div>
        @elseif ($verification->verification_status === 'Approved')
            <div class="bg-[#22C55E]/[0.07] border border-[#22C55E]/20 rounded-xl p-4 mb-4 flex gap-3">
                <svg class="w-5 h-5 text-[#15803D] shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div>
                    <p class="text-[12px] font-bold uppercase tracking-wider text-[#15803D] mb-1">Approved</p>
                    <p class="text-[13px] text-[#060D26]">
                        Approved on {{ $verification->reviewed_at->format('M d, Y \a\t g:i A') }}
                        @if ($verification->reviewer)
                            by {{ $verification->reviewer->first_name }} {{ $verification->reviewer->last_name }}
                        @endif
                    </p>
                </div>
            </div>
        @endif

        {{-- Two-column layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

            {{-- Left column (3/5) — Photos --}}
            <div class="lg:col-span-3 space-y-4">

                {{-- The point of this page: is the person on the ID the person in the selfie? Whole images, side by side, same height. --}}
                <x-card>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-[15px] font-semibold text-[#060D26]">ID and selfie</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] text-[#5B6A8E]">{{ $verification->id_type }}</span>
                            <x-admin.status :status="$verification->liveness_passed ? 'Verified' : 'Pending'" :label="$verification->liveness_passed ? 'Liveness passed' : 'No liveness check'" />
                        </div>
                    </div>

                    @if ($verification->face_match_status)
                        @php
                            $fm = $verification->face_match_status;
                            $fmTone = match ($fm) {
                                'match' => 'bg-[#22C55E]/[0.07]',
                                'review' => 'bg-[#FBBF24]/[0.10]',
                                'mismatch' => 'bg-[#EF4444]/[0.07]',
                                'no_face' => 'bg-[#FBBF24]/[0.10]',
                                default => 'bg-[#F7F8FC]',
                            };
                            $fmText = match ($fm) {
                                'match' => 'Face match: <strong>'.rtrim(rtrim(number_format((float) $verification->face_match_score, 2), '0'), '.').'%</strong> similar to the selfie',
                                'review' => 'Faces look <strong>somewhat similar</strong> ('.rtrim(rtrim(number_format((float) $verification->face_match_score, 2), '0'), '.').'%). Compare the photos by eye',
                                'mismatch' => 'Faces <strong>do not match</strong> ('.rtrim(rtrim(number_format((float) $verification->face_match_score, 2), '0'), '.').'%). Compare the photos by eye',
                                'no_face' => 'No clear face found in the ID or the selfie. Compare the photos by eye',
                                default => 'Face match not checked. Compare the photos by eye',
                            };
                        @endphp
                        <div class="mb-4 flex items-center gap-2 px-3 py-2.5 rounded-lg text-[13px] {{ $fmTone }}">
                            <span class="text-[#060D26]">{!! $fmText !!}</span>
                        </div>
                    @endif

                    @unless ($verification->liveness_passed)
                        <p class="mb-4 rounded-lg bg-[#FBBF24]/[0.10] px-3 py-2.5 text-[13px] leading-relaxed text-[#060D26]">This selfie was captured without the automatic liveness check, because the applicant's device couldn't run it. Confirm the face matches the ID photo yourself before approving.</p>
                    @endunless

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <figure>
                            <button type="button" class="block w-full overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66] cursor-zoom-in"
                                @click="previewImage = '{{ route('verifications.preview', [$verification, 'front']) }}'" aria-label="Enlarge ID front">
                                <img src="{{ route('verifications.preview', [$verification, 'front']) }}" alt="ID front" class="h-64 w-full object-contain">
                            </button>
                            <figcaption class="mt-1.5 text-center text-[12px] font-semibold text-[#5B6A8E]">ID front</figcaption>
                        </figure>
                        <figure>
                            <button type="button" class="block w-full overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66] cursor-zoom-in"
                                @click="previewImage = '{{ route('verifications.preview', [$verification, 'selfie']) }}'" aria-label="Enlarge selfie">
                                <img src="{{ route('verifications.preview', [$verification, 'selfie']) }}" alt="Selfie" class="h-64 w-full object-contain">
                            </button>
                            <figcaption class="mt-1.5 text-center text-[12px] font-semibold text-[#5B6A8E]">Selfie</figcaption>
                        </figure>
                    </div>

                    @if ($verification->id_back)
                        <figure class="mt-3 sm:max-w-[50%]">
                            <button type="button" class="block w-full overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66] cursor-zoom-in"
                                @click="previewImage = '{{ route('verifications.preview', [$verification, 'back']) }}'" aria-label="Enlarge ID back">
                                <img src="{{ route('verifications.preview', [$verification, 'back']) }}" alt="ID back" class="h-40 w-full object-contain">
                            </button>
                            <figcaption class="mt-1.5 text-center text-[12px] font-semibold text-[#5B6A8E]">ID back</figcaption>
                        </figure>
                    @endif
                </x-card>
            </div>

            {{-- Right column (2/5) --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Admin action (TOP — most important) --}}
                @if ($verification->verification_status === 'Pending')
                    <x-card x-data="{ showReject: false }">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-4 h-4 text-[#5B6A8E]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Z" />
                            </svg>
                            <h2 class="text-[14px] font-semibold text-[#060D26]">Admin action</h2>
                        </div>

                        <div class="flex gap-2 mb-3">
                            <form method="POST" action="{{ route('admin.verifications.approve', $verification) }}"
                                data-confirm="Approve this landlord application?"
                                data-confirm-type="confirm"
                                data-confirm-message="This will grant the applicant the Landlord role."
                                data-confirm-button="Approve" class="flex-1">
                                @csrf
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-lg bg-[#15803D] hover:brightness-95 text-white text-[13px] font-bold transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Approve
                                </button>
                            </form>

                            <button type="button" @click="showReject = !showReject"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-10 rounded-lg border border-[#EF4444]/30 text-[#DC2626] hover:bg-[#EF4444]/[0.06] text-[13px] font-bold transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Reject
                            </button>
                        </div>

                        <div x-show="showReject" x-cloak x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0" class="border-t border-[#E2E4EC] pt-3">
                            <form method="POST" action="{{ route('admin.verifications.reject', $verification) }}"
                                data-confirm="Reject this application?"
                                data-confirm-type="warning"
                                data-confirm-message="The applicant will see your reason. This cannot be undone."
                                data-confirm-button="Reject">
                                @csrf
                                <label for="admin_notes"
                                    class="block text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-1.5">
                                    Reason for rejection
                                </label>
                                <textarea name="admin_notes" id="admin_notes" rows="3" required
                                    class="w-full rounded-lg border border-[#E2E4EC] px-3 py-2.5 text-[13px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all resize-none"
                                    placeholder="Explain why — the applicant will see this."></textarea>
                                @error('admin_notes')
                                    <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                                @enderror
                                <div class="mt-2.5 flex gap-2">
                                    <button type="submit"
                                        class="h-9 px-4 rounded-lg bg-[#EF4444] hover:bg-[#EF4444] text-white text-[12px] font-bold transition-colors">
                                        Confirm rejection
                                    </button>
                                    <button type="button" @click="showReject = false"
                                        class="h-9 px-4 rounded-lg border border-[#E2E4EC] text-[12px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </x-card>
                @endif

                {{-- OCR results --}}
                <x-card>
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-[#5B6A8E]" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5" />
                        </svg>
                        <h2 class="text-[14px] font-semibold text-[#060D26]">OCR results</h2>
                    </div>

                    {{-- Face on the ID against the live selfie (App\Services\FaceMatch). A signal, never a decision: bad light
                         or an old ID photo can fail a real person, and a match does not prove the ID is genuine. --}}

                    @if ($verification->ocr_status)
                        <div class="space-y-1.5">
                            <div
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12px]
                                    {{ $verification->ocr_status === 'pass' ? 'bg-[#22C55E]/[0.07]' : (in_array($verification->ocr_status, ['partial', 'unreadable'], true) ? 'bg-[#FBBF24]/[0.10]' : 'bg-[#EF4444]/[0.07]') }}">
                                @if ($verification->ocr_status === 'pass')
                                    <svg class="w-3.5 h-3.5 text-[#15803D] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @elseif (in_array($verification->ocr_status, ['partial', 'unreadable'], true))
                                    <svg class="w-3.5 h-3.5 text-[#FBBF24] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                @else
                                    <svg class="w-3.5 h-3.5 text-[#DC2626] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @endif
                                <span class="text-[#060D26]">
                                    @if ($verification->ocr_status === 'pass')
                                        Name: <strong>{{ $verification->ocr_name }}</strong> ({{ $verification->ocr_confidence }}%)
                                    @elseif ($verification->ocr_status === 'partial')
                                        Partial: <strong>{{ $verification->ocr_name }}</strong>
                                        ({{ $verification->ocr_confidence }}%)
                                    @elseif ($verification->ocr_status === 'unreadable')
                                        Text could not be read automatically. Check the ID by eye
                                    @else
                                        Name not found on ID
                                    @endif
                                </span>
                            </div>

                            @if ($verification->id_number)
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[#F7F8FC] text-[12px]">
                                    <svg class="w-3.5 h-3.5 text-[#5B6A8E] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5" />
                                    </svg>
                                    <span class="text-[#060D26]">ID: <strong>{{ $verification->id_number }}</strong></span>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-[12px] text-[#5B6A8E]">OCR data not available — review ID photos manually.</p>
                    @endif
                </x-card>

                {{-- ID details --}}
                <x-card>
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-[#5B6A8E]" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <h2 class="text-[14px] font-semibold text-[#060D26]">ID details</h2>
                    </div>

                    <div class="space-y-2.5 text-[13px]">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">ID type</p>
                            <p class="font-semibold text-[#060D26] mt-0.5">{{ $verification->id_type }}</p>
                        </div>
                        @if ($verification->ocr_name)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Name on ID</p>
                                <p class="font-semibold text-[#060D26] mt-0.5">{{ $verification->ocr_name }}</p>
                            </div>
                        @endif
                        @if ($verification->id_number)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">ID number</p>
                                <p class="font-semibold text-[#060D26] mt-0.5">{{ $verification->id_number }}</p>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Business details --}}
                @if ($verification->business_name)
                    <x-card>
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-[#5B6A8E]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                            </svg>
                            <h2 class="text-[14px] font-semibold text-[#060D26]">Business details</h2>
                        </div>

                        <div class="space-y-2.5 text-[13px]">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Business name</p>
                                <p class="font-semibold text-[#060D26] mt-0.5">{{ $verification->business_name }}</p>
                            </div>
                            @if ($verification->contact_number)
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Contact</p>
                                    <p class="font-semibold text-[#060D26] mt-0.5">{{ $verification->contact_number }}</p>
                                </div>
                            @endif
                            @if ($verification->business_address)
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Address</p>
                                    <p class="font-semibold text-[#060D26] mt-0.5">{{ $verification->business_address }}</p>
                                </div>
                            @endif
                            @if ($verification->description)
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Description</p>
                                    <p class="text-[#5B6A8E] leading-relaxed mt-0.5">{{ $verification->description }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>
                @endif
            </div>
        </div>

        {{-- Lightbox --}}
        <div x-show="previewImage" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="previewImage = null" @keydown.escape.window="previewImage = null"
            class="fixed inset-0 z-[999] flex items-center justify-center bg-black/70 p-4 cursor-pointer"
            style="display: none;">
            <button type="button" @click="previewImage = null"
                class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="previewImage" alt="Document preview" @click.stop
                class="max-w-full max-h-[85vh] rounded-lg cursor-default">
        </div>

    </div>
@endsection