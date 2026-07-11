/**
 * Verification Wizard — Alpine.js component
 *
 * 5-step landlord verification wizard with camera capture,
 * OCR preview, face detection, and front/back ID support.
 *
 * Usage in Blade:
 *   <div x-data="verificationWizard(config)" x-init="init()">
 *
 * Config shape:
 *   { ocrCheckUrl: string, csrfToken: string }
 */
function verificationWizard(config) {
    return {
        step: 1,
        stepLabels: ['ID Type', 'ID Photo', 'Selfie', 'Business', 'Review'],

        // ── ID selection ──────────────────────────────────────
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

        // ── Captured images ───────────────────────────────────
        idImageBase64: '',
        idBackBase64: '',
        selfieBase64: '',

        // ── Step 2 sub-state ──────────────────────────────────
        // 'capture-front' | 'capture-back' | 'done'
        idCapturePhase: 'capture-front',

        // ── Business fields ───────────────────────────────────
        businessName: '',
        description: '',
        contactNumber: '',
        businessAddress: '',

        // ── Camera ────────────────────────────────────────────
        cameraStream: null,
        cameraActive: false,
        cameraError: '',

        // ── OCR ───────────────────────────────────────────────
        ocrLoading: false,
        ocrResult: null,
        backOcrResult: null,
        ocrError: false,

        // ── Face detection ────────────────────────────────────
        faceDetected: false,
        faceCheckDone: false,

        // ── Duplicate side detection ──────────────────────────
        duplicateSideWarning: false,

        // ── Submission ────────────────────────────────────────
        submitting: false,

        // ── Lightbox ──────────────────────────────────────────
        previewImage: null,

        openPreview(base64) {
            this.previewImage = base64;
        },

        closePreview() {
            this.previewImage = null;
        },

        // ── Computed ──────────────────────────────────────────

        get needsBack() {
            return this.idType !== 'Passport';
        },

        get idCaptureComplete() {
            if (!this.idImageBase64) return false;
            if (this.needsBack && !this.idBackBase64) return false;
            return true;
        },

        // ── Lifecycle ─────────────────────────────────────────

        init() {
            this.$watch('step', (newStep) => {
                this.stopCamera();
                this.cameraError = '';

                if (newStep === 2) {
                    if (!this.idImageBase64) {
                        this.idCapturePhase = 'capture-front';
                        this.$nextTick(() => this.startCamera('environment', this.$refs.videoId));
                    } else if (this.needsBack && !this.idBackBase64) {
                        this.idCapturePhase = 'capture-back';
                        this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
                    } else {
                        this.idCapturePhase = 'done';
                    }
                } else if (newStep === 3 && !this.selfieBase64) {
                    this.$nextTick(() => this.startCamera('user', this.$refs.videoSelfie));
                }
            });

            // Clear back image if user switches ID type
            this.$watch('idType', (newType, oldType) => {
                if (oldType && newType !== oldType) {
                    this.idBackBase64 = '';
                    this.idImageBase64 = '';
                    this.idCapturePhase = 'capture-front';
                }
            });
        },

        // ── Camera ────────────────────────────────────────────

        async startCamera(facingMode, videoEl) {
            this.cameraActive = false;
            this.cameraError = '';

            if (videoEl) {
                videoEl.srcObject = null;
            }

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

            ['videoId', 'videoIdBack', 'videoSelfie'].forEach(ref => {
                if (this.$refs[ref]) {
                    this.$refs[ref].srcObject = null;
                }
            });
        },

        // ── Photo capture ─────────────────────────────────────

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

            if (type === 'selfie') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }

            ctx.drawImage(video, 0, 0);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

            if (type === 'id') {
                this.idImageBase64 = dataUrl;
                this.stopCamera();

                if (this.needsBack) {
                    this.idCapturePhase = 'capture-back';
                    this.cameraError = '';
                    this.$nextTick(() => this.startCamera('environment', this.$refs.videoIdBack));
                } else {
                    this.idCapturePhase = 'done';
                    this.runOcrCheck();
                }
            } else if (type === 'idBack') {
                this.idBackBase64 = dataUrl;
                this.idCapturePhase = 'done';
                this.stopCamera();
                this.runOcrCheck();
            } else {
                this.selfieBase64 = dataUrl;
                this.detectFace(canvas);
                this.stopCamera();
            }
        },

        retakePhoto(type) {
            this.stopCamera();
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
                this.$nextTick(() => setTimeout(() => this.startCamera('user', this.$refs.videoSelfie), 500));
            }
        },

        // ── Face detection (FaceDetector API) ─────────────────

        async detectFace(canvas) {
            this.faceCheckDone = false;
            this.faceDetected = false;

            if (!('FaceDetector' in window)) {
                return;
            }

            try {
                const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
                const faces = await detector.detect(canvas);
                this.faceDetected = faces.length > 0;
            } catch (err) {
                console.error('Face detection error:', err);
                this.faceDetected = true;
            }

            this.faceCheckDone = true;
        },

        // ── Duplicate side detection (OCR-based) ──────────────
        //
        // Pixel comparison is unreliable — two photos of the same
        // card side from slightly different angles produce different
        // enough pixel data to slip past any threshold. Instead,
        // check the OCR text: front and back of PH IDs have
        // completely different label text, so if the "back" photo
        // contains front-side labels but no back-side labels, the
        // user photographed the same side twice.

        checkDuplicateSides() {
            this.duplicateSideWarning = false;

            if (!this.ocrResult?.extracted || !this.backOcrResult?.extracted) return;

            const backText = this.backOcrResult.extracted.toLowerCase();

            // Labels that only appear on the FRONT of PH IDs
            const frontLabels = [
                'apellido', 'last name', 'mga pangalan', 'given name',
                'gitnang', 'middle name',
            ];

            // Labels that only appear on the BACK of PH IDs
            const backLabels = [
                'kasarian', 'sex', 'uri ng dugo', 'blood type',
                'kalagayang sibil', 'marital status',
                'petsa ng pagkakaloob', 'date of issue',
                'lugar ng kapanganakan', 'place of birth',
            ];

            const hasFrontLabels = frontLabels.some(l => backText.includes(l));
            const hasBackLabels = backLabels.some(l => backText.includes(l));

            // If the "back" image has front labels but no back labels,
            // the user almost certainly photographed the front twice.
            if (hasFrontLabels && !hasBackLabels) {
                this.duplicateSideWarning = true;
            }
        },

        // ── Front ID name parser ──────────────────────────────

        parseNames(text) {
            if (!text) return { lastName: null, firstName: null, middleName: null };

            const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);

            let lastName = null;
            let firstName = null;
            let middleName = null;

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                const nextLine = i + 1 < lines.length ? lines[i + 1].trim() : null;

                // Last Name — after "Apellido" or "Last Name" label
                if (!lastName && /Apel[a-z]*|Last Name/i.test(line)) {
                    // Value on same line
                    const inline = line.match(/(?:Apel[a-z]*\/Last Name|Last Name|Apel[a-z]*)\s*[:/]?\s+([A-Z][A-Z\s-]+?)$/i);
                    if (inline) {
                        lastName = inline[1].trim();
                    } else if (nextLine && /^[A-Z][A-Z\s-]+$/.test(nextLine) && !/Pangalan|Given|Middle|Gitnang/i.test(nextLine)) {
                        lastName = nextLine;
                    }
                }

                // First Name — after "Mga Pangalan" or "Given Names" label
                if (!firstName && /Mga Pangalan|Given Name/i.test(line)) {
                    const inline = line.match(/(?:Mga Pangalan\/Given Names?|Given Names?|Mga Pangalan)\s*[:/]?\s+([A-Z][A-Z\s-]+?)$/i);
                    if (inline) {
                        firstName = inline[1].trim();
                    } else if (nextLine && /^[A-Z][A-Z\s-]+$/.test(nextLine) && !/Gitnang|Middle|Apel/i.test(nextLine)) {
                        firstName = nextLine;
                    }
                }

                // Middle Name — after "Gitnang Apelyido" or "Middle Name" label
                if (!middleName && /Gitnang Apel|Middle Name/i.test(line)) {
                    const inline = line.match(/(?:Gitnang Apel[a-z]*\/Middle Name|Middle Name|Gitnang Apel[a-z]*)\s*[:/]?\s+([A-Z][A-Z\s-]+?)$/i);
                    if (inline) {
                        middleName = inline[1].trim();
                    } else if (nextLine && /^[A-Z][A-Z\s-]+$/.test(nextLine) && !/Petsa|Date|Tirahan|Address/i.test(nextLine)) {
                        middleName = nextLine;
                    }
                }
            }

            return { lastName, firstName, middleName };
        },

        // ── OCR preview ───────────────────────────────────────

        async runOcrCheck() {
            if (!this.idImageBase64 || !this.idType) return;

            this.ocrLoading = true;
            this.ocrResult = null;
            this.backOcrResult = null;
            this.ocrError = false;

            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
            };

            try {
                // Front OCR
                const frontResponse = await fetch(config.ocrCheckUrl, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        id_image: this.idImageBase64,
                        id_type: this.idType,
                    }),
                });

                if (!frontResponse.ok) throw new Error('Front OCR request failed');
                this.ocrResult = await frontResponse.json();

                // Back OCR (if back image exists)
                if (this.idBackBase64) {
                    try {
                        const backResponse = await fetch(config.ocrCheckUrl, {
                            method: 'POST',
                            headers,
                            body: JSON.stringify({
                                id_image: this.idBackBase64,
                                id_type: this.idType,
                            }),
                        });

                        if (backResponse.ok) {
                            this.backOcrResult = await backResponse.json();
                            this.checkDuplicateSides();
                        }
                    } catch (backErr) {
                        console.error('Back OCR failed:', backErr);
                    }
                }
            } catch (err) {
                console.error('OCR check failed:', err);
                this.ocrError = true;
            } finally {
                this.ocrLoading = false;
            }
        },

        // ── Navigation ────────────────────────────────────────

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