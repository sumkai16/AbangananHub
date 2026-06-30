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
    class="rounded-2xl border border-[#9B9F98]/25 bg-white p-4"
>
    {{-- Hidden input the form actually submits --}}
    <input type="file" x-ref="fileInput" name="{{ $name }}[]" multiple class="hidden" accept="image/*">

    <div class="flex items-center justify-between mb-3">
        <div>
            <p class="text-sm font-semibold text-[#0F172A]">Unit Photos</p>
            <p class="text-[11px] text-[#9B9F98] mt-0.5">
                Live camera capture only — <span x-text="min"></span> to <span x-text="max"></span> photos required.
            </p>
        </div>
        <span
            class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
            :class="shots.length >= min ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-600'"
        >
            <span x-text="shots.length"></span> / <span x-text="max"></span>
        </span>
    </div>

    {{-- Camera / error states --}}
    <div x-show="!cameraReady && !error" class="text-[12px] text-[#9B9F98] mb-3">Starting camera…</div>

    <div x-show="error" x-cloak class="mb-3 px-3 py-2.5 rounded-xl bg-red-50 text-red-700 text-[12px] font-medium">
        <span x-text="error"></span>
    </div>

    <div x-show="cameraReady && shots.length < max" x-cloak class="relative rounded-xl overflow-hidden bg-black mb-3 aspect-video">
        <video x-ref="video" autoplay playsinline muted class="w-full h-full object-cover"></video>
        <button
            type="button"
            @click="capture()"
            class="absolute bottom-3 left-1/2 -translate-x-1/2 w-14 h-14 rounded-full bg-white ring-4 ring-white/40 hover:brightness-95 transition-all duration-200 flex items-center justify-center"
            aria-label="Capture photo"
        >
            <span class="w-10 h-10 rounded-full bg-[#0F172A]"></span>
        </button>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>

    {{-- Thumbnail strip --}}
    <div class="grid grid-cols-4 sm:grid-cols-5 gap-2" x-show="shots.length > 0">
        <template x-for="(shot, index) in shots" :key="shot.id">
            <div class="relative aspect-square rounded-lg overflow-hidden bg-[#F1F5F9]">
                <img :src="shot.url" class="w-full h-full object-cover">
                <button
                    type="button"
                    @click="remove(index)"
                    class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center text-[10px] hover:bg-black/80"
                    aria-label="Remove photo"
                >✕</button>
            </div>
        </template>
    </div>

    <p x-show="shots.length >= max" x-cloak class="text-[11px] text-[#9B9F98] mt-3">
        Maximum of <span x-text="max"></span> photos reached. Remove one to capture another.
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