{{--
    Reusable live-video-capture component.
    Records a single clip via device camera + microphone — no file picker.
    Assigns the recorded blob as a real File on a hidden <input type="file"> via DataTransfer
    so the parent <form> submits it under the given $name.

    Usage:
        <x-camera-capture-video name="video" />

    Props:
        name (string) — input name
--}}
@props([
    'name' => 'video',
])

<div
    x-data="cameraCaptureVideo({ inputName: '{{ $name }}' })"
    x-init="init()"
    class="rounded-2xl border border-[#9B9F98]/25 bg-white p-4"
>
    <input type="file" x-ref="fileInput" name="{{ $name }}" class="hidden" accept="video/*">

    <div class="flex items-center justify-between mb-3">
        <div>
            <p class="text-sm font-semibold text-[#0F172A]">Unit Walkthrough Video</p>
            <p class="text-[11px] text-[#9B9F98] mt-0.5">Live camera recording only — one short walkthrough clip required.</p>
        </div>
        <span
            class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
            :class="recordedUrl ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-600'"
        >
            <span x-text="recordedUrl ? 'Recorded' : 'Not recorded'"></span>
        </span>
    </div>

    <div x-show="!cameraReady && !error" class="text-[12px] text-[#9B9F98] mb-3">Starting camera…</div>

    <div x-show="error" x-cloak class="mb-3 px-3 py-2.5 rounded-xl bg-red-50 text-red-700 text-[12px] font-medium">
        <span x-text="error"></span>
    </div>

    {{-- Live preview while recording / before recording --}}
    <div x-show="cameraReady && !recordedUrl" x-cloak class="relative rounded-xl overflow-hidden bg-black mb-3 aspect-video">
        <video x-ref="liveVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>

        <div x-show="recording" x-cloak class="absolute top-3 left-3 flex items-center gap-1.5 bg-black/60 rounded-full px-2.5 py-1">
            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
            <span class="text-white text-[11px] font-semibold" x-text="formattedTime"></span>
        </div>

        <button
            type="button"
            x-show="!recording"
            @click="startRecording()"
            class="absolute bottom-3 left-1/2 -translate-x-1/2 inline-flex items-center gap-2 h-10 px-5 rounded-full bg-white text-[#0F172A] text-[13px] font-semibold hover:brightness-95 transition-all duration-200"
        >
            <span class="w-3 h-3 rounded-full bg-red-500"></span>
            Start Recording
        </button>

        <button
            type="button"
            x-show="recording"
            @click="stopRecording()"
            class="absolute bottom-3 left-1/2 -translate-x-1/2 inline-flex items-center gap-2 h-10 px-5 rounded-full bg-red-500 text-white text-[13px] font-semibold hover:brightness-95 transition-all duration-200"
        >
            <span class="w-3 h-3 rounded-sm bg-white"></span>
            Stop Recording
        </button>
    </div>

    {{-- Playback after recording --}}
    <div x-show="recordedUrl" x-cloak class="rounded-xl overflow-hidden bg-black mb-3 aspect-video">
        <video x-ref="playbackVideo" :src="recordedUrl" controls playsinline class="w-full h-full object-cover"></video>
    </div>

    <button
        type="button"
        x-show="recordedUrl"
        x-cloak
        @click="retake()"
        class="text-[12px] font-semibold text-[#0F172A] underline underline-offset-2 hover:no-underline"
    >
        Retake video
    </button>
</div>

@once
    @push('scripts')
        <script>
            function cameraCaptureVideo({ inputName }) {
                return {
                    inputName,
                    cameraReady: false,
                    error: null,
                    stream: null,
                    recorder: null,
                    chunks: [],
                    recording: false,
                    recordedUrl: null,
                    seconds: 0,
                    timerId: null,
                    mimeType: 'video/webm',
                    fileExt: 'webm',

                    get formattedTime() {
                        const m = Math.floor(this.seconds / 60).toString().padStart(2, '0');
                        const s = (this.seconds % 60).toString().padStart(2, '0');
                        return `${m}:${s}`;
                    },

                    async init() {
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: 'environment' },
                                audio: true,
                            });
                            this.$refs.liveVideo.srcObject = this.stream;
                            this.cameraReady = true;
                            this.detectSupportedMimeType();
                        } catch (e) {
                            this.error = 'Camera access is required to record the unit walkthrough. Please allow camera access and reload.';
                        }
                    },

                    detectSupportedMimeType() {
                        const candidates = [
                            { mime: 'video/webm;codecs=vp9,opus', ext: 'webm' },
                            { mime: 'video/webm;codecs=vp8,opus', ext: 'webm' },
                            { mime: 'video/webm', ext: 'webm' },
                            { mime: 'video/mp4', ext: 'mp4' },
                        ];
                        const supported = candidates.find(
                            (c) => window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(c.mime)
                        );
                        if (supported) {
                            this.mimeType = supported.mime;
                            this.fileExt = supported.ext;
                        } else {
                            // No explicit support reported (notably some iOS Safari versions) —
                            // omit mimeType and let the browser pick its default.
                            this.mimeType = '';
                            this.fileExt = 'mp4';
                        }
                    },

                    startRecording() {
                        this.chunks = [];
                        const options = this.mimeType ? { mimeType: this.mimeType } : {};
                        try {
                            this.recorder = new MediaRecorder(this.stream, options);
                        } catch (e) {
                            // Fallback: let the browser choose entirely if our detected type still fails
                            this.recorder = new MediaRecorder(this.stream);
                        }
                        this.recorder.ondataavailable = (e) => {
                            if (e.data.size > 0) this.chunks.push(e.data);
                        };
                        this.recorder.onstop = () => this.finalizeRecording();
                        this.recorder.start();
                        this.recording = true;
                        this.seconds = 0;
                        this.timerId = setInterval(() => this.seconds++, 1000);
                    },

                    stopRecording() {
                        if (this.recorder && this.recording) {
                            this.recorder.stop();
                        }
                        this.recording = false;
                        clearInterval(this.timerId);
                    },

                    finalizeRecording() {
                        const outputType = this.recorder?.mimeType || this.mimeType || 'video/webm';
                        const blob = new Blob(this.chunks, { type: outputType });
                        const ext = outputType.includes('mp4') ? 'mp4' : this.fileExt;
                        const file = new File([blob], `unit-walkthrough-${Date.now()}.${ext}`, { type: outputType });
                        this.recordedUrl = URL.createObjectURL(blob);

                        const dt = new DataTransfer();
                        dt.items.add(file);
                        this.$refs.fileInput.files = dt.files;
                    },

                    retake() {
                        if (this.recordedUrl) URL.revokeObjectURL(this.recordedUrl);
                        this.recordedUrl = null;
                        this.$refs.fileInput.value = '';
                    },
                };
            }
        </script>
    @endpush
@endonce