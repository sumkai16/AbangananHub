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

                // Auto-transition to back capture
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
                this.compareSides();
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
                this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoId), 300));
            } else if (type === 'idBack') {
                this.idBackBase64 = '';
                this.duplicateSideWarning = false;
                this.ocrResult = null;
                this.backOcrResult = null;
                this.ocrError = false;
                this.idCapturePhase = 'capture-back';
                this.$nextTick(() => setTimeout(() => this.startCamera('environment', this.$refs.videoIdBack), 300));
            } else {
                this.selfieBase64 = '';
                this.faceCheckDone = false;
                this.faceDetected = false;
                this.$nextTick(() => setTimeout(() => this.startCamera('user', this.$refs.videoSelfie), 300));
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

        // ── Duplicate side detection ──────────────────────────

        compareSides() {
            this.duplicateSideWarning = false;

            if (!this.idImageBase64 || !this.idBackBase64) return;

            const size = 32;
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            const getPixels = (src) => {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => {
                        ctx.clearRect(0, 0, size, size);
                        ctx.drawImage(img, 0, 0, size, size);
                        const data = ctx.getImageData(0, 0, size, size).data;
                        resolve(data);
                    };
                    img.src = src;
                });
            };

            Promise.all([getPixels(this.idImageBase64), getPixels(this.idBackBase64)])
                .then(([frontPixels, backPixels]) => {
                    let totalDiff = 0;
                    const pixelCount = size * size;

                    for (let i = 0; i < frontPixels.length; i += 4) {
                        const dr = Math.abs(frontPixels[i] - backPixels[i]);
                        const dg = Math.abs(frontPixels[i + 1] - backPixels[i + 1]);
                        const db = Math.abs(frontPixels[i + 2] - backPixels[i + 2]);
                        totalDiff += (dr + dg + db) / (3 * 255);
                    }

                    const similarity = 1 - (totalDiff / pixelCount);
                    this.duplicateSideWarning = similarity > 0.85;
                })
                .catch(() => { });
        },

        // ── Back ID field parser ──────────────────────────────

        parseBackInfo(text) {
            if (!text) return null;

            const extract = (patterns) => {
                for (const pattern of patterns) {
                    const match = text.match(pattern);
                    if (match) return match[1].trim();
                }
                return null;
            };

            return {
                sex: extract([/(?:Sex|Kasarian)\s*[:/]?\s*(MALE|FEMALE|M|F)/i]),
                bloodType: extract([/(?:Blood Type|Uri ng Dugo)\s*[:/]?\s*(\S+)/i]),
                maritalStatus: extract([/(?:Marital Status|Kalagayang Sibil)\s*[:/]?\s*(SINGLE|MARRIED|WIDOWED|SEPARATED|DIVORCED)/i]),
                placeOfBirth: extract([/(?:Place of Birth|Lugar ng Kapanganakan)\s*[:/]?\s*([A-Z][A-Z\s,]+)/i]),
                dateOfIssue: extract([/(?:Date of Issue|Petsa ng Pagkakaloob)\s*[:/]?\s*(\d{2}\s+\w+\s+\d{4}|\d{2}\/\d{2}\/\d{4})/i]),
            };
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