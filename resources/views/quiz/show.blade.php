@extends('layouts.app')

@section('page_title', $title)

@push('styles')
<style>
    :root { --cam-w: 220px; --cam-h: 165px; --bar-h: 64px; }

    /* Top bar */
    .bar {
        position: sticky; top: 0; z-index: 20;
        height: var(--bar-h);
        display: flex; align-items: center; gap: 16px;
        padding: 0 calc(var(--cam-w) + 32px) 0 24px;
        background: var(--card); border-bottom: 1px solid var(--line);
    }
    .bar-title { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-title small { display: block; font-weight: 500; color: var(--muted); font-size: .8rem; }
    .bar-spacer { flex: 1; }
    .timer {
        font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
        font-size: 1.6rem; font-weight: 700; letter-spacing: .02em;
        padding: 4px 14px; border-radius: 8px;
        background: var(--brand-soft); color: var(--brand);
        font-variant-numeric: tabular-nums;
    }
    .timer.warn { background: #fdf3e1; color: #8a5a00; }
    .timer.danger { background: var(--danger-soft); color: var(--danger); animation: pulse 1s infinite; }
    @keyframes pulse { 50% { opacity: .6; } }
    .progress { font-size: .875rem; color: var(--muted); white-space: nowrap; }

    /* Webcam, top-right corner */
    .cam {
        position: fixed; top: 12px; right: 12px; z-index: 30;
        width: var(--cam-w); height: var(--cam-h);
        border-radius: 12px; overflow: hidden;
        background: #1c2321; border: 3px solid var(--card);
        box-shadow: 0 6px 24px rgba(0,0,0,.25);
    }
    .cam video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
    .cam-tag {
        position: absolute; left: 8px; bottom: 8px;
        background: rgba(0,0,0,.6); color: #fff; font-size: .72rem; font-weight: 600;
        padding: 2px 8px; border-radius: 6px; display: flex; align-items: center; gap: 6px;
    }
    .cam-tag::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: #e5484d; }

    /* Questions */
    main { max-width: 760px; margin: 0 auto; padding: 24px 24px 120px; }
    @media (max-width: 1300px) { main { margin-left: 24px; margin-right: calc(var(--cam-w) + 36px); padding-left: 0; padding-right: 0; } }
    .q { margin-bottom: 16px; scroll-margin-top: calc(var(--bar-h) + 16px); }
    .q-head { display: flex; gap: 10px; align-items: baseline; margin-bottom: 14px; }
    .q-num { font-weight: 700; color: var(--brand); flex-shrink: 0; }
    .q-text { font-weight: 600; white-space: pre-line; }
    .opt {
        display: flex; gap: 12px; align-items: flex-start;
        padding: 11px 14px; margin-bottom: 8px;
        border: 1px solid var(--line); border-radius: 10px;
        cursor: pointer; font-weight: 400;
        transition: background .12s, border-color .12s;
    }
    .opt:hover { background: #f1f5f3; }
    .opt input { margin-top: 4px; accent-color: var(--brand); flex-shrink: 0; }
    .opt:has(input:checked) { border-color: var(--brand); background: var(--brand-soft); }
    .opt-letter { font-weight: 700; color: var(--muted); width: 1.2em; flex-shrink: 0; }
    .submit-row { display: flex; justify-content: flex-end; align-items: center; gap: 14px; margin-top: 24px; }
    .save-state { font-size: .8rem; color: var(--muted); }

    /* Blocking overlay when the camera is off */
    .overlay {
        position: fixed; inset: 0; z-index: 40;
        background: rgba(28,35,33,.86);
        display: none; align-items: center; justify-content: center; padding: 16px;
    }
    .overlay.show { display: flex; }
    .overlay .card { max-width: 420px; text-align: center; }

    /* Proctoring tally + warning dialog */
    .tally { font-size: .8rem; white-space: nowrap; display: flex; gap: 6px; }
    .tally span { padding: 3px 9px; border-radius: 999px; background: var(--paper); color: var(--muted); font-weight: 600; }
    .tally span.hit { background: var(--danger-soft); color: var(--danger); }
    .warn-modal .card { max-width: 460px; text-align: left; border-top: 6px solid #c98a1b; }
    .warn-modal[data-severity=penalty] .card { border-top-color: var(--danger); }
    .warn-modal h2 { font-size: 1.25rem; }
    .warn-modal[data-severity=penalty] h2 { color: var(--danger); }
    .warn-modal p { margin: 0 0 18px; }
    .warn-modal .btn { width: 100%; }

    /* Phones */
    @media (max-width: 800px) {
        :root { --cam-w: 112px; --cam-h: 84px; --bar-h: 108px; }
        .bar { flex-wrap: wrap; height: auto; min-height: var(--bar-h); padding: 10px calc(var(--cam-w) + 24px) 10px 14px; gap: 6px 12px; align-content: center; }
        .bar-title { width: 100%; }
        .bar .btn { display: none; }
        .tally { font-size: .7rem; }
        .timer { font-size: 1.25rem; }
        main { margin: 0; padding: 16px 12px 120px; }
        .cam { top: 10px; right: 10px; border-width: 2px; }
        .cam-tag { font-size: .62rem; left: 4px; bottom: 4px; padding: 1px 5px; }
    }
</style>
@endpush

@section('content')
<div class="cam" aria-label="Your camera">
    <video id="cam" autoplay muted playsinline></video>
    <div class="cam-tag">{{ $attempt->index_number }}</div>
</div>

<header class="bar">
    <div class="bar-title">
        {{ $title }}
        <small>Index: {{ $attempt->index_number }}</small>
    </div>
    <div class="bar-spacer"></div>
    @if ($proctoring)
        <div class="tally" title="Proctoring: times detected, and marks deducted">
            <span id="tally-talk" @class(['hit' => $attempt->talk_count])>Talking {{ $attempt->talk_count }}</span>
            <span id="tally-head" @class(['hit' => $attempt->head_turn_count])>Head turns {{ $attempt->head_turn_count }}</span>
            <span id="tally-penalty" @class(['hit' => $attempt->penalty])>−{{ $attempt->penalty }}</span>
        </div>
    @endif
    <div class="progress"><span id="answered-count">0</span> / {{ $questions->count() }} answered</div>
    <div class="timer" id="timer" aria-live="off">--:--</div>
    <button type="button" class="btn" id="submit-top">Submit</button>
</header>

<main>
    <form method="POST" action="{{ route('quiz.submit') }}" id="quiz-form">
        @csrf
        @foreach ($questions as $i => $q)
            <section class="card q" id="q{{ $i + 1 }}" data-qid="{{ $q->id }}">
                <div class="q-head">
                    <span class="q-num">{{ $i + 1 }}.</span>
                    <div class="q-text">{{ $q->question }}</div>
                </div>
                @foreach ($q->options() as $letter => $text)
                    <label class="opt">
                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $letter }}"
                               @checked(($saved[(string) $q->id] ?? null) === $letter)>
                        <span class="opt-letter">{{ $letter }}</span>
                        <span>{{ $text }}</span>
                    </label>
                @endforeach
            </section>
        @endforeach

        <div class="submit-row">
            <span class="save-state" id="save-state"></span>
            <button type="submit" class="btn" id="submit-bottom">Submit answers</button>
        </div>
    </form>
</main>

<div class="overlay warn-modal" id="warn-modal" role="alertdialog" aria-modal="true" aria-labelledby="warn-title" aria-describedby="warn-body">
    <div class="card">
        <h2 id="warn-title"></h2>
        <p id="warn-body"></p>
        <button type="button" class="btn" id="warn-ok">I understand</button>
    </div>
</div>

<div class="overlay" id="cam-overlay" role="alertdialog" aria-modal="true">
    <div class="card">
        <h2>{{ $proctoring ? 'Camera and microphone required' : 'Camera required' }}</h2>
        <p class="muted" id="cam-overlay-msg">Your {{ $proctoring ? 'camera and microphone' : 'camera' }} must be on to continue the quiz. The timer is still running.</p>
        <button type="button" class="btn" id="cam-retry">Turn {{ $proctoring ? 'camera and microphone' : 'camera' }} on</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('quiz-form');
    const timerEl = document.getElementById('timer');
    const countEl = document.getElementById('answered-count');
    const saveState = document.getElementById('save-state');
    const total = {{ $questions->count() }};
    const saveUrl = @json(route('quiz.save'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let deadline = Date.now() + {{ $secondsLeft }} * 1000;
    let submitting = false;
    let proctor = null; // AI proctoring, loaded below when enabled
    const warnModal = document.getElementById('warn-modal');

    /* ---------- Webcam ---------- */
    const video = document.getElementById('cam');
    const overlay = document.getElementById('cam-overlay');
    const overlayMsg = document.getElementById('cam-overlay-msg');

    const PROCTORING = @json($proctoring);
    const device = PROCTORING ? 'camera and microphone' : 'camera';

    function camOff(message) {
        if (message) overlayMsg.textContent = message;
        overlay.classList.add('show');
        if (proctor) proctor.hold();
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            camOff('Your ' + device + ' cannot be used because this page is not on HTTPS. Tell your lecturer.');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480 },
                // Sound is analysed on this device only; it is never sent anywhere.
                audio: PROCTORING ? { echoCancellation: false, noiseSuppression: true, autoGainControl: false } : false,
            });
            video.srcObject = stream;
            useCameraForMonitoring(stream);
            if (proctor) { proctor.setStream(stream); if (!warnModal.classList.contains('show')) proctor.release(); }
            overlay.classList.remove('show');
            stream.getTracks().forEach(track => {
                track.addEventListener('ended', () => camOff('Your ' + (track.kind === 'audio' ? 'microphone' : 'camera')
                    + ' was turned off. Turn it back on to continue. The timer is still running.'));
            });
        } catch (e) {
            camOff(e && e.name === 'NotAllowedError'
                ? 'Permission for your ' + device + ' was blocked. Allow access in your browser, then click the button. The timer is still running.'
                : 'Your ' + device + ' could not be started. Close other apps using them, then click the button. The timer is still running.');
        }
    }
    document.getElementById('cam-retry').addEventListener('click', startCamera);
    startCamera();

    /* ---------- Live monitoring ----------
     * The lecturer's monitor page sends a WebRTC offer for each camera it
     * shows; we answer with this camera. Video goes straight to the
     * lecturer's browser and is never uploaded or recorded. */
    const ICE = @json($iceServers);
    const pollUrl = @json(route('stream.poll'));
    const answerUrl = @json(route('stream.answer'));
    const peers = new Map(); // monitor tab id -> RTCPeerConnection
    let camStream = null;

    function cameraLive() {
        return !!camStream && camStream.getVideoTracks().some(t => t.readyState === 'live');
    }

    function useCameraForMonitoring(stream) {
        camStream = stream;
        const track = stream.getVideoTracks()[0];
        for (const pc of peers.values()) {
            pc.getSenders().forEach(sender => sender.replaceTrack(track).catch(() => {}));
        }
    }

    function waitForIce(pc, ms) {
        return new Promise(resolve => {
            if (pc.iceGatheringState === 'complete') return resolve();
            const done = () => { clearTimeout(t); pc.removeEventListener('icegatheringstatechange', check); resolve(); };
            const check = () => { if (pc.iceGatheringState === 'complete') done(); };
            const t = setTimeout(done, ms);
            pc.addEventListener('icegatheringstatechange', check);
        });
    }

    /** Small, low-frame-rate stream: dozens are shown at once on the monitor. */
    function limitBandwidth(sender, track) {
        try {
            const params = sender.getParameters();
            if (!params.encodings || !params.encodings.length) return;
            const width = (track.getSettings && track.getSettings().width) || 640;
            params.encodings[0].maxBitrate = 150000;
            params.encodings[0].maxFramerate = 12;
            params.encodings[0].scaleResolutionDownBy = Math.max(1, width / 320);
            sender.setParameters(params).catch(() => {});
        } catch (e) { /* older browsers: send at default quality */ }
    }

    async function answerOffer(offer) {
        if (!cameraLive() || !window.RTCPeerConnection) return; // the monitor retries later
        const previous = peers.get(offer.viewer);
        if (previous) previous.close();

        const pc = new RTCPeerConnection({ iceServers: ICE });
        peers.set(offer.viewer, pc);
        const drop = () => { pc.close(); if (peers.get(offer.viewer) === pc) peers.delete(offer.viewer); };
        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'failed' || pc.connectionState === 'closed') drop();
        };

        try {
            const track = camStream.getVideoTracks()[0];
            await pc.setRemoteDescription({ type: 'offer', sdp: offer.sdp });
            const transceiver = pc.getTransceivers().find(t => t.receiver.track.kind === 'video');
            await transceiver.sender.replaceTrack(track);
            transceiver.direction = 'sendonly';
            await pc.setLocalDescription(await pc.createAnswer());
            limitBandwidth(transceiver.sender, track);
            await waitForIce(pc, 2500);
            await fetch(answerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ viewer: offer.viewer, ref: offer.ref, sdp: pc.localDescription.sdp }),
                credentials: 'same-origin',
            });
        } catch (e) {
            console.warn('Live monitoring: could not answer', e);
            drop();
        }
    }

    function stopMonitoring() {
        for (const pc of peers.values()) pc.close();
        peers.clear();
    }

    /** Heartbeat (so the lecturer sees who is online) + pick up offers. */
    async function pollMonitor() {
        try {
            const res = await fetch(pollUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ camera: cameraLive() }),
                credentials: 'same-origin',
            });
            if (res.ok) {
                const body = await res.json();
                // Lecturer turned live video off: stop sending. Own preview stays on.
                if (body.video === false) stopMonitoring();
                body.offers.forEach(answerOffer);
            }
        } catch (e) { /* offline: try again next round */ }
        if (!submitting) setTimeout(pollMonitor, 4000);
    }
    pollMonitor();

    /* ---------- AI proctoring ---------- */
    const warnOk = document.getElementById('warn-ok');
    const proctorUrl = @json(route('quiz.proctor'));
    let reporting = false;

    function showWarning(warning, onClose) {
        warnModal.dataset.severity = warning.severity;
        document.getElementById('warn-title').textContent = warning.title;
        document.getElementById('warn-body').textContent = warning.body;
        warnModal.classList.add('show');
        warnOk.focus();
        warnOk.onclick = () => { warnModal.classList.remove('show'); onClose(); };
    }

    function updateTally(body) {
        const talk = document.getElementById('tally-talk');
        const head = document.getElementById('tally-head');
        const penalty = document.getElementById('tally-penalty');
        if (!talk || typeof body.talk_count !== 'number') return;
        talk.textContent = 'Talking ' + body.talk_count;
        talk.classList.toggle('hit', body.talk_count > 0);
        head.textContent = 'Head turns ' + body.head_turn_count;
        head.classList.toggle('hit', body.head_turn_count > 0);
        penalty.textContent = '−' + body.penalty;
        penalty.classList.toggle('hit', body.penalty > 0);
    }

    function leaveTo(url) {
        submitting = true;
        location.href = url;
    }

    async function reportProctoring(type) {
        if (reporting || submitting) return;
        reporting = true;
        if (proctor) proctor.hold();
        try {
            const res = await fetch(proctorUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ type, answers: collect() }),
                credentials: 'same-origin',
            });
            const body = await res.json();
            if (res.status === 409 && body.redirect) return leaveTo(body.redirect);
            updateTally(body);
            if (body.warning) {
                showWarning(body.warning, () => { if (proctor) proctor.release(); });
                return;
            }
        } catch (e) { /* offline: keep going */ }
        finally { reporting = false; }
        if (proctor && !warnModal.classList.contains('show')) proctor.release();
    }

    document.addEventListener('proctor:violation', e => reportProctoring(e.detail && e.detail.type));

    if (PROCTORING) {
        import(@json(asset('js/proctor.js').'?v='.filemtime(public_path('js/proctor.js')))).then(({ Proctor }) => {
            proctor = new Proctor({
                base: @json(asset('vendor/mediapipe')),
                video,
                onStatus: s => {
                    if (s.face === 'unavailable') {
                        fetch(proctorUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ type: 'detector_unavailable' }),
                            credentials: 'same-origin',
                        }).catch(() => {});
                    }
                },
            });
            if (camStream) proctor.setStream(camStream);
            if (overlay.classList.contains('show')) proctor.hold();
        }).catch(e => console.warn('Proctoring could not start', e));
    }

    /* ---------- Timer ---------- */
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
        timerEl.textContent = h > 0 ? h + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s);
        timerEl.classList.toggle('warn', left <= 300 && left > 60);
        timerEl.classList.toggle('danger', left <= 60);
        if (left === 0 && !submitting) {
            timerEl.textContent = "Time's up";
            submitNow();
        }
    }
    tick();
    setInterval(tick, 500);

    /* ---------- Answers + autosave ---------- */
    function collect() {
        const data = {};
        form.querySelectorAll('input[type=radio]:checked').forEach(r => {
            data[r.name.slice(8, -1)] = r.value; // answers[ID] -> ID
        });
        return data;
    }
    function unanswered() {
        const list = [];
        form.querySelectorAll('section.q').forEach((sec, i) => {
            if (!sec.querySelector('input:checked')) list.push(i + 1);
        });
        return list;
    }
    function updateCount() {
        countEl.textContent = total - unanswered().length;
    }

    let saveTimer = null;
    async function save() {
        saveState.textContent = 'Saving…';
        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ answers: collect() }),
                credentials: 'same-origin',
            });
            if (res.ok) {
                const body = await res.json();
                // Re-sync the clock with the server.
                if (typeof body.secondsLeft === 'number') deadline = Date.now() + body.secondsLeft * 1000;
                saveState.textContent = 'All answers saved';
            } else {
                saveState.textContent = 'Not saved — your answers are still on this page';
            }
        } catch (e) {
            saveState.textContent = 'Offline — your answers are still on this page';
        }
    }
    form.addEventListener('change', () => {
        updateCount();
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 600);
    });
    updateCount();

    /* ---------- Submit ---------- */
    function submitNow() {
        submitting = true;
        form.querySelectorAll('button').forEach(b => b.disabled = true);
        document.getElementById('submit-top').disabled = true;
        HTMLFormElement.prototype.submit.call(form);
    }
    function confirmAndSubmit(e) {
        if (e) e.preventDefault();
        if (submitting) return;
        const missing = unanswered();
        const msg = missing.length
            ? 'You have ' + missing.length + ' unanswered question(s): ' + missing.slice(0, 15).join(', ') + (missing.length > 15 ? '…' : '') + '.\n\nSubmit anyway? You cannot change your answers afterwards.'
            : 'Submit your answers? You cannot change them afterwards.';
        if (confirm(msg)) submitNow();
    }
    form.addEventListener('submit', confirmAndSubmit);
    document.getElementById('submit-top').addEventListener('click', confirmAndSubmit);

    window.addEventListener('beforeunload', e => {
        if (!submitting) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
@endpush
