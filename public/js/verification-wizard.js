/**
 * Verification Wizard — Alpine.js component
 *
 * Liveness detection powered by face-api.js (TensorFlow.js):
 *   - TinyFaceDetector for fast face detection
 *   - 68-point landmarks for head pose (yaw + pitch)
 *   - Cross-browser: Chrome, Firefox, Safari
 *
 * All images are captured live from the camera — there is no upload path,
 * so a selfie can't be swapped for a stored photo. When liveness can't run
 * (no camera models, unsupported browser) the applicant can still capture
 * manually, but livenessPassed stays false and admin review sees the flag.
 *
 * Config: { ocrCheckUrl: string, csrfToken: string }
 */
function verificationWizard(config) {
    return {
        step: 0,
        totalSteps: 5,

        idTypes: [
            'PhilSys', 'Professional ID Card', "Driver's License",
            'Passport', 'UMID', 'Postal ID', 'SSS ID',
        ],
        idType: '',

        idImageBase64: '',
        idBackBase64: '',
        selfieBase64: '',

        idCapturePhase: 'choose',
        selfieCapturePhase: 'choose',

        businessName: '',
        description: '',
        contactNumber: '',
        businessAddress: '',

        cameraStream: null,
        cameraActive: false,
        cameraError: '',

        ocrLoading: false,
        ocrResult: null,
        backOcrResult: null,
        ocrError: false,

        faceDetected: false,
        faceCheckDone: false,

        // ── Liveness ──────────────────────────────────────────
        livenessLoading: false,
        livenessActive: false,
        livenessError: false,
        livenessPassed: false,
        livenessStep: 0,
        livenessSteps: ['Look straight', 'Turn left', 'Turn right', 'Look up'],
        livenessCompleted: [],
        livenessFaceDetected: false,
        livenessGuideColor: '#E2E8F0',
        livenessInstruction: 'Preparing face detection...',

        _livenessFrameId: null,
        _processing: false,
        _holdStart: null,
        _stepPauseUntil: 0,
        _faceApiReady: false,
        _pitchSamples: [],
        _pitchBaseline: null,

        get _lastLivenessStep() { return this.livenessSteps.length - 1; },

        duplicateSideWarning: false,
        submitting: false,
        previewImage: null,

        openPreview(base64) { this.previewImage = base64; },
        closePreview() { this.previewImage = null; },

        get needsBack() { return this.idType !== 'Passport'; },

        get idCaptureComplete() {
            if (!this.idImageBase64) return false;
            if (this.needsBack && !this.idBackBase64) return false;
            return true;
        },

        // ── Lifecycle ─────────────────────────────────────────

        init() {
            this.$watch('step', (newStep) => {
                this.stopCamera();
                this.stopLiveness();
                this.cameraError = '';

                if (newStep === 2 && this.idCapturePhase !== 'choose') {
                    if (!this.idImageBase64) {
                        this.idCapturePhase = 'capture-front';
                        this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
                    } else if (this.needsBack && !this.idBackBase64) {
                        this.idCapturePhase = 'capture-back';
                        this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
                    } else {
                        this.idCapturePhase = 'done';
                    }
                } else if (newStep === 3 && this.selfieCapturePhase !== 'choose' && !this.selfieBase64) {
                    this.selfieCapturePhase = 'camera';
                    this.$nextTick(() => this.startSelfieWithLiveness());
                }
            });

            this.$watch('idType', (newType, oldType) => {
                if (oldType && newType !== oldType) {
                    this.idBackBase64 = '';
                    this.idImageBase64 = '';
                    this.idCapturePhase = 'choose';
                }
            });
        },

        // ── Starting a capture ────────────────────────────────

        startIdCapture() {
            this.cameraError = '';
            this.idCapturePhase = 'capture-front';
            this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
        },

        startSelfieCapture() {
            this.cameraError = '';
            this.selfieCapturePhase = 'camera';
            this.$nextTick(() => this.startSelfieWithLiveness());
        },

        // ── Selfie with liveness ──────────────────────────────

        async startSelfieWithLiveness() {
            await this.startCamera('user', this.$refs.videoSelfie);
            if (!this.cameraActive) return;

            this.livenessLoading = true;
            this.livenessInstruction = 'Preparing face detection...';

            try {
                await this._initFaceApi();
                this._startLivenessLoop(this.$refs.videoSelfie);
            } catch (err) {
                console.error('Liveness init failed:', err);
                this.livenessLoading = false;
                this.livenessError = true;
                this.livenessInstruction = '';
            }
        },

        // ── face-api.js initialization ────────────────────────

        async _initFaceApi() {
            if (this._faceApiReady) return;

            // Load face-api.js library
            await this._loadScript(
                'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js'
            );

            // Load detection models
            const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights';
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL);

            this._faceApiReady = true;
        },

        _loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
                const s = document.createElement('script');
                s.src = src;
                s.crossOrigin = 'anonymous';
                s.onload = resolve;
                s.onerror = () => reject(new Error('Failed to load: ' + src));
                document.head.appendChild(s);
            });
        },

        // ── Liveness frame loop ───────────────────────────────

        _startLivenessLoop(videoEl) {
            this.livenessLoading = false;
            this.livenessActive = true;
            this.livenessStep = 0;
            this.livenessCompleted = this.livenessSteps.map(() => false);
            this.livenessPassed = false;
            this._holdStart = null;
            this._stepPauseUntil = 0;
            this._processing = false;
            this._pitchSamples = [];
            this._pitchBaseline = null;
            this.livenessInstruction = this.livenessSteps[0];
            this.livenessGuideColor = '#E2E8F0';

            const processFrame = async () => {
                if (!this.livenessActive || !this.cameraActive || this.livenessPassed) return;

                if (this._processing) {
                    this._livenessFrameId = requestAnimationFrame(processFrame);
                    return;
                }

                this._processing = true;

                try {
                    const detection = await faceapi
                        .detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.5 }))
                        .withFaceLandmarks(true); // true = use tiny model

                    this._processDetection(detection);
                } catch (e) {
                    console.warn('Detection error:', e.message);
                }

                this._processing = false;
                this._livenessFrameId = requestAnimationFrame(processFrame);
            };

            this._livenessFrameId = requestAnimationFrame(processFrame);
        },

        stopLiveness() {
            this.livenessActive = false;
            if (this._livenessFrameId) {
                cancelAnimationFrame(this._livenessFrameId);
                this._livenessFrameId = null;
            }
            this._processing = false;
        },

        // ── Detection processing ──────────────────────────────

        _processDetection(detection) {
            // Losing the face always resets the hold — no step may complete
            // while we can't see the applicant.
            if (!detection) {
                this.livenessFaceDetected = false;
                this.livenessGuideColor = '#E2E8F0';
                this._holdStart = null;
                return;
            }

            this.livenessFaceDetected = true;

            if (Date.now() < this._stepPauseUntil) return;

            const positions = detection.landmarks.positions;

            const step = this.livenessStep;
            if (step === 0) this._checkLookStraight(positions);
            else if (step === 1) this._checkTurnLeft(positions);
            else if (step === 2) this._checkTurnRight(positions);
            else if (step === 3) this._checkLookUp(positions);
        },

        // ── Head pose (yaw) from 68-point landmarks ──────────
        //
        // Uses nose tip (30) relative to jaw edges (0, 16).
        // Pixel coordinates — normalize by face width.
        // Front camera: invert for user perspective.

        _getYaw(positions) {
            const nose = positions[30];
            const leftJaw = positions[0];
            const rightJaw = positions[16];

            const faceWidth = rightJaw.x - leftJaw.x;
            if (Math.abs(faceWidth) < 1) return 0;

            const faceCenterX = (leftJaw.x + rightJaw.x) / 2;
            const noseOffset = nose.x - faceCenterX;

            return -(noseOffset / (faceWidth / 2)) * 90;
        },

        // ── Head pitch from 68-point landmarks ────────────────
        //
        // There's no clean 2D formula for pitch the way there is for yaw, so
        // we use the proportion of the face that sits above the nose:
        //
        //   (nose.y - eyeLine.y) / (chin.y - eyeLine.y)
        //
        // Scale-invariant, but its resting value shifts with camera height and
        // face shape — so we never compare it to a fixed number. Step 0 records
        // the applicant's own resting value and step 3 looks for a delta from
        // that. Tilting back exposes the underside of the face: eye→nose
        // compresses while nose→chin expands, so the ratio DROPS when looking
        // up. Flip PITCH_UP_SIGN if a device ever reports it the other way.

        _getPitch(positions) {
            const eyeLineY = (positions[36].y + positions[39].y + positions[42].y + positions[45].y) / 4;
            const chinY = positions[8].y;
            const noseY = positions[30].y;

            const span = chinY - eyeLineY;
            if (Math.abs(span) < 1) return null;

            return (noseY - eyeLineY) / span;
        },

        // ── Action validators ─────────────────────────────────

        _checkLookStraight(positions) {
            const yaw = this._getYaw(positions);
            if (Math.abs(yaw) < 15) {
                this.livenessGuideColor = '#2AA7A1';

                // Sample the resting pitch while the head is held straight —
                // this becomes the baseline the "Look up" step measures against.
                const pitch = this._getPitch(positions);
                if (pitch !== null) this._pitchSamples.push(pitch);

                if (!this._holdStart) this._holdStart = Date.now();
                else if (Date.now() - this._holdStart > 1000) {
                    this._pitchBaseline = this._median(this._pitchSamples);
                    this._completeLivenessStep();
                }
            } else {
                this.livenessGuideColor = '#E2E8F0';
                this._pitchSamples = [];
                this._holdStart = null;
            }
        },

        _median(values) {
            if (!values.length) return null;
            const sorted = [...values].sort((a, b) => a - b);
            const mid = Math.floor(sorted.length / 2);
            return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        },

        _checkTurnLeft(positions) {
            const yaw = this._getYaw(positions);
            if (yaw < -20) {
                this.livenessGuideColor = '#2AA7A1';
                if (!this._holdStart) this._holdStart = Date.now();
                else if (Date.now() - this._holdStart > 500) this._completeLivenessStep();
            } else {
                this.livenessGuideColor = '#E2E8F0';
                this._holdStart = null;
            }
        },

        _checkTurnRight(positions) {
            const yaw = this._getYaw(positions);
            if (yaw > 20) {
                this.livenessGuideColor = '#2AA7A1';
                if (!this._holdStart) this._holdStart = Date.now();
                else if (Date.now() - this._holdStart > 500) this._completeLivenessStep();
            } else {
                this.livenessGuideColor = '#E2E8F0';
                this._holdStart = null;
            }
        },

        _checkLookUp(positions) {
            const PITCH_UP_SIGN = -1;   // ratio drops as the head tilts back
            const PITCH_DELTA = 0.07;   // how far from resting counts as "up"

            if (this._pitchBaseline === null) return;

            const pitch = this._getPitch(positions);
            if (pitch === null) return;

            // Require a roughly frontal head — otherwise a leftover turn from
            // the previous step can skew the ratio enough to pass on its own.
            const facingForward = Math.abs(this._getYaw(positions)) < 25;
            const delta = (pitch - this._pitchBaseline) * PITCH_UP_SIGN;

            if (facingForward && delta > PITCH_DELTA) {
                this.livenessGuideColor = '#2AA7A1';
                if (!this._holdStart) this._holdStart = Date.now();
                else if (Date.now() - this._holdStart > 500) this._completeLivenessStep();
            } else {
                this.livenessGuideColor = '#E2E8F0';
                this._holdStart = null;
            }
        },

        // ── Countdown ──────────────────────────────────────
        countdownValue: 0,

        // ── Step completion ───────────────────────────────────

        _completeLivenessStep() {
            this.livenessCompleted[this.livenessStep] = true;
            this.livenessGuideColor = '#22C55E';
            this._holdStart = null;

            if (this.livenessStep < this._lastLivenessStep) {
                this._stepPauseUntil = Date.now() + 600;
                this.livenessStep++;
                this.livenessInstruction = this.livenessSteps[this.livenessStep];

                setTimeout(() => {
                    if (this.livenessActive) this.livenessGuideColor = '#E2E8F0';
                }, 600);
            } else {
                this.livenessPassed = true;
                this.livenessInstruction = 'Hold still...';
                this._startCountdown();
            }
        },

        _startCountdown() {
            this.countdownValue = 3;
            this.livenessGuideColor = '#22C55E';

            const tick = () => {
                if (this.countdownValue > 1) {
                    this.countdownValue--;
                    setTimeout(tick, 700);
                } else {
                    this.countdownValue = 0;
                    this.livenessInstruction = 'Captured!';
                    if (this.cameraActive) this._autoCaptureSelfie();
                }
            };

            setTimeout(tick, 700);
        },

        _autoCaptureSelfie() {
            const video = this.$refs.videoSelfie;
            const canvas = this.$refs.canvasSelfie;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0);

            this.selfieBase64 = canvas.toDataURL('image/jpeg', 0.85);
            this.selfieCapturePhase = 'done';
            this.faceCheckDone = true;
            this.faceDetected = true;
            this.stopCamera();
            this.stopLiveness();
        },

        skipLiveness() {
            this.stopLiveness();
            this.livenessError = false;
            this.livenessLoading = false;
            this.livenessActive = false;
        },

        // ── Camera ────────────────────────────────────────────

        async startCamera(facingMode, videoEl) {
            this.cameraActive = false;
            this.cameraError = '';
            if (videoEl) videoEl.srcObject = null;
            try {
                this.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode, width: { ideal: 1280 }, height: { ideal: 960 } },
                    audio: false,
                });
                videoEl.srcObject = this.cameraStream;
                await videoEl.play();
                this.cameraActive = true;
            } catch (err) {
                console.error('Camera error:', err);
                if (err.name === 'NotAllowedError') this.cameraError = 'Camera access denied. Allow camera access in your browser settings, then try again.';
                else if (err.name === 'NotFoundError') this.cameraError = 'No camera found on this device. Photos must be taken live — please continue on a phone or a device with a camera.';
                else this.cameraError = 'Could not access camera. Close any other app using it and try again.';
            }
        },

        stopCamera() {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(t => t.stop());
                this.cameraStream = null;
            }
            this.cameraActive = false;
            ['videoId', 'videoIdBack', 'videoSelfie'].forEach(ref => {
                if (this.$refs[ref]) this.$refs[ref].srcObject = null;
            });
        },

        capturePhoto(type) {
            const refs = {
                id: { video: this.$refs.videoId, canvas: this.$refs.canvasId },
                idBack: { video: this.$refs.videoIdBack, canvas: this.$refs.canvasIdBack },
                selfie: { video: this.$refs.videoSelfie, canvas: this.$refs.canvasSelfie },
            };
            const { video, canvas } = refs[type];
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            if (type === 'selfie') { ctx.translate(canvas.width, 0); ctx.scale(-1, 1); }
            ctx.drawImage(video, 0, 0);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

            if (type === 'id') {
                this.idImageBase64 = dataUrl;
                this.stopCamera();
                if (this.needsBack) {
                    this.idCapturePhase = 'capture-back';
                    this.cameraError = '';
                    this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
                } else { this.idCapturePhase = 'done'; this.runOcrCheck(); }
            } else if (type === 'idBack') {
                this.idBackBase64 = dataUrl;
                this.idCapturePhase = 'done';
                this.stopCamera();
                this.runOcrCheck();
            } else {
                this.selfieBase64 = dataUrl;
                this.selfieCapturePhase = 'done';
                this.stopCamera();
                this.stopLiveness();
                this.faceCheckDone = true;
                this.faceDetected = false;
            }
        },

        retakePhoto(type) {
            this.stopCamera();
            this.stopLiveness();
            this.cameraError = '';
            if (type === 'id') {
                this.idImageBase64 = '';
                this.idBackBase64 = '';
                this.ocrResult = null;
                this.backOcrResult = null;
                this.ocrError = false;
                this.duplicateSideWarning = false;
                this.idCapturePhase = 'capture-front';
                this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoId), 500));
            } else if (type === 'idBack') {
                this.idBackBase64 = '';
                this.duplicateSideWarning = false;
                this.ocrResult = null;
                this.backOcrResult = null;
                this.ocrError = false;
                this.idCapturePhase = 'capture-back';
                this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoIdBack), 500));
            } else {
                this.selfieBase64 = '';
                this.faceCheckDone = false;
                this.faceDetected = false;
                this.livenessPassed = false;
                this.livenessError = false;
                this.selfieCapturePhase = 'choose';
            }
        },

        // ── Duplicate side detection ──────────────────────────

        checkDuplicateSides() {
            this.duplicateSideWarning = false;
            if (!this.ocrResult?.extracted || !this.backOcrResult?.extracted) return;
            const backText = this.backOcrResult.extracted.toLowerCase();
            const fl = ['apellido', 'last name', 'mga pangalan', 'given name', 'gitnang', 'middle name'];
            const bl = ['kasarian', 'sex', 'uri ng dugo', 'blood type', 'kalagayang sibil', 'marital status', 'petsa ng pagkakaloob', 'date of issue', 'lugar ng kapanganakan', 'place of birth'];
            if (fl.some(l => backText.includes(l)) && !bl.some(l => backText.includes(l))) this.duplicateSideWarning = true;
        },

        parseNames(text) {
            if (!text) return { lastName: null, firstName: null, middleName: null };
            const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
            let lastName = null, firstName = null, middleName = null;
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                const nl = i + 1 < lines.length ? lines[i + 1].trim() : null;
                if (!lastName && /Apel[a-z]*|Last Name/i.test(line)) {
                    const m = line.match(/(?:Apel[a-z]*\/Last Name|Last Name|Apel[a-z]*)\s*[:/]?\s+([A-Z][A-Z\s-]+?)$/i);
                    if (m) lastName = m[1].trim();
                    else if (nl && /^[A-Z][A-Z\s-]+$/.test(nl) && !/Pangalan|Given|Middle|Gitnang/i.test(nl)) lastName = nl;
                }
                if (!firstName && /Mga Pangalan|Given Name/i.test(line)) {
                    const m = line.match(/(?:Mga Pangalan\/Given Names?|Given Names?|Mga Pangalan)\s*[:/]?\s+([A-Z][A-Z\s-]+?)$/i);
                    if (m) firstName = m[1].trim();
                    else if (nl && /^[A-Z][A-Z\s-]+$/.test(nl) && !/Gitnang|Middle|Apel/i.test(nl)) firstName = nl;
                }
                if (!middleName && /Gitnang Apel|Middle Name/i.test(line)) {
                    const m = line.match(/(?:Gitnang Apel[a-z]*\/Middle Name|Middle Name|Gitnang Apel[a-z]*)\s*[:/]?\s+([A-Z][A-Z\s-]+?)$/i);
                    if (m) middleName = m[1].trim();
                    else if (nl && /^[A-Z][A-Z\s-]+$/.test(nl) && !/Petsa|Date|Tirahan|Address/i.test(nl)) middleName = nl;
                }
            }
            return { lastName, firstName, middleName };
        },

        async runOcrCheck() {
            if (!this.idImageBase64 || !this.idType) return;
            this.ocrLoading = true; this.ocrResult = null; this.backOcrResult = null; this.ocrError = false;
            const h = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken, 'Accept': 'application/json' };
            try {
                const r = await fetch(config.ocrCheckUrl, { method: 'POST', headers: h, body: JSON.stringify({ id_image: this.idImageBase64, id_type: this.idType }) });
                if (!r.ok) throw new Error('OCR failed');
                this.ocrResult = await r.json();
                if (this.idBackBase64) {
                    try {
                        const br = await fetch(config.ocrCheckUrl, { method: 'POST', headers: h, body: JSON.stringify({ id_image: this.idBackBase64, id_type: this.idType }) });
                        if (br.ok) { this.backOcrResult = await br.json(); this.checkDuplicateSides(); }
                    } catch (e) { console.error('Back OCR failed:', e); }
                }
            } catch (e) { console.error('OCR failed:', e); this.ocrError = true; }
            finally { this.ocrLoading = false; }
        },

        nextStep() { if (this.step < 5) this.step++; },
        prevStep() { if (this.step > 0) this.step--; },
        submitForm() { this.submitting = true; this.$refs.form.submit(); },
    };
}