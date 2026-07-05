{{--
    Reusable live-photo-capture component.
    Captures photos via device camera only — no file picker.
    Builds a FileList on a hidden <input type="file" multiple> via DataTransfer
    so the parent <form> submits real File objects under the given $name.

    Usage:
        <x-camera-capture-photo name="photos" :min="3" :max="10" />

    Props:
        name (string)  — input name, submitted as name[] (array)
        min  (int)     — minimum required shots before form is considered valid client-side
        max  (int)     — maximum shots allowed
--}}
@props([
    'name' => 'photos',
    'min' => 3,
    'max' => 10,
])

<div
    x-data="cameraCapturePhoto({ min: {{ $min }}, max: {{ $max }}, inputName: '{{ $name }}' })"
    x-init="init()"
    class="rounded-2xl border border-[#64748B]/20 bg-[#F7F8FA] p-4"
>
    {{-- Hidden input the form actually submits --}}
    <input type="file" x-ref="fileInput" name="{{ $name }}[]" multiple class="hidden" accept="image/*">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#0F172A] flex items-center justify-center shrink-0">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
            </div>
            <div>
                <p class="text-[13px] font-semibold text-[#0F172A]">Unit Photos</p>
                <p class="text-[11px] text-[#64748B] mt-0.5">
                    Live capture only — <span x-text="min"></span>–<span x-text="max"></span> photos required.
                </p>
            </div>
        </div>
        <span
            class="text-[11px] font-semibold px-2.5 py-1 rounded-full transition-colors duration-200"
            :class="shots.length >= min ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-600 ring-1 ring-amber-200'"
        >
            <span x-text="shots.length"></span> / <span x-text="max"></span>
        </span>
    </div>

    {{-- Starting camera state --}}
    <div x-show="!cameraReady && !error" class="flex items-center gap-2 px-3 py-2.5 mb-3 rounded-xl bg-white border border-[#64748B]/20">
        <svg class="animate-spin text-[#64748B] shrink-0" width="14" height="14" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="12" stroke-linecap="round"/>
        </svg>
        <span class="text-[12px] text-[#64748B]">Starting camera…</span>
    </div>

    {{-- Error state --}}
    <div x-show="error" x-cloak class="mb-3 px-3 py-2.5 rounded-xl bg-red-50 border border-red-100 text-red-700 text-[12px] font-medium flex items-start gap-2">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <span x-text="error"></span>
    </div>

    {{-- Camera viewfinder --}}
    <div x-show="cameraReady && shots.length < max" x-cloak class="relative rounded-xl overflow-hidden bg-black mb-3 aspect-video ring-1 ring-black/10">
        <video x-ref="video" autoplay playsinline muted class="w-full h-full object-cover"></video>
        {{-- Corner guide marks --}}
        <span class="absolute top-2.5 left-2.5 w-5 h-5 border-t-2 border-l-2 border-white/60 rounded-tl-sm pointer-events-none"></span>
        <span class="absolute top-2.5 right-2.5 w-5 h-5 border-t-2 border-r-2 border-white/60 rounded-tr-sm pointer-events-none"></span>
        <span class="absolute bottom-2.5 left-2.5 w-5 h-5 border-b-2 border-l-2 border-white/60 rounded-bl-sm pointer-events-none"></span>
        <span class="absolute bottom-2.5 right-2.5 w-5 h-5 border-b-2 border-r-2 border-white/60 rounded-br-sm pointer-events-none"></span>
        {{-- Shot counter overlay --}}
        <div class="absolute top-3 left-1/2 -translate-x-1/2 bg-black/50 backdrop-blur-sm rounded-full px-3 py-1 flex items-center gap-1.5">
            <span class="text-white/80 text-[11px]" x-text="`${shots.length} / ${max} captured`"></span>
        </div>
        {{-- Shutter button --}}
        <button
            type="button"
            @click="capture()"
            class="absolute bottom-4 left-1/2 -translate-x-1/2 w-16 h-16 rounded-full bg-white ring-4 ring-white/30 hover:scale-95 active:scale-90 transition-all duration-150 flex items-center justify-center shadow-lg"
            aria-label="Capture photo"
        >
            <span class="w-11 h-11 rounded-full bg-[#0F172A]"></span>
        </button>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>

    {{-- Thumbnail strip --}}
    <div class="grid grid-cols-4 sm:grid-cols-5 gap-2 mt-1" x-show="shots.length > 0">
        <template x-for="(shot, index) in shots" :key="shot.id">
            <div class="relative aspect-square rounded-lg overflow-hidden bg-[#F1F5F9] ring-1 ring-[#64748B]/15 group">
                <img :src="shot.url" class="w-full h-full object-cover">
                <button
                    type="button"
                    @click="remove(index)"
                    class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-[#EF4444] transition-colors duration-150 opacity-0 group-hover:opacity-100"
                    aria-label="Remove photo"
                >
                    <svg width="8" height="8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <p x-show="shots.length >= max" x-cloak class="text-[11px] text-[#64748B] mt-3 text-center">
        Maximum of <span x-text="max"></span> photos reached — remove one to add another.
    </p>
</div>

@once
    @push('scripts')
        <script>
            function cameraCapturePhoto({ min, max, inputName }) {
                return {
                    min, max, inputName,
                    shots: [],
                    cameraReady: false,
                    error: null,
                    stream: null,

                    async init() {
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: 'environment' },
                                audio: false,
                            });
                            this.$refs.video.srcObject = this.stream;
                            this.cameraReady = true;
                        } catch (e) {
                            this.error = 'Camera access is required to add unit photos. Please allow camera access and reload.';
                        }

                        this.$watch('shots', () => this.syncInput());
                    },

                    capture() {
                        if (this.shots.length >= this.max) return;
                        const video = this.$refs.video;
                        const canvas = this.$refs.canvas;
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                        canvas.toBlob((blob) => {
                            if (!blob) return;
                            const file = new File([blob], `unit-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
                            const url = URL.createObjectURL(blob);
                            this.shots.push({ id: crypto.randomUUID(), file, url });
                        }, 'image/jpeg', 0.9);
                    },

                    remove(index) {
                        URL.revokeObjectURL(this.shots[index].url);
                        this.shots.splice(index, 1);
                    },

                    syncInput() {
                        const dt = new DataTransfer();
                        this.shots.forEach((shot) => dt.items.add(shot.file));
                        this.$refs.fileInput.files = dt.files;
                    },
                };
            }
        </script>
    @endpush
@endonce