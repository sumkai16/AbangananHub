/**
 * Verification Wizard — Alpine.js component
 *
 * Liveness detection powered by face-api.js (TensorFlow.js):
 *   - TinyFaceDetector for fast face detection
 *   - 68-point landmarks for head pose (yaw) and blink (EAR)
 *   - Cross-browser: Chrome, Firefox, Safari
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

        idCaptureMethod: null,
        selfieCaptureMethod: null,

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
        livenessSteps: ['Look straight', 'Turn left', 'Turn right', 'Open your mouth'],
        livenessCompleted: [false, false, false, false],
        livenessFaceDetected: false,
        livenessGuideColor: '#E2E8F0',
        livenessInstruction: 'Preparing face detection...',

        _livenessFrameId: null,
        _processing: false,
        _holdStart: null,
        _blinkCount: 0,
        _lastBlinkState: false,
        _stepPauseUntil: 0,
        _faceApiReady: false,

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

                if (newStep === 2) {
                    if (this.idCaptureMethod === 'camera') {
                        if (!this.idImageBase64) {
                            this.idCapturePhase = 'capture-front';
                            this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
                        } else if (this.needsBack && !this.idBackBase64) {
                            this.idCapturePhase = 'capture-back';
                            this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
                        } else {
                            this.idCapturePhase = 'done';
                        }
                    } else if (!this.idCaptureMethod) {
                        this.idCapturePhase = 'choose';
                    }
                } else if (newStep === 3) {
                    if (this.selfieCaptureMethod === 'camera' && !this.selfieBase64) {
                        this.selfieCapturePhase = 'camera';
                        this.$nextTick(() => this.startSelfieWithLiveness());
                    } else if (!this.selfieCaptureMethod) {
                        this.selfieCapturePhase = 'choose';
                    }
                }
            });

            this.$watch('idType', (newType, oldType) => {
                if (oldType && newType !== oldType) {
                    this.idBackBase64 = '';
                    this.idImageBase64 = '';
                    this.idCapturePhase = 'choose';
                    this.idCaptureMethod = null;
                }
            });
        },

        // ── Capture method selection ──────────────────────────

        chooseIdMethod(method) {
            this.idCaptureMethod = method;
            this.cameraError = '';
            if (method === 'camera') {
                this.idCapturePhase = 'capture-front';
                this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
            } else {
                this.idCapturePhase = 'capture-front';
            }
        },

        chooseSelfieMethod(method) {
            this.selfieCaptureMethod = method;
            this.cameraError = '';
            if (method === 'camera') {
                this.selfieCapturePhase = 'camera';
                this.$nextTick(() => this.startSelfieWithLiveness());
            } else {
                this.selfieCapturePhase = 'camera';
            }
        },

        switchIdMethod() {
            this.stopCamera();
            this.idCaptureMethod = null;
            this.idCapturePhase = 'choose';
            this.cameraError = '';
        },

        switchSelfieMethod() {
            this.stopCamera();
            this.stopLiveness();
            this.selfieCaptureMethod = null;
            this.selfieCapturePhase = 'choose';
            this.cameraError = '';
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
            this.livenessCompleted = [false, false, false, false];
            this.livenessPassed = false;
            this._holdStart = null;
            this._blinkCount = 0;
            this._lastBlinkState = false;
            this._stepPauseUntil = 0;
            this._processing = false;
            this._faceGoneAt = null;
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
                    // Lower detection threshold during blink step — closed eyes
                    // reduce face confidence, so we need to be more lenient
                    const threshold = this.livenessStep === 3 ? 0.25 : 0.5;

                    const detection = await faceapi
                        .detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: threshold }))
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
            if (!detection) {
                // During blink step: brief face loss counts as a blink
                if (this.livenessStep === 3 && this.livenessFaceDetected) {
                    // Face was visible last frame, now gone — start tracking disappearance
                    if (!this._faceGoneAt) this._faceGoneAt = Date.now();
                }

                this.livenessFaceDetected = false;
                this.livenessGuideColor = this.livenessStep === 3 ? '#2AA7A1' : '#E2E8F0';
                if (this.livenessStep !== 3) this._holdStart = null;
                return;
            }

            // Face reappeared after brief disappearance during blink step
            if (this.livenessStep === 3 && this._faceGoneAt) {
                const goneMs = Date.now() - this._faceGoneAt;
                if (goneMs > 50 && goneMs < 800) {
                    // Brief disappearance = blink
                    this._blinkCount++;
                    if (this._blinkCount >= 2) {
                        this._faceGoneAt = null;
                        this._completeLivenessStep();
                        return;
                    }
                    this.livenessInstruction = 'Blink twice (' + this._blinkCount + '/2)';
                }
                this._faceGoneAt = null;
            }

            this.livenessFaceDetected = true;

            if (Date.now() < this._stepPauseUntil) return;

            const positions = detection.landmarks.positions;
            const leftEye = detection.landmarks.getLeftEye();
            const rightEye = detection.landmarks.getRightEye();

            const step = this.livenessStep;
            if (step === 0) this._checkLookStraight(positions);
            else if (step === 1) this._checkTurnLeft(positions);
            else if (step === 2) this._checkTurnRight(positions);
            else if (step === 3) this._checkMouthOpen(positions);
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

        // ── Eye Aspect Ratio for blink ────────────────────────
        //
        // face-api.js eye points (6 each):
        //   [0]=outer, [1]=upper-outer, [2]=upper-inner,
        //   [3]=inner, [4]=lower-inner, [5]=lower-outer

        _getEAR(eyePoints) {
            const dist = (a, b) => Math.sqrt((a.x - b.x) ** 2 + (a.y - b.y) ** 2);
            const vertA = dist(eyePoints[1], eyePoints[5]);
            const vertB = dist(eyePoints[2], eyePoints[4]);
            const horiz = dist(eyePoints[0], eyePoints[3]);
            if (horiz < 1) return 0.3;
            return (vertA + vertB) / (2 * horiz);
        },

        // ── Action validators ─────────────────────────────────

        _checkLookStraight(positions) {
            const yaw = this._getYaw(positions);
            if (Math.abs(yaw) < 15) {
                this.livenessGuideColor = '#2AA7A1';
                if (!this._holdStart) this._holdStart = Date.now();
                else if (Date.now() - this._holdStart > 1000) this._completeLivenessStep();
            } else {
                this.livenessGuideColor = '#E2E8F0';
                this._holdStart = null;
            }
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

        _checkMouthOpen(positions) {
            // 68-point landmarks: 62 = upper inner lip, 66 = lower inner lip
            const upperLip = positions[62];
            const lowerLip = positions[66];
            const noseTip = positions[30];
            const chin = positions[8];

            // Normalize mouth opening by nose-to-chin distance (face scale)
            const faceHeight = Math.abs(chin.y - noseTip.y);
            if (faceHeight < 1) return;

            const mouthGap = Math.abs(lowerLip.y - upperLip.y);
            const mouthRatio = mouthGap / faceHeight;

            // Mouth open when ratio > 0.3 (roughly)
            const isOpen = mouthRatio > 0.3;

            if (isOpen) {
                this.livenessGuideColor = '#2AA7A1';
                if (!this._holdStart) this._holdStart = Date.now();
                else if (Date.now() - this._holdStart > 600) this._completeLivenessStep();
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

            if (this.livenessStep < 3) {
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

        // ── File upload ───────────────────────────────────────

        handleFileUpload(type, event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) { this.cameraError = 'Please select an image file (JPEG or PNG).'; return; }
            if (file.size > 10 * 1024 * 1024) { this.cameraError = 'Image is too large. Max 10MB.'; return; }
            this.cameraError = '';
            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;
                if (type === 'id') {
                    this.idImageBase64 = dataUrl;
                    if (this.needsBack) this.idCapturePhase = 'capture-back';
                    else { this.idCapturePhase = 'done'; this.runOcrCheck(); }
                } else if (type === 'idBack') {
                    this.idBackBase64 = dataUrl;
                    this.idCapturePhase = 'done';
                    this.runOcrCheck();
                } else if (type === 'selfie') {
                    this.selfieBase64 = dataUrl;
                    this.selfieCapturePhase = 'done';
                    this.faceCheckDone = true;
                    this.faceDetected = false;
                }
            };
            reader.readAsDataURL(file);
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
                if (err.name === 'NotAllowedError') this.cameraError = 'Camera access denied. Allow camera access or upload instead.';
                else if (err.name === 'NotFoundError') this.cameraError = 'No camera found on this device.';
                else this.cameraError = 'Could not access camera. Try again or upload instead.';
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
                if (this.idCaptureMethod === 'camera') {
                    this.idCapturePhase = 'capture-front';
                    this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoId), 500));
                } else {
                    this.idCapturePhase = 'capture-front';
                    this.$nextTick(() => { if (this.$refs.fileInputFront) this.$refs.fileInputFront.value = ''; });
                }
            } else if (type === 'idBack') {
                this.idBackBase64 = '';
                this.duplicateSideWarning = false;
                this.ocrResult = null;
                this.backOcrResult = null;
                this.ocrError = false;
                if (this.idCaptureMethod === 'camera') {
                    this.idCapturePhase = 'capture-back';
                    this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoIdBack), 500));
                } else {
                    this.idCapturePhase = 'capture-back';
                    this.$nextTick(() => { if (this.$refs.fileInputBack) this.$refs.fileInputBack.value = ''; });
                }
            } else {
                this.selfieBase64 = '';
                this.faceCheckDone = false;
                this.faceDetected = false;
                this.livenessPassed = false;
                this.livenessError = false;
                this.selfieCapturePhase = 'choose';
                this.selfieCaptureMethod = null;
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