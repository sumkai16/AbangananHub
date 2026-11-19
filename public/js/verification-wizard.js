/**
 * Verification Wizard — Alpine.js component
 *
 * Liveness detection powered by face-api.js (TensorFlow.js):
 *   - TinyFaceDetector for fast face detection
 *   - 68-point landmarks for head turn (yaw)
 *   - the model files are served from this site (public/models/face-api)
 *   - Cross-browser: Chrome, Firefox, Safari
 *
 * All images are captured live from the camera — there is no upload path,
 * so a selfie can't be swapped for a stored photo. When liveness can't run
 * (no camera models, unsupported browser) the applicant can still capture
 * manually, but livenessPassed stays false and admin review sees the flag.
 *
 * Config: { ocrCheckUrl: string, csrfToken: string, modelUrl?: string }
 */
function verificationWizard(config) {
    // Liveness tuning. Yaw is how far the head is turned, in degrees from the 68 landmarks (0 = facing the camera).
    const LIVENESS = {
        smoothFrames: 5,       // yaw is the mean of the last few readings, so one noisy frame cannot break a hold
        graceMs: 300,          // a dip out of pose, or a lost face, shorter than this keeps the hold
        straight: { maxYaw: 15, holdMs: 800 },
        turn: { minYaw: 20, holdMs: 500 },
        hintAfterMs: 6000,     // a step still open after this long gets a hint
        frame: {               // the last phase: face the camera again, close and centred, then the photo is taken
            maxYaw: 10,
            relaxedMaxYaw: 20, // after relaxAfterMs the size and centring rules go and this looser angle applies
            minFaceWidth: 0.30, // face box width as a share of the camera box the applicant sees (the ring is 55% of it)
            centreBand: 0.2,   // face centre within +/- this share of the camera box: its middle 40%
            stageAspect: 3 / 4, // the camera box is aspect-[3/4] and shows a centred crop of the video
            countdownFrom: 3,
            countdownStepMs: 700,
            relaxAfterMs: 12000, // so nobody is ever stuck on a camera that cannot meet the rules
            hintAfterMs: 3000,
        },
    };
    // The ID check runs on the server (two reads, one per side). A stalled connection must not leave Continue greyed out,
    // so after this long the applicant carries on unchecked and the admin sees it was not checked.
    const OCR_TIMEOUT_MS = 45000;
    const NO_FACE_HINT = 'Move into better light and fit your face in the ring';
    const TURN_ARROWS = [null, 'left', 'right']; // the arrow on the camera view, by step

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
        idBlurry: false,
        idBackBlurry: false,
        ocrError: false,
        _ocrSeq: 0,      // which ID check is the current one; an answer for an older one is ignored
        _ocrAbort: null, // aborts the check in flight (a retake, a new ID type, a newer check, or the timeout)

        faceDetected: false,
        faceCheckDone: false,

        // ── Liveness ──────────────────────────────────────────
        livenessLoading: false,
        livenessActive: false,
        livenessError: false,
        livenessPassed: false,
        livenessStep: 0,
        livenessSteps: ['Look straight', 'Turn left', 'Turn right'],
        livenessPhase: 'steps',   // 'steps' while the movements run, then 'frame' until the photo is taken
        livenessProgress: 0,      // 0 to 1: how far into the current pose, or into the countdown
        livenessHint: '',
        livenessDirection: null,  // 'left' | 'right' on the turn steps: an arrow on the camera view
        livenessCompleted: [],
        livenessFaceDetected: false,
        livenessGuideColor: '#E2E4EC',
        livenessInstruction: 'Preparing face detection...',

        _livenessFrameId: null,
        _processing: false,
        _holdStart: null,
        _stepPauseUntil: 0,
        _faceApiReady: false,
        _yawSamples: [],
        _outOfPoseSince: null,
        _noFaceSince: null,
        _stepStartedAt: 0,
        _frameStartedAt: 0,
        _frameHoldStart: null,
        _frameOutSince: null,

        get _lastLivenessStep() { return this.livenessSteps.length - 1; },

        // What the applicant is walked through: the movements, then looking at the camera for the photo. One list for
        // the ready screen, the dots on the camera view and the desktop checklist.
        get livenessChecklist() { return [...this.livenessSteps, 'Look at the camera']; },
        livenessItemDone(idx) {
            return idx < this.livenessSteps.length ? !!this.livenessCompleted[idx] : this.livenessPassed;
        },
        livenessItemCurrent(idx) {
            return idx < this.livenessSteps.length
                ? this.livenessPhase === 'steps' && this.livenessStep === idx
                : this.livenessPhase === 'frame' && !this.livenessPassed;
        },

        duplicateSideWarning: false,
        submitting: false,
        previewImage: null,

        openPreview(base64) { this.previewImage = base64; },
        closePreview() { this.previewImage = null; },

        get needsBack() { return this.idType !== 'Passport'; },

        get idCaptureComplete() {
            if (!this.idImageBase64) return false;
            if (this.needsBack && !this.idBackBase64) return false;
            // Wait for the "does this look like an ID" answer, and stop while it says retake. The server only holds
            // the applicant back for the first couple of failed tries; a network error (ocrError) never blocks.
            if (this.ocrLoading || this.idCheckBlocks) return false;
            return true;
        },

        get idCheckBlocks() { return this.ocrResult?.id_check?.blocked === true; },

        // The one card shown above the ID results, from the server's `id_check` answer: red while the applicant must
        // retake, amber when they may continue, neutral when the check could not run. Null when the ID passed.
        get idCheckCard() {
            const c = this.ocrResult?.id_check;
            if (!c) return null;

            const frontFailed = c.status === 'fail';
            const backFailed = !frontFailed && c.back_status === 'fail';

            if (frontFailed || backFailed) {
                const what = frontFailed ? 'your ' + this.idType : 'the back of your ' + this.idType;
                if (c.blocked) {
                    const tries = c.attempts_left === 1 ? '1 more try' : c.attempts_left + ' more tries';
                    return {
                        tone: 'red',
                        title: "This doesn't look like " + what,
                        body: 'Lay the ID flat, fill the frame, and avoid glare. ' + tries + ' before you can continue anyway.',
                        retake: frontFailed ? 'id' : 'idBack',
                    };
                }
                return {
                    tone: 'amber',
                    title: "We still can't confirm this is " + what,
                    body: 'You can continue, and our team will check it by hand.',
                    retake: null,
                };
            }

            if (c.status === 'unsure') {
                return { tone: 'amber', title: 'Some details were hard to read', body: 'Retake for a clearer photo, or continue.', retake: null };
            }

            if (c.status === 'unavailable') {
                return { tone: 'neutral', title: "We couldn't check this photo right now", body: 'Our team will check it by hand.', retake: null };
            }

            return null;
        },

        // Nothing was read from the front, so "What we found" would only show dashes.
        get idFrontUnread() { return this.ocrResult?.id_check?.status === 'fail'; },

        // ── Lifecycle ─────────────────────────────────────────

        init() {
            this.$watch('step', (newStep) => {
                // Start fetching the face models while the applicant photographs the ID, so the selfie step opens ready.
                if (newStep === 2) this._initFaceApi().catch(() => {});

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
                    this._cancelOcrCheck();
                    this.ocrResult = null;
                    this.ocrError = false;
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
                // A slow or blocked CDN must not leave "Preparing face detection..." spinning for good: after 20s the
                // applicant falls back to a manual capture (liveness stays unverified and the admin sees it).
                await Promise.race([
                    this._initFaceApi(),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('Face detection took too long to load')), 20000)),
                ]);
                this._startLivenessLoop(this.$refs.videoSelfie);
            } catch (err) {
                console.error('Liveness init failed:', err);
                this.livenessLoading = false;
                this.livenessError = true;
                this.livenessInstruction = '';
            }
        },

        // ── face-api.js initialization ────────────────────────

        // One shared load: the early preload and the selfie step both wait on the same promise, so the second
        // caller never sees `faceapi` before the script has finished loading.
        _initFaceApi() {
            if (this._faceApiReady) return Promise.resolve();

            if (!this._faceApiPromise) {
                this._faceApiPromise = (async () => {
                    // Load face-api.js library
                    await this._loadScript(
                        'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js'
                    );

                    // The model files are served from this site (public/models/face-api), copied from face-api.js
                    // commit a86f011d72124e5fb93e59d5c4ab98f699dd5c9c, so a change upstream can never break the face check.
                    // The full 68-point landmark net (not the tiny one) reads head turns more steadily.
                    const MODEL_URL = config.modelUrl || '/models/face-api';
                    await Promise.all([
                        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    ]);

                    this._faceApiReady = true;
                })().catch((err) => {
                    this._faceApiPromise = null; // allow a retry
                    throw err;
                });
            }

            return this._faceApiPromise;
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
            this._resetLiveness();

            const processFrame = async () => {
                if (!this.livenessActive || !this.cameraActive || this.livenessPassed) return;

                if (this._processing) {
                    this._livenessFrameId = requestAnimationFrame(processFrame);
                    return;
                }

                this._processing = true;

                try {
                    const detection = await faceapi
                        .detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                        .withFaceLandmarks();

                    this._processDetection(detection);
                } catch (e) {
                    console.warn('Detection error:', e.message);
                }

                this._processing = false;
                this._livenessFrameId = requestAnimationFrame(processFrame);
            };

            this._livenessFrameId = requestAnimationFrame(processFrame);
        },

        // Back to the first movement, with nothing left over from an earlier attempt.
        _resetLiveness() {
            this.livenessStep = 0;
            this.livenessCompleted = this.livenessSteps.map(() => false);
            this.livenessPassed = false;
            this.livenessPhase = 'steps';
            this.livenessProgress = 0;
            this.livenessHint = '';
            this.livenessDirection = TURN_ARROWS[0];
            this.livenessInstruction = this.livenessSteps[0];
            this.livenessGuideColor = '#E2E4EC';
            this.livenessFaceDetected = false;
            this.countdownValue = 0;
            this._yawSamples = [];
            this._holdStart = null;
            this._outOfPoseSince = null;
            this._noFaceSince = null;
            this._stepPauseUntil = 0;
            this._stepStartedAt = Date.now();
            this._frameStartedAt = 0;
            this._frameHoldStart = null;
            this._frameOutSince = null;
            this._processing = false;
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
            const now = Date.now();

            if (!detection) {
                // Losing the face never completes a step. A gap shorter than the grace is just a dropped frame, so it
                // keeps the hold (and the "face detected" badge) instead of restarting everything.
                this._noFaceSince = this._noFaceSince ?? now;
                if (now - this._noFaceSince > LIVENESS.graceMs) {
                    this.livenessFaceDetected = false;
                    this.livenessGuideColor = '#E2E4EC';
                    this.livenessProgress = 0;
                    this.countdownValue = 0;
                    this._holdStart = null;
                    this._outOfPoseSince = null;
                    this._frameHoldStart = null;
                    this._frameOutSince = null;
                    this._yawSamples = [];
                }
                this._updateHint(now, false, null);
                return;
            }

            this._noFaceSince = null;
            this.livenessFaceDetected = true;

            if (now < this._stepPauseUntil) return;

            const yaw = this._smoothedYaw(this._getYaw(detection.landmarks.positions));

            if (this.livenessPhase === 'frame') this._checkFrame(detection, yaw, now);
            else if (this.livenessStep === 0) this._checkLookStraight(yaw, now);
            else this._checkTurn(yaw, this.livenessStep === 1 ? -1 : 1, now);
        },

        // ── Head turn (yaw) from 68-point landmarks ──────────
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

        // The mean of the last few yaw readings: one noisy frame cannot flip a pose in or out.
        _smoothedYaw(yaw) {
            this._yawSamples.push(yaw);
            if (this._yawSamples.length > LIVENESS.smoothFrames) this._yawSamples.shift();
            return this._yawSamples.reduce((sum, value) => sum + value, 0) / this._yawSamples.length;
        },

        // ── Action validators ─────────────────────────────────

        _checkLookStraight(yaw, now) {
            const { maxYaw, holdMs } = LIVENESS.straight;
            const done = this._holdPose(Math.abs(yaw) < maxYaw, now, holdMs);
            this.livenessProgress = this._holdStart ? Math.min(1, (now - this._holdStart) / holdMs) : 0;
            if (!done) this._updateHint(now, true, null);
        },

        // direction: -1 to turn left, 1 to turn right (as the applicant sees it).
        _checkTurn(yaw, direction, now) {
            const { minYaw, holdMs } = LIVENESS.turn;
            const turned = yaw * direction;
            const done = this._holdPose(turned > minYaw, now, holdMs);
            this.livenessProgress = Math.min(1, Math.max(0, turned / minYaw));
            if (!done) this._updateHint(now, true, null);
        },

        // One pose held for holdMs. Stepping out of it for less than the grace keeps the hold; longer restarts it.
        // Returns true when the hold is complete (the step is then finished).
        _holdPose(inPose, now, holdMs) {
            if (inPose) {
                this.livenessGuideColor = '#FF8A66';
                this._outOfPoseSince = null;
                this._holdStart = this._holdStart ?? now;
                if (now - this._holdStart >= holdMs) {
                    this._completeLivenessStep(now);
                    return true;
                }
                return false;
            }

            this.livenessGuideColor = '#E2E4EC';
            this._outOfPoseSince = this._outOfPoseSince ?? now;
            if (now - this._outOfPoseSince > LIVENESS.graceMs) this._holdStart = null;
            return false;
        },

        // After a step has stayed open a while, say what to fix. faceFound is whether a face is in view right now;
        // frameState (photo phase only) says which of the three rules is not met.
        _updateHint(now, faceFound, frameState) {
            if (this.livenessPhase === 'frame') {
                if (now - this._frameStartedAt < LIVENESS.frame.hintAfterMs) return;
                this.livenessHint = !faceFound ? NO_FACE_HINT
                    : !frameState ? ''
                    : !frameState.facing ? 'Look straight at the camera'
                    : !frameState.closeEnough ? 'Move a little closer'
                    : !frameState.centred ? 'Move into the middle of the ring'
                    : '';
                return;
            }

            if (now - this._stepStartedAt < LIVENESS.hintAfterMs) return;
            this.livenessHint = !faceFound ? NO_FACE_HINT
                : this.livenessStep === 0 ? 'Look straight at the screen'
                : 'Turn a little more';
        },

        // ── Countdown ──────────────────────────────────────
        countdownValue: 0,

        // ── Step completion ───────────────────────────────────

        _completeLivenessStep(now) {
            this.livenessCompleted[this.livenessStep] = true;
            this.livenessGuideColor = '#22C55E';
            this.livenessHint = '';
            this.livenessProgress = 0;
            this._holdStart = null;
            this._outOfPoseSince = null;
            this._yawSamples = []; // the next pose starts from a clean reading, not the last pose's
            try { navigator.vibrate?.(40); } catch (e) { /* no vibration on this device */ }

            if (this.livenessStep < this._lastLivenessStep) {
                this._stepPauseUntil = now + 600;
                this._stepStartedAt = now;
                this.livenessStep++;
                this.livenessInstruction = this.livenessSteps[this.livenessStep];
                this.livenessDirection = TURN_ARROWS[this.livenessStep];

                setTimeout(() => {
                    if (this.livenessActive) this.livenessGuideColor = '#E2E4EC';
                }, 600);
            } else {
                this._startFramePhase(now);
            }
        },

        // ── The photo ─────────────────────────────────────────
        //
        // The movements are done, but the applicant is still turned. The selfie is compared with the ID photo, so it
        // is only taken once they face the camera again, close enough and in the middle of the ring.

        _startFramePhase(now) {
            this.livenessPhase = 'frame';
            this.livenessInstruction = 'Look at the camera';
            this.livenessDirection = null;
            this._frameStartedAt = now;
            this._frameHoldStart = null;
            this._frameOutSince = null;
            this.countdownValue = 0;
        },

        _checkFrame(detection, yaw, now) {
            const rules = LIVENESS.frame;
            // After a long wait only a roughly frontal face is needed, so a camera that cannot meet the size or centring
            // rules never traps the applicant on this screen.
            const relaxed = now - this._frameStartedAt > rules.relaxAfterMs;
            const box = detection.detection?.relativeBox;
            const video = this.$refs.videoSelfie;

            const facing = Math.abs(yaw) < (relaxed ? rules.relaxedMaxYaw : rules.maxYaw);

            // Size and position are judged against what the applicant SEES. The camera box is a centred 3:4 crop of the
            // video, so a landscape webcam shows only part of its width: measured against the whole video, a face that
            // fits the ring would always read as too small and the hint would push the applicant out of the ring.
            let closeEnough = true;
            let centred = true;
            if (!relaxed && box && video?.videoWidth && video?.videoHeight) {
                const visibleWidth = Math.min(video.videoWidth, video.videoHeight * rules.stageAspect);
                closeEnough = (box.width * video.videoWidth) / visibleWidth >= rules.minFaceWidth;
                centred = Math.abs((box.x + box.width / 2 - 0.5) * video.videoWidth) / visibleWidth <= rules.centreBand;
            }

            if (facing && closeEnough && centred) {
                this._frameOutSince = null;
                this.livenessGuideColor = '#FF8A66';
                this.livenessHint = '';
                this._frameHoldStart = this._frameHoldStart ?? now;

                const held = now - this._frameHoldStart;
                const total = rules.countdownFrom * rules.countdownStepMs;
                if (held >= total) {
                    this._captureSelfieNow();
                    return;
                }

                this.livenessInstruction = 'Hold still...';
                this.livenessProgress = held / total;
                this.countdownValue = rules.countdownFrom - Math.floor(held / rules.countdownStepMs);
                return;
            }

            // A wobble shorter than the grace keeps the countdown going; longer starts it again from 3.
            this.livenessGuideColor = '#E2E4EC';
            this._frameOutSince = this._frameOutSince ?? now;
            if (now - this._frameOutSince > LIVENESS.graceMs) {
                this._frameHoldStart = null;
                this.countdownValue = 0;
                this.livenessProgress = 0;
                this.livenessInstruction = 'Look at the camera';
            }
            this._updateHint(now, true, { facing, closeEnough, centred });
        },

        _captureSelfieNow() {
            this.countdownValue = 0;
            this.livenessProgress = 1;
            this.livenessHint = '';
            this.livenessGuideColor = '#22C55E';
            this.livenessInstruction = 'Captured!';
            this.livenessPassed = true;
            if (this.cameraActive) this._autoCaptureSelfie();
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
                // The back camera photographs the ID, where small print has to survive OCR: ask for more pixels.
                // The front camera only needs enough for a face.
                const idShot = facingMode === 'environment';
                this.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode,
                        width: { ideal: idShot ? 1920 : 1280 },
                        height: { ideal: idShot ? 1440 : 960 },
                    },
                    audio: false,
                });
                // Keep refocusing while the card is held up (Android Chrome); ignored where unsupported.
                if (idShot) {
                    this.cameraStream.getVideoTracks()[0]?.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
                }
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

        // How sharp a photo is: the variance of its Laplacian on a 480px grayscale copy. Low means soft edges (blur or
        // motion). A hint only; the cut-off is a starting point and nothing is blocked on it.
        _sharpness(source) {
            const w = 480;
            const h = Math.max(3, Math.round(source.height * (w / source.width)));
            const c = document.createElement('canvas');
            c.width = w;
            c.height = h;
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(source, 0, 0, w, h);
            const px = ctx.getImageData(0, 0, w, h).data;
            const g = new Float32Array(w * h);
            for (let i = 0, j = 0; i < px.length; i += 4, j++) g[j] = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];

            let sum = 0, sumSq = 0, n = 0;
            for (let y = 1; y < h - 1; y++) {
                for (let x = 1; x < w - 1; x++) {
                    const k = y * w + x;
                    const lap = -4 * g[k] + g[k - 1] + g[k + 1] + g[k - w] + g[k + w];
                    sum += lap;
                    sumSq += lap * lap;
                    n++;
                }
            }
            const mean = sum / n;
            return sumSq / n - mean * mean;
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
            // ID photos keep more detail (less compression); the selfie does not need it.
            const dataUrl = canvas.toDataURL('image/jpeg', type === 'selfie' ? 0.85 : 0.92);

            if (type === 'id') this.idBlurry = this._sharpness(canvas) < 45;
            if (type === 'idBack') this.idBackBlurry = this._sharpness(canvas) < 45;

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
            if (type === 'id' || type === 'idBack') this._cancelOcrCheck();
            if (type === 'id') {
                this.idImageBase64 = '';
                this.idBackBase64 = '';
                this.idBlurry = false;
                this.idBackBlurry = false;
                this.ocrResult = null;
                this.ocrError = false;
                this.duplicateSideWarning = false;
                this.idCapturePhase = 'capture-front';
                this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoId), 500));
            } else if (type === 'idBack') {
                this.idBackBase64 = '';
                this.idBackBlurry = false;
                this.duplicateSideWarning = false;
                this.ocrResult = null;
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
            if (!this.ocrResult?.extracted || !this.ocrResult?.back_extracted) return;
            const backText = this.ocrResult.back_extracted.toLowerCase();
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

        // Drop the check in flight: its answer would describe photos that are being replaced.
        _cancelOcrCheck() {
            this._ocrSeq++;
            if (this._ocrAbort) {
                this._ocrAbort.abort();
                this._ocrAbort = null;
            }
            this.ocrLoading = false;
        },

        async runOcrCheck() {
            if (!this.idImageBase64 || !this.idType) return;
            this._cancelOcrCheck();
            const seq = this._ocrSeq;
            const controller = new AbortController();
            this._ocrAbort = controller;
            const timer = setTimeout(() => controller.abort(), OCR_TIMEOUT_MS);
            this.ocrLoading = true; this.ocrResult = null; this.ocrError = false;
            const h = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken, 'Accept': 'application/json' };
            // One request for front and back together: the server reads each photo once and counts a failed look as
            // one try, not two.
            const body = { id_image: this.idImageBase64, id_type: this.idType };
            if (this.idBackBase64) body.id_back = this.idBackBase64;
            try {
                const r = await fetch(config.ocrCheckUrl, { method: 'POST', headers: h, body: JSON.stringify(body), signal: controller.signal });
                if (!r.ok) throw new Error('OCR failed');
                const result = await r.json();
                if (seq !== this._ocrSeq) return; // the photos changed while this ran: the answer is for photos that are gone
                this.ocrResult = result;
                if (this.idBackBase64) this.checkDuplicateSides();
            } catch (e) {
                if (seq !== this._ocrSeq) return; // cancelled on purpose, not a failure
                console.error('OCR failed:', e);
                this.ocrError = true; // also what a timeout lands on: not checked, never blocks
            } finally {
                clearTimeout(timer);
                if (seq === this._ocrSeq) {
                    this.ocrLoading = false;
                    this._ocrAbort = null;
                }
            }
        },

        nextStep() { if (this.step < 5) this.step++; },
        prevStep() { if (this.step > 0) this.step--; },
        submitForm() { this.submitting = true; this.$refs.form.submit(); },
    };
}