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
    class="rounded-2xl border border-[#9B9F98]/20 bg-[#F7F8FA] p-4"
>
    <input type="file" x-ref="fileInput" name="{{ $name }}" class="hidden" accept="video/*">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#0F172A] flex items-center justify-center shrink-0">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
            <div>
                <p class="text-[13px] font-semibold text-[#0F172A]">Unit Walkthrough Video</p>
                <p class="text-[11px] text-[#9B9F98] mt-0.5">Live recording only — one short walkthrough clip required.</p>
            </div>
        </div>
        <span
            class="text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 transition-colors duration-200"
            :class="recordedUrl ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-600 ring-amber-200'"
        >
            <span x-text="recordedUrl ? 'Recorded' : 'Not recorded'"></span>
        </span>
    </div>

    {{-- Starting camera state --}}
    <div x-show="!cameraReady && !error" class="flex items-center gap-2 px-3 py-2.5 mb-3 rounded-xl bg-white border border-[#9B9F98]/20">
        <svg class="animate-spin text-[#9B9F98] shrink-0" width="14" height="14" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="12" stroke-linecap="round"/>
        </svg>
        <span class="text-[12px] text-[#9B9F98]">Starting camera…</span>
    </div>

    {{-- Error state --}}
    <div x-show="error" x-cloak class="mb-3 px-3 py-2.5 rounded-xl bg-red-50 border border-red-100 text-red-700 text-[12px] font-medium flex items-start gap-2">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <span x-text="error"></span>
    </div>

    {{-- Live viewfinder --}}
    <div x-show="cameraReady && !recordedUrl" x-cloak class="relative rounded-xl overflow-hidden bg-black mb-3 aspect-video ring-1 ring-black/10">
        <video x-ref="liveVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>

        {{-- Corner guide marks --}}
        <span class="absolute top-2.5 left-2.5 w-5 h-5 border-t-2 border-l-2 border-white/60 rounded-tl-sm pointer-events-none"></span>
        <span class="absolute top-2.5 right-2.5 w-5 h-5 border-t-2 border-r-2 border-white/60 rounded-tr-sm pointer-events-none"></span>
        <span class="absolute bottom-2.5 left-2.5 w-5 h-5 border-b-2 border-l-2 border-white/60 rounded-bl-sm pointer-events-none"></span>
        <span class="absolute bottom-2.5 right-2.5 w-5 h-5 border-b-2 border-r-2 border-white/60 rounded-br-sm pointer-events-none"></span>

        {{-- Recording indicator --}}
        <div x-show="recording" x-cloak class="absolute top-3 left-3 flex items-center gap-1.5 bg-black/60 backdrop-blur-sm rounded-full px-2.5 py-1">
            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
            <span class="text-white text-[11px] font-semibold tracking-wide" x-text="formattedTime"></span>
        </div>

        {{-- Start button --}}
        <button
            type="button"
            x-show="!recording"
            @click="startRecording()"
            class="absolute bottom-4 left-1/2 -translate-x-1/2 inline-flex items-center gap-2 h-11 px-5 rounded-full bg-white text-[#0F172A] text-[13px] font-semibold shadow-lg hover:scale-95 active:scale-90 transition-all duration-150"
        >
            <span class="w-3 h-3 rounded-full bg-red-500"></span>
            Start Recording
        </button>

        {{-- Stop button --}}
        <button
            type="button"
            x-show="recording"
            x-cloak
            @click="stopRecording()"
            class="absolute bottom-4 left-1/2 -translate-x-1/2 inline-flex items-center gap-2 h-11 px-5 rounded-full bg-red-500 text-white text-[13px] font-semibold shadow-lg hover:scale-95 active:scale-90 transition-all duration-150"
        >
            <span class="w-3 h-3 rounded-sm bg-white"></span>
            Stop Recording
        </button>
    </div>

    {{-- Playback after recording --}}
    <div x-show="recordedUrl" x-cloak class="rounded-xl overflow-hidden bg-black mb-3 aspect-video ring-1 ring-black/10">
        <video x-ref="playbackVideo" :src="recordedUrl" controls playsinline class="w-full h-full object-cover"></video>
    </div>

    {{-- Retake --}}
    <div x-show="recordedUrl" x-cloak class="flex items-center justify-between">
        <div class="flex items-center gap-1.5 text-[11px] text-emerald-700">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
            </svg>
            <span class="font-medium">Walkthrough recorded</span>
        </div>
        <button
            type="button"
            @click="retake()"
            class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg border border-[#9B9F98]/30 text-[12px] font-semibold text-[#0F172A] hover:bg-[#F1F5F9] transition-colors duration-200"
        >
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Retake
        </button>
    </div>
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