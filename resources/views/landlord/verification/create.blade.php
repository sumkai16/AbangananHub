@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])
@section('content')<div class="min-h-screen bg-[#F7FCFC] py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto"
             x-data="verificationWizard()"
             x-init="init()">

            {{-- Rejection Banner --}}
            @if($verification && $verification->verification_status === 'Rejected')
                <div class="mb-6 rounded-lg border border-[#EF4444]/30 bg-red-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-[#EF4444] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
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

            {{-- Header --}}
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-[#156F8C]">Apply as Landlord</h1>
                <p class="mt-1 text-sm text-[#64748B]">Complete all steps to submit your verification application.</p>
            </div>

            {{-- Progress Bar --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <template x-for="i in 5" :key="i">
                        <div class="flex items-center" :class="i < 5 ? 'flex-1' : ''">
                            <div class="flex flex-col items-center">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-colors duration-200"
                                     :class="step > i ? 'bg-[#22C55E] text-white' : (step === i ? 'bg-[#2AA7A1] text-white' : 'bg-[#E2E8F0] text-[#64748B]')">
                                    <template x-if="step > i">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </template>
                                    <template x-if="step <= i">
                                        <span x-text="i"></span>
                                    </template>
                                </div>
                                <span class="hidden sm:block mt-1 text-xs text-[#64748B]"
                                      x-text="stepLabels[i - 1]"></span>
                            </div>
                            <div x-show="i < 5" class="flex-1 h-0.5 mx-2"
                                 :class="step > i ? 'bg-[#22C55E]' : 'bg-[#E2E8F0]'"></div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Validation Errors --}}
            @if($errors->any())
                <div class="mb-6 rounded-lg border border-[#EF4444]/30 bg-red-50 p-4">
                    <ul class="list-disc list-inside text-sm text-[#EF4444] space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST"
                  action="{{ route('landlord.verification.store') }}"
                  enctype="multipart/form-data"
                  x-ref="form"
                  @submit.prevent="submitForm()">
                @csrf

                {{-- Hidden fields for base64 data --}}
                <input type="hidden" name="id_type" :value="idType">
                <input type="hidden" name="id_image" :value="idImageBase64">
                <input type="hidden" name="id_back" :value="idBackBase64">
                <input type="hidden" name="selfie" :value="selfieBase64">

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 1 — Select ID Type                        --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 1" x-transition:enter.duration.200ms>
                    <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                        <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Select your Government ID</h2>
                        <p class="text-sm text-[#64748B] mb-5">Choose the type of valid, unexpired Philippine government ID you will use for verification.</p>

                        <div class="space-y-2">
                            <template x-for="id in idTypes" :key="id">
                                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors duration-150"
                                       :class="idType === id ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#E2E8F0] hover:bg-[#F7FCFC]'">
                                    <input type="radio"
                                           :value="id"
                                           x-model="idType"
                                           class="h-4 w-4 text-[#2AA7A1] border-[#E2E8F0] focus:ring-[#2AA7A1]">
                                    <span class="text-sm text-[#1F2937]" x-text="id"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button"
                                @click="nextStep()"
                                :disabled="!idType"
                                class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="idType ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                            Continue
                        </button>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 2 — Capture Government ID (Front + Back)  --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 2" x-transition:enter.duration.200ms>
                    <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                        <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Capture your <span x-text="idType"></span></h2>
                        <p class="text-sm text-[#64748B] mb-4">
                            <template x-if="needsBack">
                                <span>Take photos of both the front and back of your ID.</span>
                            </template>
                            <template x-if="!needsBack">
                                <span>Take a photo of the data page of your Passport.</span>
                            </template>
                        </p>

                        {{-- Tips --}}
                        <div class="mb-5 rounded-lg bg-[#EEF8F8] p-3">
                            <div class="flex items-start gap-2">
                                <svg class="h-5 w-5 text-[#2AA7A1] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                <ul class="text-xs text-[#64748B] space-y-1">
                                    <li>Use good, even lighting — avoid glare and shadows</li>
                                    <li>Place the ID on a flat, dark surface</li>
                                    <li>Ensure all four corners are visible</li>
                                    <li>Text on the ID must be sharp and readable</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Camera Error --}}
                        <template x-if="cameraError">
                            <div class="mb-4 rounded-lg border border-[#EF4444]/30 bg-red-50 p-3">
                                <p class="text-sm text-[#EF4444]" x-text="cameraError"></p>
                            </div>
                        </template>

                        {{-- ── Front of ID ── --}}
                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-[#1F2937] mb-3 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[#2AA7A1] text-white text-xs flex items-center justify-center font-bold">1</span>
                                <span x-text="needsBack ? 'Front of ID' : 'ID Data Page'"></span>
                                <template x-if="idImageBase64">
                                    <svg class="w-5 h-5 text-[#22C55E]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </template>
                            </h3>

                            {{-- Camera Viewfinder (front) --}}
                            <div x-show="!idImageBase64 && captureSide === 'front'" class="relative">
                                <div class="relative rounded-lg overflow-hidden bg-black aspect-[4/3]">
                                    <video x-ref="videoId" autoplay playsinline class="w-full h-full object-cover"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="w-[85%] h-[60%] border-2 border-white/60 rounded-lg"></div>
                                    </div>
                                </div>
                                <canvas x-ref="canvasId" class="hidden"></canvas>
                                <div class="mt-3 flex justify-center">
                                    <button type="button"
                                            @click="capturePhoto('id')"
                                            :disabled="!cameraActive"
                                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                        </svg>
                                        Capture Front
                                    </button>
                                </div>
                            </div>

                            {{-- Front Preview --}}
                            <div x-show="idImageBase64">
                                <div class="rounded-lg overflow-hidden border border-[#E2E8F0]">
                                    <img :src="idImageBase64" alt="Front of ID" class="w-full">
                                </div>
                                <div class="mt-2 flex justify-center">
                                    <button type="button" @click="retakePhoto('id')"
                                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-xs font-semibold text-[#1F2937] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                        </svg>
                                        Retake front
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- ── Back of ID ── --}}
                        <div x-show="needsBack">
                            <h3 class="text-sm font-semibold text-[#1F2937] mb-3 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[#2AA7A1] text-white text-xs flex items-center justify-center font-bold">2</span>
                                Back of ID
                                <template x-if="idBackBase64">
                                    <svg class="w-5 h-5 text-[#22C55E]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </template>
                            </h3>

                            {{-- Camera Viewfinder (back) --}}
                            <div x-show="!idBackBase64 && captureSide === 'back'" class="relative">
                                <div class="relative rounded-lg overflow-hidden bg-black aspect-[4/3]">
                                    <video x-ref="videoIdBack" autoplay playsinline class="w-full h-full object-cover"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="w-[85%] h-[60%] border-2 border-white/60 rounded-lg"></div>
                                    </div>
                                </div>
                                <canvas x-ref="canvasIdBack" class="hidden"></canvas>
                                <div class="mt-3 flex justify-center">
                                    <button type="button"
                                            @click="capturePhoto('idBack')"
                                            :disabled="!cameraActive"
                                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                        </svg>
                                        Capture Back
                                    </button>
                                </div>
                            </div>

                            {{-- Prompt to start back capture --}}
                            <div x-show="!idBackBase64 && captureSide !== 'back' && idImageBase64">
                                <button type="button" @click="startBackCapture()"
                                        class="w-full flex items-center justify-center gap-2 py-8 rounded-lg border-2 border-dashed border-[#E2E8F0] text-sm text-[#64748B] hover:border-[#2AA7A1] hover:text-[#2AA7A1] transition-colors duration-150">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                    </svg>
                                    Flip your ID and capture the back
                                </button>
                            </div>

                            {{-- Back Preview --}}
                            <div x-show="idBackBase64">
                                <div class="rounded-lg overflow-hidden border border-[#E2E8F0]">
                                    <img :src="idBackBase64" alt="Back of ID" class="w-full">
                                </div>
                                <div class="mt-2 flex justify-center">
                                    <button type="button" @click="retakePhoto('idBack')"
                                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-xs font-semibold text-[#1F2937] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                        </svg>
                                        Retake back
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="prevStep()"
                                class="px-5 py-2.5 rounded-lg text-sm font-semibold text-[#64748B] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Back
                        </button>
                        <button type="button" @click="nextStep()"
                                :disabled="!idCaptureComplete"
                                class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="idCaptureComplete ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                            Continue
                        </button>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 3 — Capture Selfie                        --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 3" x-transition:enter.duration.200ms>
                    <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                        <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Take a Selfie</h2>
                        <p class="text-sm text-[#64748B] mb-4">We will compare this with the photo on your ID to confirm your identity.</p>

                        {{-- Tips --}}
                        <div class="mb-4 rounded-lg bg-[#EEF8F8] p-3">
                            <div class="flex items-start gap-2">
                                <svg class="h-5 w-5 text-[#2AA7A1] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                <ul class="text-xs text-[#64748B] space-y-1">
                                    <li>Face the camera directly — no sunglasses or hats</li>
                                    <li>Use a plain, well-lit background</li>
                                    <li>Keep your face centered in the guide</li>
                                </ul>
                            </div>
                        </div>

                        <template x-if="cameraError">
                            <div class="mb-4 rounded-lg border border-[#EF4444]/30 bg-red-50 p-3">
                                <p class="text-sm text-[#EF4444]" x-text="cameraError"></p>
                            </div>
                        </template>

                        <div x-show="!selfieBase64" class="relative">
                            <div class="relative rounded-lg overflow-hidden bg-black aspect-[3/4] max-w-sm mx-auto">
                                <video x-ref="videoSelfie" autoplay playsinline class="w-full h-full object-cover" style="transform: scaleX(-1)"></video>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="w-[55%] h-[50%] border-2 border-white/60 rounded-full"></div>
                                </div>
                            </div>
                            <canvas x-ref="canvasSelfie" class="hidden"></canvas>
                            <div class="mt-4 flex justify-center">
                                <button type="button"
                                        @click="capturePhoto('selfie')"
                                        :disabled="!cameraActive"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                    </svg>
                                    Take Selfie
                                </button>
                            </div>
                        </div>

                        <div x-show="selfieBase64">
                            <div class="rounded-lg overflow-hidden border border-[#E2E8F0] max-w-sm mx-auto">
                                <img :src="selfieBase64" alt="Captured selfie" class="w-full">
                            </div>
                            <div class="mt-4 flex justify-center">
                                <button type="button" @click="retakePhoto('selfie')"
                                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-[#1F2937] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                    Retake
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="prevStep()"
                                class="px-5 py-2.5 rounded-lg text-sm font-semibold text-[#64748B] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Back
                        </button>
                        <button type="button" @click="nextStep()"
                                :disabled="!selfieBase64"
                                class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="selfieBase64 ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                            Continue
                        </button>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 4 — Business Details                      --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 4" x-transition:enter.duration.200ms>
                    <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                        <h2 class="text-lg font-semibold text-[#1F2937] mb-1">Rental Business Details</h2>
                        <p class="text-sm text-[#64748B] mb-5">Tell us about the rental business you will operate on AbangananHub.</p>

                        <div class="space-y-4">
                            <div>
                                <label for="business_name" class="block text-sm font-medium text-[#1F2937] mb-1">Business Name <span class="text-[#EF4444]">*</span></label>
                                <input type="text" id="business_name" name="business_name"
                                       x-model="businessName"
                                       class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors"
                                       placeholder="e.g. Sunrise Boarding House">
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-[#1F2937] mb-1">Description <span class="text-[#64748B] text-xs font-normal">(optional)</span></label>
                                <textarea id="description" name="description" rows="3"
                                          x-model="description"
                                          maxlength="1000"
                                          class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors resize-none"
                                          placeholder="Brief description of your rental business"></textarea>
                                <p class="mt-1 text-xs text-[#64748B]" x-text="(description?.length || 0) + '/1000'"></p>
                            </div>

                            <div>
                                <label for="logo" class="block text-sm font-medium text-[#1F2937] mb-1">Business Logo <span class="text-[#64748B] text-xs font-normal">(optional, max 2MB)</span></label>
                                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png"
                                       class="w-full text-sm text-[#64748B] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#EEF8F8] file:text-[#156F8C] hover:file:bg-[#ddf1f1] file:cursor-pointer file:transition-colors">
                            </div>

                            <div>
                                <label for="contact_number" class="block text-sm font-medium text-[#1F2937] mb-1">Contact Number <span class="text-[#EF4444]">*</span></label>
                                <input type="text" id="contact_number" name="contact_number"
                                       x-model="contactNumber"
                                       class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors"
                                       placeholder="09XX XXX XXXX">
                            </div>

                            <div>
                                <label for="business_address" class="block text-sm font-medium text-[#1F2937] mb-1">Business Address <span class="text-[#EF4444]">*</span></label>
                                <input type="text" id="business_address" name="business_address"
                                       x-model="businessAddress"
                                       class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none transition-colors"
                                       placeholder="Full address of your rental property/business">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="prevStep()"
                                class="px-5 py-2.5 rounded-lg text-sm font-semibold text-[#64748B] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Back
                        </button>
                        <button type="button" @click="nextStep()"
                                :disabled="!businessName || !contactNumber || !businessAddress"
                                class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="(businessName && contactNumber && businessAddress) ? 'bg-[#2AA7A1] hover:brightness-95' : 'bg-[#2AA7A1]'">
                            Continue
                        </button>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- STEP 5 — Review & Submit                       --}}
                {{-- ═══════════════════════════════════════════════ --}}
                <div x-show="step === 5" x-transition:enter.duration.200ms>
                    <div class="space-y-4">

                        {{-- OCR Result --}}
                        <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                            <h2 class="text-lg font-semibold text-[#1F2937] mb-4">ID Verification Preview</h2>

                            <div x-show="ocrLoading" class="flex items-center gap-3 py-6 justify-center">
                                <svg class="animate-spin h-5 w-5 text-[#2AA7A1]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span class="text-sm text-[#64748B]">Scanning your ID...</span>
                            </div>

                            <div x-show="!ocrLoading && ocrResult" class="space-y-3">
                                {{-- Name Match Status --}}
                                <div class="flex items-start gap-3 p-3 rounded-lg"
                                     :class="ocrResult?.status === 'pass' ? 'bg-green-50' : (ocrResult?.status === 'partial' ? 'bg-amber-50' : 'bg-red-50')">
                                    <template x-if="ocrResult?.status === 'pass'">
                                        <svg class="w-5 h-5 text-[#22C55E] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </template>
                                    <template x-if="ocrResult?.status === 'partial'">
                                        <svg class="w-5 h-5 text-[#FBBF24] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                    </template>
                                    <template x-if="ocrResult?.status === 'fail'">
                                        <svg class="w-5 h-5 text-[#EF4444] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </template>
                                    <div>
                                        <p class="text-sm font-semibold text-[#1F2937]"
                                           x-text="ocrResult?.status === 'pass' ? 'Name match confirmed' : (ocrResult?.status === 'partial' ? 'Partial name match' : 'Name could not be verified')"></p>
                                        <p class="text-xs text-[#64748B] mt-0.5">
                                            Your account: <span class="font-medium" x-text="ocrResult?.user_name"></span>
                                        </p>
                                        <p class="text-xs text-[#64748B]" x-show="ocrResult?.name">
                                            ID reads: <span class="font-medium" x-text="ocrResult?.name"></span>
                                            (<span x-text="ocrResult?.confidence"></span>% confidence)
                                        </p>
                                        <p class="text-xs text-[#64748B] mt-1" x-show="ocrResult?.status === 'fail'">
                                            You can still submit — an admin will manually verify your ID.
                                        </p>
                                    </div>
                                </div>

                                {{-- ID Number --}}
                                <div x-show="ocrResult?.id_number" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[#EEF8F8]">
                                    <svg class="w-4 h-4 text-[#2AA7A1] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                                    </svg>
                                    <span class="text-xs text-[#64748B]">ID Number: <span class="font-medium text-[#1F2937]" x-text="ocrResult?.id_number"></span></span>
                                </div>

                                {{-- ID Type Match --}}
                                <div x-show="ocrResult?.type_match === 'mismatch'"
                                     class="flex items-start gap-3 p-3 rounded-lg bg-amber-50">
                                    <svg class="w-5 h-5 text-[#FBBF24] mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-[#1F2937]">ID type may not match your selection</p>
                                        <p class="text-xs text-[#64748B] mt-0.5">You selected <span class="font-medium" x-text="idType"></span>, but the document text didn't match expected keywords. Please verify you captured the correct ID. You can go back to retake if needed.</p>
                                    </div>
                                </div>

                                <div x-show="ocrResult?.type_match === 'match'"
                                     class="flex items-center gap-2 px-3 py-2 rounded-lg bg-green-50">
                                    <svg class="w-4 h-4 text-[#22C55E] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span class="text-xs text-[#1F2937]">Document type confirmed: <span class="font-medium" x-text="idType"></span></span>
                                </div>
                            </div>

                            <div x-show="!ocrLoading && ocrError" class="rounded-lg border border-[#FBBF24]/30 bg-amber-50 p-3">
                                <p class="text-sm text-[#1F2937]">OCR preview unavailable. You can still submit — the admin will review your ID manually.</p>
                            </div>
                        </div>

                        {{-- Photo Previews --}}
                        <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                            <h2 class="text-lg font-semibold text-[#1F2937] mb-4">Your Photos</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                                 :class="needsBack ? 'lg:grid-cols-3' : ''">
                                <div>
                                    <p class="text-xs font-medium text-[#64748B] mb-2" x-text="needsBack ? 'ID Front' : 'Government ID'"></p>
                                    <div class="rounded-lg overflow-hidden border border-[#E2E8F0]">
                                        <img :src="idImageBase64" alt="Front of ID" class="w-full">
                                    </div>
                                </div>
                                <div x-show="needsBack && idBackBase64">
                                    <p class="text-xs font-medium text-[#64748B] mb-2">ID Back</p>
                                    <div class="rounded-lg overflow-hidden border border-[#E2E8F0]">
                                        <img :src="idBackBase64" alt="Back of ID" class="w-full">
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#64748B] mb-2">Selfie</p>
                                    <div class="rounded-lg overflow-hidden border border-[#E2E8F0]">
                                        <img :src="selfieBase64" alt="Selfie" class="w-full">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Business Summary --}}
                        <div class="bg-white rounded-lg border border-[#E2E8F0] p-6">
                            <h2 class="text-lg font-semibold text-[#1F2937] mb-4">Business Details</h2>
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

                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="prevStep()"
                                class="px-5 py-2.5 rounded-lg text-sm font-semibold text-[#64748B] border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Back
                        </button>
                        <button type="submit"
                                :disabled="submitting"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#FF8A65] hover:brightness-95 transition-all duration-150 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Submitting...' : 'Submit Application'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function verificationWizard() {
        return {
            step: 1,
            stepLabels: ['ID Type', 'ID Photo', 'Selfie', 'Business', 'Review'],

            idTypes: [
                'PhilSys',
                'Professional ID Card',
                "Driver's License",
                'Passport',
                'UMID',
                'Postal ID',
                'SSS ID',
            ],
            idType: '',

            // Captured images
            idImageBase64: '',
            idBackBase64: '',
            selfieBase64: '',

            // Which side of the ID is being captured
            captureSide: 'front',

            // Business fields
            businessName: '',
            description: '',
            contactNumber: '',
            businessAddress: '',

            // Camera
            cameraStream: null,
            cameraActive: false,
            cameraError: '',

            // OCR
            ocrLoading: false,
            ocrResult: null,
            ocrError: false,

            // Submission
            submitting: false,

            get needsBack() {
                return this.idType !== 'Passport';
            },

            get idCaptureComplete() {
                if (!this.idImageBase64) return false;
                if (this.needsBack && !this.idBackBase64) return false;
                return true;
            },

            init() {
                this.$watch('step', (newStep, oldStep) => {
                    this.stopCamera();
                    this.cameraError = '';
                    this.captureSide = 'front';

                    if (newStep === 2 && !this.idImageBase64) {
                        this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
                    } else if (newStep === 3 && !this.selfieBase64) {
                        this.$nextTick(() => this.startCamera('user', this.$refs.videoSelfie));
                    } else if (newStep === 5) {
                        this.runOcrCheck();
                    }
                });

                // Clear back image if user changes ID type to/from Passport
                this.$watch('idType', (newType, oldType) => {
                    if (oldType && newType !== oldType) {
                        this.idBackBase64 = '';
                    }
                });
            },

            async startCamera(facingMode, videoEl) {
                this.cameraActive = false;
                this.cameraError = '';

                try {
                    this.cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: facingMode,
                            width: { ideal: 1280 },
                            height: { ideal: 960 },
                        },
                        audio: false,
                    });

                    videoEl.srcObject = this.cameraStream;
                    await videoEl.play();
                    this.cameraActive = true;
                } catch (err) {
                    console.error('Camera error:', err);
                    if (err.name === 'NotAllowedError') {
                        this.cameraError = 'Camera access was denied. Please allow camera access in your browser settings and try again.';
                    } else if (err.name === 'NotFoundError') {
                        this.cameraError = 'No camera found on this device.';
                    } else {
                        this.cameraError = 'Could not access the camera. Please try again or use a different device.';
                    }
                }
            },

            stopCamera() {
                if (this.cameraStream) {
                    this.cameraStream.getTracks().forEach(track => track.stop());
                    this.cameraStream = null;
                }
                this.cameraActive = false;
            },

            capturePhoto(type) {
                let video, canvas;

                if (type === 'id') {
                    video = this.$refs.videoId;
                    canvas = this.$refs.canvasId;
                } else if (type === 'idBack') {
                    video = this.$refs.videoIdBack;
                    canvas = this.$refs.canvasIdBack;
                } else {
                    video = this.$refs.videoSelfie;
                    canvas = this.$refs.canvasSelfie;
                }

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                const ctx = canvas.getContext('2d');

                if (type === 'selfie') {
                    ctx.translate(canvas.width, 0);
                    ctx.scale(-1, 1);
                }

                ctx.drawImage(video, 0, 0);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

                if (type === 'id') {
                    this.idImageBase64 = dataUrl;
                } else if (type === 'idBack') {
                    this.idBackBase64 = dataUrl;
                } else {
                    this.selfieBase64 = dataUrl;
                }

                this.stopCamera();
            },

            startBackCapture() {
                this.captureSide = 'back';
                this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
            },

            retakePhoto(type) {
                if (type === 'id') {
                    this.idImageBase64 = '';
                    this.captureSide = 'front';
                    this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
                } else if (type === 'idBack') {
                    this.idBackBase64 = '';
                    this.captureSide = 'back';
                    this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
                } else {
                    this.selfieBase64 = '';
                    this.$nextTick(() => this.startCamera('user', this.$refs.videoSelfie));
                }
            },

            async runOcrCheck() {
                if (!this.idImageBase64 || !this.idType) return;

                this.ocrLoading = true;
                this.ocrResult = null;
                this.ocrError = false;

                try {
                    const response = await fetch('{{ route("landlord.verification.ocrCheck") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            id_image: this.idImageBase64,
                            id_type: this.idType,
                        }),
                    });

                    if (!response.ok) throw new Error('OCR request failed');
                    this.ocrResult = await response.json();
                } catch (err) {
                    console.error('OCR check failed:', err);
                    this.ocrError = true;
                } finally {
                    this.ocrLoading = false;
                }
            },

            nextStep() {
                if (this.step < 5) this.step++;
            },

            prevStep() {
                if (this.step > 1) this.step--;
            },

            submitForm() {
                this.submitting = true;
                this.$refs.form.submit();
            },
        };
    }
    </script>
@endsection