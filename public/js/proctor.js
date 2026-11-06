/**
 * AI proctoring that runs entirely on the candidate's device.
 *
 * Talking  = the microphone hears voice AND the candidate's own lips move
 *            (so a neighbour talking does not count against them).
 * Head turn = the face is turned sideways (~30°+) for 2 s, or the face
 *            leaves the camera for 3 s.
 *
 * Nothing is recorded or uploaded. A detection is announced as a
 * `proctor:violation` event on `document` with detail `{ type }`.
 */

const TICK_MS = 100;              // audio sampled every tick
const FACE_EVERY_MS = 150;        // face model ~6-7 times a second
const WINDOW_MS = 2500;           // talking is judged over this window
const WARMUP_MS = 3000;           // learn the room's background noise first
const RESUME_GRACE_MS = 3000;     // quiet period after a warning is closed

const VOICE_DB_ABOVE_NOISE = 12;  // how much louder than the room counts as voice
const VOICE_FRACTION = 0.5;       // share of the window with voice
const VOICE_FRACTION_AUDIO_ONLY = 0.75;
const MOUTH_RANGE = 0.1;          // jaw movement (0-1) within the window
const HEAD_YAW_LIMIT = 0.27;      // nose offset between cheeks; 0.27 ≈ 30° turn
const HEAD_TURN_MS = 2000;
const FACE_MISSING_MS = 3000;

export class Proctor {
    /**
     * @param {{ base: string, video: HTMLVideoElement, onStatus?: (s: object) => void }} options
     */
    constructor({ base, video, onStatus }) {
        this.base = base.replace(/\/$/, '');
        this.video = video;
        this.onStatus = onStatus || (() => {});
        this.status = { face: 'loading', mic: 'waiting' };

        this.landmarker = null;
        this.audio = null;
        this.floor = null;
        this.voice = [];
        this.mouth = [];
        this.startedAt = 0;
        this.lastFaceAt = 0;
        this.lastVideoTime = -1;
        this.turnedSince = null;
        this.missingSince = null;
        this.faceSeen = false;
        this.held = false;
        this.quietUntil = 0;

        this.loadFaceModel();
        this.timer = setInterval(() => this.tick(), TICK_MS);
    }

    /** Use this camera + microphone stream (call again if it changes). */
    setStream(stream) {
        this.stopAudio();
        const track = stream.getAudioTracks()[0];
        if (!track) {
            this.setStatus({ mic: 'missing' });
            return;
        }
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = new Ctx();
        const source = ctx.createMediaStreamSource(new MediaStream([track]));
        const analyser = ctx.createAnalyser();
        analyser.fftSize = 2048;
        analyser.smoothingTimeConstant = 0.2;
        source.connect(analyser);

        const binHz = ctx.sampleRate / analyser.fftSize;
        this.audio = {
            ctx, source, analyser,
            buf: new Float32Array(analyser.frequencyBinCount),
            lo: Math.floor(300 / binHz),   // speech band
            hi: Math.ceil(3400 / binHz),
        };
        this.floor = null;
        this.startedAt = performance.now();
        this.resetWindows();
        this.setStatus({ mic: 'ok' });

        // Browsers may start audio suspended until the page is clicked.
        const resume = () => ctx.state === 'suspended' && ctx.resume().catch(() => {});
        resume();
        ['pointerdown', 'keydown'].forEach(e => document.addEventListener(e, resume, { passive: true }));
    }

    /** Stop detecting (e.g. while a warning is on screen). */
    hold() {
        this.held = true;
    }

    /** Start detecting again after a short quiet period. */
    release() {
        this.held = false;
        this.quietUntil = performance.now() + RESUME_GRACE_MS;
        this.resetWindows();
    }

    /* ---------- internals ---------- */

    async loadFaceModel() {
        try {
            const vision = await import(this.base + '/vision_bundle.mjs');
            const files = await vision.FilesetResolver.forVisionTasks(this.base + '/wasm');
            const options = delegate => ({
                baseOptions: { modelAssetPath: this.base + '/models/face_landmarker.task', delegate },
                runningMode: 'VIDEO',
                numFaces: 1,
                outputFaceBlendshapes: true,
            });
            try {
                this.landmarker = await vision.FaceLandmarker.createFromOptions(files, options('GPU'));
            } catch (e) {
                this.landmarker = await vision.FaceLandmarker.createFromOptions(files, options('CPU'));
            }
            this.setStatus({ face: 'ready' });
        } catch (e) {
            console.warn('Proctoring: face model unavailable', e);
            this.setStatus({ face: 'unavailable' });
        }
    }

    setStatus(patch) {
        Object.assign(this.status, patch);
        this.onStatus({ ...this.status });
    }

    stopAudio() {
        if (this.audio) {
            this.audio.source.disconnect();
            this.audio.ctx.close().catch(() => {});
            this.audio = null;
        }
    }

    resetWindows() {
        this.voice = [];
        this.mouth = [];
        this.turnedSince = null;
        this.missingSince = null;
    }

    active(now) {
        return !this.held && now >= this.quietUntil && this.startedAt && now - this.startedAt >= WARMUP_MS;
    }

    tick() {
        const now = performance.now();
        this.sampleAudio(now);
        if (this.landmarker && now - this.lastFaceAt >= FACE_EVERY_MS) {
            this.lastFaceAt = now;
            this.sampleFace(now);
        }
        const cutoff = now - WINDOW_MS;
        while (this.voice.length && this.voice[0].t < cutoff) this.voice.shift();
        while (this.mouth.length && this.mouth[0].t < cutoff) this.mouth.shift();

        if (this.active(now)) this.checkTalking();
    }

    sampleAudio(now) {
        const a = this.audio;
        if (!a || a.ctx.state !== 'running') return;
        a.analyser.getFloatFrequencyData(a.buf);
        let power = 0;
        for (let i = a.lo; i < a.hi; i++) power += Math.pow(10, a.buf[i] / 10);
        const level = 10 * Math.log10(power / (a.hi - a.lo) + 1e-20);
        if (!isFinite(level)) return;

        // Background noise: follows quiet moments quickly, loud ones very slowly.
        if (this.floor === null) this.floor = level;
        else this.floor += (level - this.floor) * (level < this.floor ? 0.2 : 0.003);

        this.voice.push({ t: now, on: level > this.floor + VOICE_DB_ABOVE_NOISE });
    }

    sampleFace(now) {
        const v = this.video;
        if (v.readyState < 2 || v.videoWidth === 0 || v.currentTime === this.lastVideoTime) return;
        this.lastVideoTime = v.currentTime;

        let result;
        try {
            result = this.landmarker.detectForVideo(v, now);
        } catch (e) {
            return;
        }
        const face = result.faceLandmarks && result.faceLandmarks[0];
        const watching = this.active(now);

        if (!face) {
            this.turnedSince = null;
            if (this.missingSince === null) this.missingSince = now;
            if (watching && this.faceSeen && now - this.missingSince >= FACE_MISSING_MS) this.fire('head_turn');
            return;
        }
        this.faceSeen = true;
        this.missingSince = null;

        // Sideways turn: where the nose tip sits between the two cheek edges.
        const nose = face[1], left = face[234], right = face[454];
        const span = right.x - left.x;
        const offset = span ? Math.abs((nose.x - left.x) / span - 0.5) : 0;
        if (offset > HEAD_YAW_LIMIT) {
            if (this.turnedSince === null) this.turnedSince = now;
            if (watching && now - this.turnedSince >= HEAD_TURN_MS) this.fire('head_turn');
        } else {
            this.turnedSince = null;
        }

        const shapes = result.faceBlendshapes && result.faceBlendshapes[0];
        const jaw = shapes && shapes.categories.find(c => c.categoryName === 'jawOpen');
        if (jaw) this.mouth.push({ t: now, v: jaw.score });
    }

    checkTalking() {
        if (this.voice.length < WINDOW_MS / TICK_MS * 0.6) return;
        const voiced = this.voice.filter(s => s.on).length / this.voice.length;

        if (this.landmarker) {
            if (voiced < VOICE_FRACTION || this.mouth.length < 5) return;
            let lo = 1, hi = 0;
            for (const s of this.mouth) { lo = Math.min(lo, s.v); hi = Math.max(hi, s.v); }
            if (hi - lo < MOUTH_RANGE) return; // voice, but not from this candidate's mouth
        } else if (voiced < VOICE_FRACTION_AUDIO_ONLY) {
            return; // no face model: rely on sound alone, more strictly
        }
        this.fire('talking');
    }

    fire(type) {
        this.held = true; // the page releases us once the warning is dealt with
        this.resetWindows();
        document.dispatchEvent(new CustomEvent('proctor:violation', { detail: { type } }));
    }
}
