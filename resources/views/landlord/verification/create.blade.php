@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="min-h-screen bg-[#F7FCFC] py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto"
            x-data="verificationWizard({ ocrCheckUrl: '{{ route('landlord.verification.ocrCheck') }}', csrfToken: '{{ csrf_token() }}' })"
            x-init="init()">

            {{-- Rejection Banner --}}
            @if($verification && $verification->verification_status === 'Rejected')
                <div class="mb-6 rounded-xl border border-[#EF4444]/30 bg-red-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-[#EF4444] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-[#1F2937]">Your previous application was not approved</p>
                            @if($verification->admin_notes)
                                <p class="mt-1 text-sm text-[#64748B]">Reason: {{ $verification->admin_notes }}</p>
                            @endif
                            <p class="mt-1 text-sm text-[#64748B]">You may re-submit with corrected information below.</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Progress Bar (steps 1-5 only, hidden on intro) --}}
            <div x-show="step > 0" x-transition:enter.duration.200ms class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <button type="button" @click="prevStep()" x-show="step > 1"
                        class="p-1.5 rounded-lg text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="step <= 1" class="w-8"></div>
                    <div class="flex gap-1.5 flex-1 max-w-[200px] mx-auto">
                        <template x-for="i in 5" :key="i">
                            <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                                :class="step >= i ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]'"></div>
                        </template>
                    </div>
                    <div class="w-8"></div>
                </div>
            </div>

            {{-- Validation Errors --}}
            @if($errors->any())
                <div class="mb-6 rounded-xl border border-[#EF4444]/30 bg-red-50 p-4">
                    <ul class="list-disc list-inside text-sm text-[#EF4444] space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('landlord.verification.store') }}" enctype="multipart/form-data"
                x-ref="form" @submit.prevent="submitForm()">
                @csrf

                <input type="hidden" name="id_type" :value="idType">
                <input type="hidden" name="id_image" :value="idImageBase64">
                <input type="hidden" name="id_back" :value="idBackBase64">
                <input type="hidden" name="selfie" :value="selfieBase64">

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 0 — Welcome / Intro --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 0" x-transition:enter.duration.200ms>
                    <div class="text-center pt-8 pb-6">
                        <div class="w-24 h-24 rounded-full bg-[#EEF8F8] mx-auto mb-6 flex items-center justify-center">
                            <svg class="w-12 h-12 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-[#156F8C] mb-2">Verify your identity</h1>
                        <p class="text-sm text-[#64748B] max-w-sm mx-auto">Complete these steps to start listing properties
                            on AbangananHub.</p>
                    </div>

                    <div class="space-y-3 mb-8">
                        {{-- Checklist item: Government ID --}}
                        <div class="flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0]">
                            <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#1F2937]">Government ID</p>
                                <p class="text-xs text-[#64748B]">Photo of front and back</p>
                            </div>
                        </div>

                        {{-- Checklist item: Face verification --}}
                        <div class="flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0]">
                            <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#1F2937]">Face verification</p>
                                <p class="text-xs text-[#64748B]">Selfie to confirm your identity</p>
                            </div>
                        </div>

                        {{-- Checklist item: Business details --}}
                        <div class="flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0]">
                            <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#1F2937]">Business details</p>
                                <p class="text-xs text-[#64748B]">Name, address, contact info</p>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="nextStep()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150">
                        Get started
                    </button>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 1 — Select ID Type --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 1" x-transition:enter.duration.200ms>
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] mx-auto mb-4 flex items-center justify-center">
                            <svg class="w-8 h-8 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Select your government ID</h2>
                        <p class="text-sm text-[#64748B]">Choose a valid, unexpired Philippine government ID.</p>
                    </div>

                    <div class="space-y-2 mb-6">
                        <template x-for="id in idTypes" :key="id">
                            <label
                                class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors duration-150"
                                :class="idType === id ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#E2E8F0] bg-white hover:bg-[#F7FCFC]'">
                                <input type="radio" :value="id" x-model="idType"
                                    class="h-4 w-4 text-[#2AA7A1] border-[#E2E8F0] focus:ring-[#2AA7A1]">
                                <span class="text-sm text-[#1F2937]" x-text="id"></span>
                            </label>
                        </template>
                    </div>

                    <button type="button" @click="nextStep()" :disabled="!idType"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                        :class="idType ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                        Continue
                    </button>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 2 — Capture Government ID --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 2" x-transition:enter.duration.200ms>

                    {{-- ── Method chooser ──────────────────────── --}}
                    <div x-show="idCapturePhase === 'choose'">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] mx-auto mb-4 flex items-center justify-center">
                                <svg class="w-8 h-8 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                                </svg>
                            </div>
                            <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Upload your <span x-text="idType"></span>
                            </h2>
                            <p class="text-sm text-[#64748B]">Take a clear photo or upload from your gallery.</p>
                        </div>

                        <div class="space-y-3 mb-6">
                            <button type="button" @click="chooseIdMethod('camera')"
                                class="w-full flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0] hover:border-[#2AA7A1] hover:bg-[#F7FCFC] transition-colors duration-150 text-left">
                                <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-[#156F8C]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1F2937]">Take a photo</p>
                                    <p class="text-xs text-[#64748B]">Use your camera to capture</p>
                                </div>
                                <svg class="w-5 h-5 text-[#64748B] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>

                            <button type="button" @click="chooseIdMethod('upload')"
                                class="w-full flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0] hover:border-[#2AA7A1] hover:bg-[#F7FCFC] transition-colors duration-150 text-left">
                                <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-[#156F8C]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1F2937]">Upload from gallery</p>
                                    <p class="text-xs text-[#64748B]">Select an existing photo</p>
                                </div>
                                <svg class="w-5 h-5 text-[#64748B] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-3.5 rounded-xl bg-[#EEF8F8]">
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-[#2AA7A1] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                <p class="text-xs text-[#64748B] leading-relaxed">Good lighting, flat surface, all 4 corners
                                    visible. Text must be readable.</p>
                            </div>
                        </div>
                    </div>

                    {{-- ── Active capture / upload area ─────────── --}}
                    <div x-show="idCapturePhase !== 'choose'">
                        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">

                            <template x-if="cameraError">
                                <div class="mb-4 rounded-xl border border-[#EF4444]/30 bg-red-50 p-3">
                                    <p class="text-sm text-[#EF4444]" x-text="cameraError"></p>
                                </div>
                            </template>

                            {{-- Thumbnail row --}}
                            <div x-show="idImageBase64 && idCapturePhase !== 'capture-front'" class="mb-4">
                                <div class="grid gap-4" :class="needsBack ? 'grid-cols-2' : 'grid-cols-1 max-w-xs'">
                                    <div class="relative">
                                        <div class="rounded-xl overflow-hidden border-2 border-[#22C55E] cursor-pointer"
                                            @click="openPreview(idImageBase64)">
                                            <img :src="idImageBase64" alt="Front" class="w-full aspect-[4/3] object-cover">
                                        </div>
                                        <div
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-[#22C55E] flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </div>
                                        <div class="flex items-center justify-between mt-1.5">
                                            <span class="text-xs text-[#64748B]"
                                                x-text="needsBack ? 'Front' : 'ID Photo'"></span>
                                            <button type="button" @click="retakePhoto('id')"
                                                class="text-xs text-[#2AA7A1] hover:underline">Retake</button>
                                        </div>
                                    </div>
                                    <div x-show="needsBack" class="relative">
                                        <template x-if="idBackBase64">
                                            <div>
                                                <div class="rounded-xl overflow-hidden border-2 border-[#22C55E] cursor-pointer"
                                                    @click="openPreview(idBackBase64)">
                                                    <img :src="idBackBase64" alt="Back"
                                                        class="w-full aspect-[4/3] object-cover">
                                                </div>
                                                <div
                                                    class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-[#22C55E] flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg"
                                                        fill="none" viewBox="0 0 24 24" stroke-width="3"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                </div>
                                                <div class="flex items-center justify-between mt-1.5">
                                                    <span class="text-xs text-[#64748B]">Back</span>
                                                    <button type="button" @click="retakePhoto('idBack')"
                                                        class="text-xs text-[#2AA7A1] hover:underline">Retake</button>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!idBackBase64">
                                            <div
                                                class="rounded-xl border-2 border-dashed border-[#E2E8F0] aspect-[4/3] flex items-center justify-center">
                                                <span class="text-xs text-[#64748B]">Back</span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- Duplicate side warning --}}
                            <div x-show="duplicateSideWarning" class="mb-4">
                                <div class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50">
                                    <svg class="w-4 h-4 text-[#FBBF24] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-[#1F2937]">These photos look the same</p>
                                        <p class="text-xs text-[#64748B] mt-1">Flip your ID over and retake the back photo.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- OCR results --}}
                            <div x-show="idCapturePhase === 'done' && (ocrLoading || ocrResult || ocrError)" class="mb-4">
                                <div x-show="ocrLoading" class="flex items-center gap-2 py-4 justify-center">
                                    <svg class="animate-spin h-4 w-4 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span class="text-xs text-[#64748B]">Checking your ID...</span>
                                </div>

                                <template x-if="!ocrLoading && ocrResult">
                                    <div>
                                        <div class="border-t border-[#E2E8F0] pt-4 mb-4">
                                            <p class="text-xs font-semibold text-[#64748B] mb-3">What we found on your ID
                                            </p>
                                            <div class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-1.5 text-sm"
                                                x-data="{ names: parseNames(ocrResult?.extracted) }">
                                                <span class="text-xs text-[#64748B]">Last Name</span>
                                                <span class="text-sm font-medium text-[#1F2937]"
                                                    x-text="names.lastName || '-----'"></span>
                                                <span class="text-xs text-[#64748B]">First Name</span>
                                                <span class="text-sm font-medium text-[#1F2937]"
                                                    x-text="names.firstName || '-----'"></span>
                                                <span class="text-xs text-[#64748B]">Middle Name</span>
                                                <span class="text-sm text-[#1F2937]"
                                                    x-text="names.middleName || '-----'"></span>
                                                <span class="text-xs text-[#64748B]">ID Number</span>
                                                <span class="text-sm font-medium text-[#1F2937]"
                                                    x-text="ocrResult?.id_number || '-----'"></span>
                                                <span class="text-xs text-[#64748B]">ID Type</span>
                                                <span class="text-sm text-[#1F2937]" x-text="idType"></span>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-start gap-2.5 p-3 rounded-xl"
                                                :class="ocrResult?.status === 'pass' ? 'bg-green-50' : (ocrResult?.status === 'partial' ? 'bg-amber-50' : 'bg-red-50')">
                                                <template x-if="ocrResult?.status === 'pass'">
                                                    <svg class="w-4 h-4 text-[#22C55E] mt-0.5 shrink-0"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </template>
                                                <template x-if="ocrResult?.status !== 'pass'">
                                                    <svg class="w-4 h-4 mt-0.5 shrink-0"
                                                        :class="ocrResult?.status === 'partial' ? 'text-[#FBBF24]' : 'text-[#EF4444]'"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                                    </svg>
                                                </template>
                                                <div>
                                                    <p class="text-sm font-semibold text-[#1F2937]"
                                                        x-text="ocrResult?.status === 'pass' ? 'We found your name on this ID' : (ocrResult?.status === 'partial' ? 'Partial name match' : 'Name not found on this ID')">
                                                    </p>
                                                    <p class="text-xs text-[#64748B] mt-1"
                                                        x-show="ocrResult?.status === 'partial'">Close but not exact. Try
                                                        retaking with better lighting, or continue — our team will verify.
                                                    </p>
                                                    <p class="text-xs text-[#64748B] mt-1"
                                                        x-show="ocrResult?.status === 'fail'">Make sure text is clear and
                                                        well-lit. You can retake or continue — our team will check manually.
                                                    </p>
                                                </div>
                                            </div>
                                            <div x-show="ocrResult?.type_match === 'match'"
                                                class="flex items-center gap-2 p-3 rounded-xl bg-green-50">
                                                <svg class="w-4 h-4 text-[#22C55E] shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                <span class="text-sm text-[#1F2937]">This looks like a <span
                                                        class="font-medium" x-text="idType"></span></span>
                                            </div>
                                            <div x-show="ocrResult?.type_match === 'mismatch'"
                                                class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50">
                                                <svg class="w-4 h-4 text-[#FBBF24] mt-0.5 shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                                </svg>
                                                <div>
                                                    <p class="text-sm font-semibold text-[#1F2937]">This doesn't look like a
                                                        <span x-text="idType"></span></p>
                                                    <p class="text-xs text-[#64748B] mt-1">Make sure you photographed the
                                                        correct ID type.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="ocrError" class="px-3 py-2 rounded-xl bg-amber-50">
                                    <p class="text-xs text-[#1F2937]">OCR unavailable — admin will review your ID manually.
                                    </p>
                                </div>
                            </div>

                            {{-- CAMERA: Front --}}
                            <div x-show="idCapturePhase === 'capture-front' && idCaptureMethod === 'camera'"
                                class="relative">
                                <p class="text-xs font-semibold text-[#1F2937] mb-2"
                                    x-text="needsBack ? 'Front of ID' : 'ID data page'"></p>
                                <div class="relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
                                    <video x-ref="videoId" autoplay playsinline class="w-full h-full object-cover"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="w-[85%] h-[60%] border-2 border-white/60 rounded-lg"></div>
                                    </div>
                                </div>
                                <canvas x-ref="canvasId" class="hidden"></canvas>
                                <div class="mt-3 flex justify-center">
                                    <button type="button" @click="capturePhoto('id')" :disabled="!cameraActive"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                                        @include('components.icons.camera')
                                        <span x-text="needsBack ? 'Capture front' : 'Capture ID'"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- CAMERA: Back --}}
                            <div x-show="idCapturePhase === 'capture-back' && idCaptureMethod === 'camera'"
                                class="relative">
                                <p class="text-xs font-semibold text-[#1F2937] mb-2">Flip your ID and capture the back</p>
                                <div class="relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
                                    <video x-ref="videoIdBack" autoplay playsinline
                                        class="w-full h-full object-cover"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="w-[85%] h-[60%] border-2 border-white/60 rounded-lg"></div>
                                    </div>
                                </div>
                                <canvas x-ref="canvasIdBack" class="hidden"></canvas>
                                <div class="mt-3 flex justify-center">
                                    <button type="button" @click="capturePhoto('idBack')" :disabled="!cameraActive"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                                        @include('components.icons.camera')
                                        Capture back
                                    </button>
                                </div>
                            </div>

                            {{-- UPLOAD: Front --}}
                            <div x-show="idCapturePhase === 'capture-front' && idCaptureMethod === 'upload'">
                                <p class="text-xs font-semibold text-[#1F2937] mb-2"
                                    x-text="needsBack ? 'Upload front of ID' : 'Upload ID photo'"></p>
                                <label
                                    class="flex flex-col items-center justify-center w-full aspect-[4/3] rounded-xl border-2 border-dashed border-[#E2E8F0] hover:border-[#2AA7A1] bg-[#F7FCFC] cursor-pointer transition-colors duration-150">
                                    <svg class="w-10 h-10 text-[#64748B] mb-2" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                    <span class="text-sm text-[#64748B]">Tap to select a photo</span>
                                    <span class="text-xs text-[#64748B] mt-1">JPEG or PNG, max 10MB</span>
                                    <input type="file" x-ref="fileInputFront" accept="image/jpeg,image/png"
                                        capture="environment" class="hidden" @change="handleFileUpload('id', $event)">
                                </label>
                            </div>

                            {{-- UPLOAD: Back --}}
                            <div x-show="idCapturePhase === 'capture-back' && idCaptureMethod === 'upload'">
                                <p class="text-xs font-semibold text-[#1F2937] mb-2">Upload back of ID</p>
                                <label
                                    class="flex flex-col items-center justify-center w-full aspect-[4/3] rounded-xl border-2 border-dashed border-[#E2E8F0] hover:border-[#2AA7A1] bg-[#F7FCFC] cursor-pointer transition-colors duration-150">
                                    <svg class="w-10 h-10 text-[#64748B] mb-2" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                    <span class="text-sm text-[#64748B]">Tap to select a photo</span>
                                    <span class="text-xs text-[#64748B] mt-1">JPEG or PNG, max 10MB</span>
                                    <input type="file" x-ref="fileInputBack" accept="image/jpeg,image/png"
                                        capture="environment" class="hidden" @change="handleFileUpload('idBack', $event)">
                                </label>
                            </div>

                            {{-- Switch method link --}}
                            <div x-show="idCapturePhase !== 'done'" class="mt-3 text-center">
                                <button type="button" @click="switchIdMethod()"
                                    class="text-xs text-[#64748B] hover:text-[#2AA7A1] underline transition-colors">
                                    <span
                                        x-text="idCaptureMethod === 'camera' ? 'Upload a photo instead' : 'Use camera instead'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="button" @click="nextStep()" :disabled="!idCaptureComplete"
                                class="w-full py-3 rounded-xl text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="idCaptureComplete ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                                Continue
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 3 — Face Verification / Selfie --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 3" x-transition:enter.duration.200ms>

                    {{-- ── Method chooser ──────────────────────── --}}
                    <div x-show="selfieCapturePhase === 'choose' && !selfieBase64">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] mx-auto mb-4 flex items-center justify-center">
                                <svg class="w-8 h-8 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Face verification</h2>
                            <p class="text-sm text-[#64748B]">We'll compare this with the photo on your ID.</p>
                        </div>

                        <div class="space-y-3 mb-6">
                            <button type="button" @click="chooseSelfieMethod('camera')"
                                class="w-full flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0] hover:border-[#2AA7A1] hover:bg-[#F7FCFC] transition-colors duration-150 text-left">
                                <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-[#156F8C]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1F2937]">Take a selfie</p>
                                    <p class="text-xs text-[#64748B]">Use your front camera</p>
                                </div>
                                <svg class="w-5 h-5 text-[#64748B] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>

                            <button type="button" @click="chooseSelfieMethod('upload')"
                                class="w-full flex items-center gap-4 p-4 bg-white rounded-xl border border-[#E2E8F0] hover:border-[#2AA7A1] hover:bg-[#F7FCFC] transition-colors duration-150 text-left">
                                <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-[#156F8C]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1F2937]">Upload a selfie</p>
                                    <p class="text-xs text-[#64748B]">Select from your gallery</p>
                                </div>
                                <svg class="w-5 h-5 text-[#64748B] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-3.5 rounded-xl bg-[#EEF8F8]">
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-[#2AA7A1] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                <p class="text-xs text-[#64748B] leading-relaxed">Face the camera directly, no sunglasses or
                                    hats, plain well-lit background.</p>
                            </div>
                        </div>
                    </div>

                    {{-- ── Camera selfie capture ───────────────── --}}
                    <div x-show="selfieCapturePhase === 'camera' && !selfieBase64">
                        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                            <template x-if="cameraError">
                                <div class="mb-4 rounded-xl border border-[#EF4444]/30 bg-red-50 p-3">
                                    <p class="text-sm text-[#EF4444]" x-text="cameraError"></p>
                                </div>
                            </template>

                            {{-- Camera selfie --}}
                            <div x-show="selfieCaptureMethod === 'camera'" class="relative">
                                <div class="relative rounded-xl overflow-hidden bg-black aspect-[3/4] max-w-sm mx-auto">
                                    <video x-ref="videoSelfie" autoplay playsinline class="w-full h-full object-cover"
                                        style="transform: scaleX(-1)"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="w-[55%] h-[50%] border-2 border-white/60 rounded-full"></div>
                                    </div>
                                </div>
                                <canvas x-ref="canvasSelfie" class="hidden"></canvas>
                                <div class="mt-4 flex justify-center">
                                    <button type="button" @click="capturePhoto('selfie')" :disabled="!cameraActive"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                                        @include('components.icons.camera')
                                        Take selfie
                                    </button>
                                </div>
                            </div>

                            {{-- Upload selfie --}}
                            <div x-show="selfieCaptureMethod === 'upload'">
                                <label
                                    class="flex flex-col items-center justify-center w-full aspect-[3/4] max-w-sm mx-auto rounded-xl border-2 border-dashed border-[#E2E8F0] hover:border-[#2AA7A1] bg-[#F7FCFC] cursor-pointer transition-colors duration-150">
                                    <svg class="w-10 h-10 text-[#64748B] mb-2" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                    <span class="text-sm text-[#64748B]">Tap to select a selfie</span>
                                    <span class="text-xs text-[#64748B] mt-1">JPEG or PNG, max 10MB</span>
                                    <input type="file" x-ref="fileInputSelfie" accept="image/jpeg,image/png" capture="user"
                                        class="hidden" @change="handleFileUpload('selfie', $event)">
                                </label>
                            </div>

                            <div class="mt-3 text-center">
                                <button type="button" @click="switchSelfieMethod()"
                                    class="text-xs text-[#64748B] hover:text-[#2AA7A1] underline transition-colors">
                                    <span
                                        x-text="selfieCaptureMethod === 'camera' ? 'Upload a photo instead' : 'Use camera instead'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── Selfie preview (both modes) ─────────── --}}
                    <div x-show="selfieBase64">
                        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                            <div class="rounded-xl overflow-hidden border border-[#E2E8F0] max-w-sm mx-auto">
                                <img :src="selfieBase64" alt="Captured selfie" class="w-full">
                            </div>

                            <div x-show="faceCheckDone && !faceDetected" class="mt-3 max-w-sm mx-auto">
                                <div class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50">
                                    <svg class="w-4 h-4 text-[#FBBF24] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-[#1F2937]">We can't see your face clearly</p>
                                        <p class="text-xs text-[#64748B] mt-1">Face the camera directly with good lighting.
                                            You can retake or continue — our team will check.</p>
                                    </div>
                                </div>
                            </div>

                            <div x-show="faceCheckDone && faceDetected" class="mt-3 max-w-sm mx-auto">
                                <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-green-50">
                                    <svg class="w-4 h-4 text-[#22C55E] shrink-0" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span class="text-sm text-[#1F2937]">Looking good!</span>
                                </div>
                            </div>

                            <div class="mt-3 flex justify-center">
                                <button type="button" @click="retakePhoto('selfie')"
                                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold text-[#1F2937] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                                    @include('components.icons.refresh')
                                    Retake
                                </button>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="button" @click="nextStep()" :disabled="!selfieBase64"
                                class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all duration-150">
                                Continue
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 4 — Business Details --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 4" x-transition:enter.duration.200ms>
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] mx-auto mb-4 flex items-center justify-center">
                            <svg class="w-8 h-8 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Business details</h2>
                        <p class="text-sm text-[#64748B]">Tell us about your rental business on AbangananHub.</p>
                    </div>

                    <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                        <div class="space-y-4">
                            <div>
                                <label for="business_name" class="block text-sm font-medium text-[#1F2937] mb-1">Business
                                    name <span class="text-[#EF4444]">*</span></label>
                                <input type="text" id="business_name" name="business_name" x-model="businessName"
                                    class="w-full rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors"
                                    placeholder="e.g. Sunrise Boarding House">
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-[#1F2937] mb-1">Description
                                    <span class="text-[#64748B] text-xs font-normal">(optional)</span></label>
                                <textarea id="description" name="description" rows="3" x-model="description"
                                    maxlength="1000"
                                    class="w-full rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors resize-none"
                                    placeholder="Brief description of your rental business"></textarea>
                                <p class="mt-1 text-xs text-[#64748B]" x-text="(description?.length || 0) + '/1000'"></p>
                            </div>
                            <div>
                                <label for="logo" class="block text-sm font-medium text-[#1F2937] mb-1">Business logo <span
                                        class="text-[#64748B] text-xs font-normal">(optional, max 2MB)</span></label>
                                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png"
                                    class="w-full text-sm text-[#64748B] file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-[#EEF8F8] file:text-[#156F8C] hover:file:bg-[#ddf1f1] file:cursor-pointer file:transition-colors">
                            </div>
                            <div>
                                <label for="contact_number" class="block text-sm font-medium text-[#1F2937] mb-1">Contact
                                    number <span class="text-[#EF4444]">*</span></label>
                                <input type="text" id="contact_number" name="contact_number" x-model="contactNumber"
                                    class="w-full rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors"
                                    placeholder="09XX XXX XXXX">
                            </div>
                            <div>
                                <label for="business_address" class="block text-sm font-medium text-[#1F2937] mb-1">Business
                                    address <span class="text-[#EF4444]">*</span></label>
                                <input type="text" id="business_address" name="business_address" x-model="businessAddress"
                                    class="w-full rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors"
                                    placeholder="Full address of your rental property/business">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="button" @click="nextStep()"
                            :disabled="!businessName || !contactNumber || !businessAddress"
                            class="w-full py-3 rounded-xl text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="(businessName && contactNumber && businessAddress) ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                            Continue
                        </button>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 5 — Review & Submit --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 5" x-transition:enter.duration.200ms>
                    <div class="text-center mb-6">
                        <h2 class="text-lg font-semibold text-[#1F2937]">Review your application</h2>
                        <p class="text-sm text-[#64748B]">Make sure everything looks correct before submitting.</p>
                    </div>

                    <div class="space-y-4">
                        {{-- Verification checks --}}
                        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                            <p class="text-xs font-semibold text-[#64748B] mb-3">Verification checks</p>
                            <div x-show="ocrResult" class="space-y-2">
                                <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl"
                                    :class="ocrResult?.status === 'pass' ? 'bg-green-50' : (ocrResult?.status === 'partial' ? 'bg-amber-50' : 'bg-red-50')">
                                    <template x-if="ocrResult?.status === 'pass'">
                                        <svg class="w-4 h-4 text-[#22C55E] shrink-0" xmlns="http://www.w3.org/2000/svg"
                                            fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </template>
                                    <template x-if="ocrResult?.status !== 'pass'">
                                        <svg class="w-4 h-4 shrink-0"
                                            :class="ocrResult?.status === 'partial' ? 'text-[#FBBF24]' : 'text-[#EF4444]'"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                    </template>
                                    <span class="text-xs text-[#1F2937]"
                                        x-text="ocrResult?.status === 'pass' ? 'Name found on ID' : (ocrResult?.status === 'partial' ? 'Partial name match — team will verify' : 'Name not verified — team will check')"></span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl"
                                    :class="ocrResult?.type_match === 'match' ? 'bg-green-50' : 'bg-amber-50'">
                                    <svg class="w-4 h-4 shrink-0"
                                        :class="ocrResult?.type_match === 'match' ? 'text-[#22C55E]' : 'text-[#FBBF24]'"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor">
                                        <template x-if="ocrResult?.type_match === 'match'">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </template>
                                        <template x-if="ocrResult?.type_match !== 'match'">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </template>
                                    </svg>
                                    <span class="text-xs text-[#1F2937]"
                                        x-text="ocrResult?.type_match === 'match' ? 'Looks like a ' + idType : 'Document type not confirmed — team will check'"></span>
                                </div>
                                <div x-show="ocrResult?.id_number"
                                    class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[#EEF8F8]">
                                    <svg class="w-4 h-4 text-[#2AA7A1] shrink-0" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                                    </svg>
                                    <span class="text-xs text-[#64748B]">ID Number: <span class="font-medium text-[#1F2937]"
                                            x-text="ocrResult?.id_number"></span></span>
                                </div>
                                <div x-show="faceCheckDone" class="flex items-center gap-2 px-3 py-2.5 rounded-xl"
                                    :class="faceDetected ? 'bg-green-50' : 'bg-amber-50'">
                                    <svg class="w-4 h-4 shrink-0"
                                        :class="faceDetected ? 'text-[#22C55E]' : 'text-[#FBBF24]'"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor">
                                        <template x-if="faceDetected">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </template>
                                        <template x-if="!faceDetected">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </template>
                                    </svg>
                                    <span class="text-xs text-[#1F2937]"
                                        x-text="faceDetected ? 'Selfie looks good' : 'Face not clearly visible — team will check'"></span>
                                </div>
                            </div>
                            <div x-show="ocrError" class="px-3 py-2.5 rounded-xl bg-amber-50">
                                <p class="text-xs text-[#1F2937]">OCR unavailable — admin will review your ID manually.</p>
                            </div>
                        </div>

                        {{-- Photos --}}
                        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                            <p class="text-xs font-semibold text-[#64748B] mb-3">Your photos</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" :class="needsBack ? 'lg:grid-cols-3' : ''">
                                <div>
                                    <p class="text-xs text-[#64748B] mb-1.5"
                                        x-text="needsBack ? 'ID Front' : 'Government ID'"></p>
                                    <div class="rounded-xl overflow-hidden border border-[#E2E8F0] cursor-pointer"
                                        @click="openPreview(idImageBase64)">
                                        <img :src="idImageBase64" alt="Front of ID" class="w-full">
                                    </div>
                                </div>
                                <div x-show="needsBack && idBackBase64">
                                    <p class="text-xs text-[#64748B] mb-1.5">ID Back</p>
                                    <div class="rounded-xl overflow-hidden border border-[#E2E8F0] cursor-pointer"
                                        @click="openPreview(idBackBase64)">
                                        <img :src="idBackBase64" alt="Back of ID" class="w-full">
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs text-[#64748B] mb-1.5">Selfie</p>
                                    <div class="rounded-xl overflow-hidden border border-[#E2E8F0] cursor-pointer"
                                        @click="openPreview(selfieBase64)">
                                        <img :src="selfieBase64" alt="Selfie" class="w-full">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Business details --}}
                        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                            <p class="text-xs font-semibold text-[#64748B] mb-3">Business details</p>
                            <dl class="space-y-3">
                                <div class="flex flex-col sm:flex-row sm:gap-4">
                                    <dt class="text-xs font-medium text-[#64748B] sm:w-32 shrink-0">ID Type</dt>
                                    <dd class="text-sm text-[#1F2937]" x-text="idType"></dd>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:gap-4">
                                    <dt class="text-xs font-medium text-[#64748B] sm:w-32 shrink-0">Business Name</dt>
                                    <dd class="text-sm text-[#1F2937]" x-text="businessName"></dd>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:gap-4" x-show="description">
                                    <dt class="text-xs font-medium text-[#64748B] sm:w-32 shrink-0">Description</dt>
                                    <dd class="text-sm text-[#1F2937]" x-text="description"></dd>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:gap-4">
                                    <dt class="text-xs font-medium text-[#64748B] sm:w-32 shrink-0">Contact</dt>
                                    <dd class="text-sm text-[#1F2937]" x-text="contactNumber"></dd>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:gap-4">
                                    <dt class="text-xs font-medium text-[#64748B] sm:w-32 shrink-0">Address</dt>
                                    <dd class="text-sm text-[#1F2937]" x-text="businessAddress"></dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" :disabled="submitting"
                            class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Submitting...' : 'Submit application'"></span>
                        </button>
                    </div>
                </div>

                {{-- Lightbox --}}
                <div x-show="previewImage" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" @click="closePreview()" @keydown.escape.window="closePreview()"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 cursor-pointer">
                    <button type="button" @click="closePreview()"
                        class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <img :src="previewImage" alt="ID Preview" @click.stop
                        class="max-w-full max-h-[85vh] rounded-xl cursor-default">
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('js/verification-wizard.js') }}"></script>
@endsection