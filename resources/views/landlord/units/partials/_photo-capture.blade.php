{{-- Shared live-capture + upload UI for unit photos — create and edit both
     include this. Extracted from the original create-only markup; it binds
     to hard-coded DOM ids (photo-input, camera-video, live-count, …), so
     only one instance may render on a page at a time.

     Params (pass via @include('...', [...])):
     - $existingLiveCount (int, default 0): camera-sourced photos the unit
       already has. On create this is always 0. Display only, informational —
       there is no required minimum of live vs. uploaded photos (Sept 2026);
       it's the landlord's choice which tab to use, or both.
--}}
@php $existingLiveCount = $existingLiveCount ?? 0; @endphp
<x-card flush class="p-6" x-data="{ tab: 'live' }">
    <div class="flex items-center gap-2.5 mb-3">
        <div class="w-8 h-8 rounded-lg bg-[#156F8C] flex items-center justify-center shrink-0">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
        </div>
        <div>
            <h2 class="text-[13px] font-bold text-[#1F2937]">
                {{ $existingLiveCount > 0 ? 'Add more photos' : 'Unit Photos' }}
                @if($existingLiveCount === 0)<span class="text-[#EF4444]">*</span>@endif
            </h2>
            <p class="text-[11px] text-[#64748B] mt-0.5">
                @if($existingLiveCount > 0)
                    This unit already has {{ $existingLiveCount }} live {{ Str::plural('photo', $existingLiveCount) }}.
                @else
                    Use Live Capture or Upload, or mix both — whichever you prefer.
                @endif
            </p>
        </div>
    </div>

    <div class="mb-4 px-3.5 py-3 rounded-xl bg-[#EEF8F8] border border-[#2AA7A1]/20 flex items-start gap-2.5">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0 mt-0.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
        </svg>
        <p class="text-[12px] text-[#1F2937]/70 leading-relaxed">
            Live photos show tenants the unit is real and current, but it's optional — Live Capture and Upload are both fine, use whichever you prefer. At least 3 photos in total are required. Up to 10 new photos per save. You can add an optional caption to each.
        </p>
    </div>

    {{-- Tabs --}}
    <div class="inline-flex rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] p-1 mb-4">
        <button type="button" x-on:click="tab = 'live'"
            :class="tab === 'live' ? 'bg-white shadow-sm text-[#156F8C]' : 'text-[#64748B]'"
            class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-[12.5px] font-semibold transition-colors duration-150 cursor-pointer">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
            Live Capture
        </button>
        <button type="button" x-on:click="tab = 'upload'"
            :class="tab === 'upload' ? 'bg-white shadow-sm text-[#156F8C]' : 'text-[#64748B]'"
            class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-[12.5px] font-semibold transition-colors duration-150 cursor-pointer">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            Upload
        </button>
    </div>

    {{-- Live Capture panel --}}
    <div x-show="tab === 'live'">
        <div id="camera-shell" class="rounded-xl border border-[#64748B]/25 bg-[#0F172A] overflow-hidden relative aspect-video flex items-center justify-center">
            <video id="camera-video" autoplay playsinline muted class="hidden w-full h-full object-cover"></video>
            <canvas id="camera-canvas" class="hidden"></canvas>

            {{-- Idle / start state --}}
            <div id="camera-idle" class="text-center px-6">
                <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="1.4" class="mx-auto mb-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                </svg>
                <p class="text-[13px] font-semibold text-white">Camera is off</p>
                <p class="text-[11.5px] text-[#94A3B8] mt-0.5 mb-3">Enable your camera to capture live photos at the unit.</p>
                <button type="button" id="camera-enable"
                    class="h-9 px-4 inline-flex items-center gap-1.5 rounded-full bg-[#2AA7A1] text-white text-[12.5px] font-semibold hover:brightness-95 transition-all duration-200">
                    Enable camera
                </button>
            </div>

            {{-- Capture button (live) --}}
            <button type="button" id="camera-capture"
                class="hidden absolute bottom-3 left-1/2 -translate-x-1/2 w-14 h-14 rounded-full bg-white ring-4 ring-white/40 hover:brightness-95 transition-all flex items-center justify-center"
                aria-label="Capture photo">
                <span class="w-10 h-10 rounded-full border-2 border-[#156F8C]"></span>
            </button>
        </div>
        <p id="camera-error" class="hidden text-[11.5px] text-[#EF4444] mt-2"></p>
    </div>

    {{-- Upload panel --}}
    <div x-show="tab === 'upload'" x-cloak>
        <div id="photo-dropzone"
            class="rounded-xl border-2 border-dashed border-[#64748B]/30 bg-[#F7FCFC] px-6 py-8 text-center cursor-pointer hover:border-[#2AA7A1]/60 transition-colors duration-200">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5" class="mx-auto mb-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
            </svg>
            <p class="text-[13px] font-semibold text-[#1F2937]">Click to select photos</p>
            <p class="text-[11.5px] text-[#64748B] mt-0.5">JPEG, PNG or WEBP</p>
            <input type="file" id="upload-input" multiple accept="image/jpeg,image/png,image/webp" class="hidden" aria-label="Select additional unit photos">
        </div>
    </div>

    {{-- Live counter — informational only, no required minimum to hit --}}
    <div class="mt-4 flex items-center gap-2 text-[12px]">
        <span id="live-count-badge" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold bg-[#2AA7A1]/[0.08] text-[#156F8C] border border-[#2AA7A1]/25">
            <span id="live-count">{{ $existingLiveCount }}</span> live photo(s)
        </span>
        <span id="total-count" class="text-[#64748B]">0 new</span>
    </div>

    {{-- Hidden aggregated file input for submission --}}
    <input type="file" id="photo-input" name="photos[]" multiple class="hidden" aria-label="Unit photos">

    <p id="photo-limit-msg" class="hidden text-[11.5px] text-[#EF4444] mt-2">You can add a maximum of 10 photos at once.</p>
    @error('photos')
        <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
    @enderror
    @error('photos.*')
        <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
    @enderror

    {{-- Unified gallery (live + uploaded, this session only) --}}
    <div id="photo-gallery" class="hidden grid-cols-1 sm:grid-cols-2 gap-3 mt-4"></div>
</x-card>

<script>
    (function () {
        const MAX_PHOTOS = 10;
        const EXISTING_LIVE = {{ (int) $existingLiveCount }};

        const photoInput = document.getElementById('photo-input');
        const gallery = document.getElementById('photo-gallery');
        const limitMsg = document.getElementById('photo-limit-msg');
        const liveCountEl = document.getElementById('live-count');
        const totalCountEl = document.getElementById('total-count');

        // Upload
        const dropzone = document.getElementById('photo-dropzone');
        const uploadInput = document.getElementById('upload-input');

        // Camera
        const video = document.getElementById('camera-video');
        const canvas = document.getElementById('camera-canvas');
        const idle = document.getElementById('camera-idle');
        const enableBtn = document.getElementById('camera-enable');
        const captureBtn = document.getElementById('camera-capture');
        const cameraError = document.getElementById('camera-error');

        let photos = []; // { id, file, source, caption, url } — file/url are
        // local (blob: URLs and File objects the browser handed us), never
        // text a person typed, so building small DOM fragments from them
        // below carries no injection risk. Captions ARE user text, and are
        // wired through element.value, never interpolated into markup.
        let stream = null;

        const newId = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Math.random());

        // ── Upload ──────────────────────────────────────────────
        dropzone.addEventListener('click', () => uploadInput.click());
        uploadInput.addEventListener('change', () => {
            for (const file of uploadInput.files) addPhoto(file, 'upload');
            uploadInput.value = '';
        });

        // ── Camera ──────────────────────────────────────────────
        enableBtn.addEventListener('click', async () => {
            cameraError.classList.add('hidden');
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } }, audio: false
                });
                video.srcObject = stream;
                idle.classList.add('hidden');
                video.classList.remove('hidden');
                captureBtn.classList.remove('hidden');
            } catch (e) {
                cameraError.textContent = 'Could not access the camera (' + (e.name || 'error') + '). Check browser permissions, or use the Upload tab instead.';
                cameraError.classList.remove('hidden');
            }
        });

        captureBtn.addEventListener('click', () => {
            if (photos.length >= MAX_PHOTOS) { showLimit(); return; }
            const w = video.videoWidth, h = video.videoHeight;
            if (!w || !h) return;
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(video, 0, 0, w, h);
            canvas.toBlob((blob) => {
                if (!blob) return;
                const file = new File([blob], 'live-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                addPhoto(file, 'camera');
            }, 'image/jpeg', 0.9);
        });

        window.addEventListener('beforeunload', () => {
            if (stream) stream.getTracks().forEach(t => t.stop());
        });

        // ── Shared ──────────────────────────────────────────────
        function addPhoto(file, source) {
            if (photos.length >= MAX_PHOTOS) { showLimit(); return; }
            limitMsg.classList.add('hidden');
            photos.push({ id: newId(), file, source, caption: '', url: URL.createObjectURL(file) });
            render();
        }

        function removePhoto(id) {
            const i = photos.findIndex(p => p.id === id);
            if (i === -1) return;
            URL.revokeObjectURL(photos[i].url);
            photos.splice(i, 1);
            limitMsg.classList.add('hidden');
            render();
        }

        function showLimit() {
            limitMsg.classList.remove('hidden');
        }

        function syncInput() {
            const dt = new DataTransfer();
            photos.forEach(p => dt.items.add(p.file));
            photoInput.files = dt.files;
        }

        function updateCounters() {
            const newLive = photos.filter(p => p.source === 'camera').length;
            const totalLive = EXISTING_LIVE + newLive;
            liveCountEl.textContent = totalLive;
            totalCountEl.textContent = photos.length + ' new';
        }

        function render() {
            // Rebuild aggregated file input in the same order as the gallery
            syncInput();

            gallery.innerHTML = '';
            gallery.classList.toggle('hidden', photos.length === 0);
            gallery.classList.toggle('grid', photos.length > 0);

            photos.forEach((p) => {
                const card = document.createElement('div');
                card.className = 'rounded-xl overflow-hidden border border-[#E2E8F0] bg-white';

                const media = document.createElement('div');
                media.className = 'relative aspect-video bg-[#F7FCFC]';

                const img = document.createElement('img');
                img.src = p.url;
                img.alt = 'Unit photo';
                img.className = 'w-full h-full object-cover';
                media.appendChild(img);

                const sourceTag = document.createElement('span');
                sourceTag.className = 'absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold '
                    + (p.source === 'camera' ? 'bg-[#2AA7A1] text-white' : 'bg-white/90 text-[#1F2937] border border-[#E2E8F0]');
                sourceTag.textContent = p.source === 'camera' ? 'Live' : 'Upload';
                media.appendChild(sourceTag);

                const sourceInput = document.createElement('input');
                sourceInput.type = 'hidden';
                sourceInput.name = 'photo_sources[]';
                sourceInput.value = p.source;
                media.appendChild(sourceInput);

                const body = document.createElement('div');
                body.className = 'p-2';

                const cap = document.createElement('input');
                cap.type = 'text';
                cap.name = 'photo_captions[]';
                cap.maxLength = 150;
                cap.placeholder = 'Add a caption (optional)';
                cap.setAttribute('aria-label', 'Photo caption (optional)');
                cap.className = 'h-9 w-full rounded-lg border border-[#64748B]/25 px-2.5 text-[12px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition';
                cap.value = p.caption;
                cap.addEventListener('input', () => { p.caption = cap.value; });
                body.appendChild(cap);

                // Remove button
                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-white/90 border border-[#E2E8F0] flex items-center justify-center text-[#EF4444] hover:brightness-95 transition';
                rm.setAttribute('aria-label', 'Remove photo');
                const rmIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                rmIcon.setAttribute('width', '12');
                rmIcon.setAttribute('height', '12');
                rmIcon.setAttribute('fill', 'none');
                rmIcon.setAttribute('viewBox', '0 0 24 24');
                rmIcon.setAttribute('stroke', 'currentColor');
                rmIcon.setAttribute('stroke-width', '2');
                const rmPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                rmPath.setAttribute('stroke-linecap', 'round');
                rmPath.setAttribute('stroke-linejoin', 'round');
                rmPath.setAttribute('d', 'M6 18 18 6M6 6l12 12');
                rmIcon.appendChild(rmPath);
                rm.appendChild(rmIcon);
                rm.addEventListener('click', () => removePhoto(p.id));
                media.appendChild(rm);

                card.appendChild(media);
                card.appendChild(body);
                gallery.appendChild(card);
            });

            updateCounters();
        }

        updateCounters();
    })();
</script>
