@extends('layouts.app')

@section('page_title', 'Live monitor · '.$title)

@push('styles')
<style>
    :root { --top-max: none; }
    body { background: #121816; }
    .toolbar {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 12px 24px; color: #d7dfdc; border-bottom: 1px solid #26302d;
    }
    .toolbar .summary { flex: 1; font-size: .9rem; }
    .toolbar .summary b { color: #fff; font-variant-numeric: tabular-nums; }
    .legend { display: flex; gap: 14px; font-size: .8rem; color: #9aa8a3; }
    .legend span::before { content: ""; display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; background: var(--c); }
    .pager { display: flex; gap: 4px; flex-wrap: wrap; }
    .pager button {
        font: inherit; font-size: .85rem; font-weight: 600; cursor: pointer;
        padding: 5px 11px; border-radius: 7px; border: 1px solid #34403c;
        background: #1b2321; color: #d7dfdc; font-variant-numeric: tabular-nums;
    }
    .pager button[aria-current=page] { background: var(--brand); border-color: var(--brand); color: #fff; }
    .pager button:disabled { opacity: .4; cursor: default; }

    .grid {
        display: grid; gap: 8px; padding: 12px 24px 32px;
        grid-template-columns: repeat(auto-fill, minmax(176px, 1fr));
    }
    .tile {
        position: relative; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden;
        background: #1f2826; border: 2px solid #1f2826; cursor: zoom-in;
    }
    .tile video { width: 100%; height: 100%; object-fit: cover; display: block; background: #000; }
    .tile-msg {
        position: absolute; inset: 0; display: grid; place-items: center; text-align: center;
        color: #9aa8a3; font-size: .8rem; padding: 0 10px 22px; pointer-events: none;
    }
    .tile[data-state=live] .tile-msg { display: none; }
    .tile-bar {
        position: absolute; left: 0; right: 0; bottom: 0;
        display: flex; align-items: center; gap: 6px; padding: 4px 8px;
        background: linear-gradient(transparent, rgba(0,0,0,.75)); color: #fff; font-size: .75rem;
    }
    .tile-bar b { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tile-bar .time { font-variant-numeric: tabular-nums; opacity: .85; }
    .flags {
        position: absolute; top: 6px; left: 6px; right: 6px; z-index: 1;
        background: rgba(229,72,77,.92); color: #fff; font-size: .7rem; font-weight: 700;
        padding: 2px 7px; border-radius: 6px; width: fit-content;
    }
    .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: var(--c, #6b7672); }
    .tile[data-state=live]       { --c: #30a46c; }
    .tile[data-state=connecting] { --c: #f5d90a; }
    .tile[data-state=unstable]   { --c: #f5d90a; }
    .tile[data-state=nocam]      { --c: #e5484d; border-color: #e5484d; }
    .tile[data-state=offline]    { --c: #6b7672; }
    .tile[data-state=paused]     { --c: #6b7672; cursor: default; }

    .video-toggle {
        display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;
        font-size: .85rem; font-weight: 600; color: #d7dfdc;
        padding: 5px 12px 5px 6px; border-radius: 999px; border: 1px solid #34403c; background: #1b2321;
    }
    .video-toggle input { position: absolute; opacity: 0; pointer-events: none; }
    .video-toggle .switch {
        position: relative; width: 34px; height: 20px; border-radius: 999px; background: #4a5652; transition: background .15s;
    }
    .video-toggle .switch::after {
        content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%;
        background: #fff; transition: transform .15s;
    }
    .video-toggle input:checked + .switch { background: #30a46c; }
    .video-toggle input:checked + .switch::after { transform: translateX(14px); }
    .video-toggle input:focus-visible + .switch { outline: 2px solid #fff; outline-offset: 2px; }
    .video-toggle input:disabled ~ * { opacity: .6; }

    .empty-state { color: #9aa8a3; text-align: center; padding: 80px 24px; grid-column: 1 / -1; }
    .banner { margin: 12px 24px 0; }

    .zoom {
        position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,.88);
        display: none; align-items: center; justify-content: center; flex-direction: column; gap: 12px; padding: 24px;
    }
    .zoom.show { display: flex; }
    .zoom video { max-width: min(100%, 960px); max-height: calc(100vh - 120px); width: 100%; border-radius: 12px; background: #000; }
    .zoom-bar { color: #fff; display: flex; gap: 16px; align-items: center; }
    @media (max-width: 640px) {
        .toolbar, .grid { padding-left: 12px; padding-right: 12px; }
        .grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        .legend { display: none; }
    }
</style>
@endpush

@section('content')
@include('admin._nav', ['active' => 'monitor', 'title' => $title])

<div class="alert alert-error banner" id="banner" hidden></div>

<div class="toolbar">
    <div class="summary" id="summary">Loading candidates…</div>
    <div class="legend" aria-hidden="true">
        <span style="--c:#30a46c">Live</span>
        <span style="--c:#f5d90a">Connecting</span>
        <span style="--c:#e5484d">Camera off</span>
        <span style="--c:#6b7672">Offline</span>
    </div>
    <label class="video-toggle" title="Off: candidates stop streaming to this page but still see themselves">
        <input type="checkbox" id="video-toggle" @checked($liveVideo)>
        <span class="switch" aria-hidden="true"></span>
        <span id="video-toggle-label">Live video {{ $liveVideo ? 'on' : 'off' }}</span>
    </label>
    <div class="pager" id="pager" aria-label="Pages"></div>
</div>

<div class="grid" id="grid"></div>

<div class="zoom" id="zoom" role="dialog" aria-modal="true" aria-label="Enlarged camera">
    <video id="zoom-video" autoplay muted playsinline></video>
    <div class="zoom-bar"><b id="zoom-label"></b><button type="button" class="btn btn-sm" id="zoom-close">Close (Esc)</button></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const ICE = @json($iceServers);
    const PER_PAGE = {{ $perPage }};
    const urls = {
        roster: @json(route('admin.monitor.roster')),
        offers: @json(route('admin.monitor.offers')),
        answers: @json(route('admin.monitor.answers')),
        video: @json(route('admin.monitor.video')),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const viewer = Array.from(crypto.getRandomValues(new Uint8Array(12)), b => b.toString(16).padStart(2, '0')).join('');

    const grid = document.getElementById('grid');
    const summary = document.getElementById('summary');
    const pager = document.getElementById('pager');
    const banner = document.getElementById('banner');

    const ANSWER_TIMEOUT = 20000;   // no answer / no connection by then: retry
    const UNSTABLE_TIMEOUT = 8000;  // "disconnected" this long: retry
    const RETRY_DELAY = 3000;

    let page = Math.max(1, parseInt(new URLSearchParams(location.search).get('page'), 10) || 1);
    let lastPage = 1;
    let refSeq = 0;
    let offerQueue = [];
    let flushTimer = null;
    let zoomedId = null;
    let videoOn = @json($liveVideo);
    const tiles = new Map(); // attempt id -> tile

    if (!window.RTCPeerConnection) {
        showBanner('This browser cannot show live video. Use a current Chrome, Edge, Firefox or Safari.');
        return;
    }

    /* ---------- Server calls ---------- */
    async function call(url, body) {
        const res = await fetch(url, {
            method: body ? 'POST' : 'GET',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'same-origin',
        });
        const json = (res.headers.get('content-type') || '').includes('application/json');
        if (res.status === 419 || res.redirected || !json) {
            showBanner('Your session has expired. Reload the page and log in again.');
            throw new Error('session');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }
    function showBanner(text) { banner.textContent = text; banner.hidden = false; }

    /* ---------- Tiles ---------- */
    function makeTile(c) {
        const el = document.createElement('div');
        el.className = 'tile';
        el.innerHTML = '<video autoplay muted playsinline></video><div class="tile-msg"></div>'
            + '<div class="flags" hidden></div>'
            + '<div class="tile-bar"><span class="dot"></span><b></b><span class="time"></span></div>';
        el.querySelector('b').textContent = c.index_number;
        el.title = c.index_number + ' · UIN ' + c.uin;
        const tile = { id: c.id, el, video: el.querySelector('video'), msg: el.querySelector('.tile-msg'),
                       time: el.querySelector('.time'), flags: el.querySelector('.flags'), pc: null, ref: null, pendingSince: null,
                       unstableSince: null, retryAt: 0, info: c, endsAt: 0 };
        el.addEventListener('click', () => openZoom(tile));
        return tile;
    }

    function setState(tile, state, text) {
        tile.el.dataset.state = state;
        tile.msg.textContent = text || '';
    }

    function closePc(tile) {
        if (tile.pc) {
            tile.pc.onconnectionstatechange = null;
            tile.pc.ontrack = null;
            tile.pc.close();
        }
        tile.pc = null;
        tile.ref = null;
        tile.pendingSince = null;
        tile.unstableSince = null;
        tile.video.srcObject = null;
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

    async function connect(tile) {
        closePc(tile);
        const pc = new RTCPeerConnection({ iceServers: ICE });
        const ref = (++refSeq).toString(36) + '.' + Date.now().toString(36);
        tile.pc = pc;
        tile.ref = ref;
        tile.pendingSince = Date.now();
        setState(tile, 'connecting', 'Connecting…');

        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.ontrack = e => {
            const stream = e.streams[0] || new MediaStream([e.track]);
            tile.video.srcObject = stream;
            if (zoomedId === tile.id) document.getElementById('zoom-video').srcObject = stream;
        };
        pc.onconnectionstatechange = () => {
            if (tile.pc !== pc) return;
            const s = pc.connectionState;
            if (s === 'connected') {
                tile.pendingSince = null;
                tile.unstableSince = null;
                setState(tile, 'live');
            } else if (s === 'disconnected') {
                tile.unstableSince = Date.now();
                setState(tile, 'unstable', 'Connection unstable…');
            } else if (s === 'failed') {
                closePc(tile);
                tile.retryAt = Date.now() + RETRY_DELAY;
                setState(tile, 'connecting', 'Reconnecting…');
            }
        };

        try {
            await pc.setLocalDescription(await pc.createOffer());
            await waitForIce(pc, 2500); // send all ICE candidates in one message
            if (tile.pc !== pc) return;
            offerQueue.push({ attempt_id: tile.id, ref, sdp: pc.localDescription.sdp });
            clearTimeout(flushTimer);
            flushTimer = setTimeout(flushOffers, 150);
        } catch (e) {
            if (tile.pc === pc) { closePc(tile); tile.retryAt = Date.now() + RETRY_DELAY; }
        }
    }

    async function flushOffers() {
        if (!offerQueue.length) return;
        const batch = offerQueue;
        offerQueue = [];
        try { await call(urls.offers, { viewer, offers: batch }); } catch (e) { /* watchdog retries */ }
    }

    async function pollAnswers() {
        const waiting = [...tiles.values()].some(t => t.pc && t.pc.signalingState === 'have-local-offer');
        if (!waiting) return;
        let body;
        try { body = await call(urls.answers, { viewer }); } catch (e) { return; }
        for (const a of body.answers) {
            const tile = tiles.get(a.attempt_id);
            if (!tile || !tile.pc || tile.ref !== a.ref || tile.pc.signalingState !== 'have-local-offer') continue;
            tile.pc.setRemoteDescription({ type: 'answer', sdp: a.sdp }).catch(() => {
                closePc(tile);
                tile.retryAt = Date.now() + RETRY_DELAY;
            });
        }
    }

    /** Keep every tile in the right state; (re)connect where needed. */
    function watchdog() {
        const now = Date.now();
        for (const tile of tiles.values()) {
            const c = tile.info;
            if (!c.online) {
                if (tile.pc) closePc(tile);
                setState(tile, 'offline', 'Offline — page closed or no network');
                continue;
            }
            if (!c.camera_on) {
                if (tile.pc) closePc(tile);
                setState(tile, 'nocam', 'Camera is OFF');
                continue;
            }
            if (!videoOn) {
                if (tile.pc) closePc(tile);
                setState(tile, 'paused', 'Live video is off');
                continue;
            }
            const pc = tile.pc;
            if (!pc) {
                if (now >= tile.retryAt) connect(tile);
                else setState(tile, 'connecting', 'Reconnecting…');
            } else if (pc.connectionState === 'closed'
                || (tile.pendingSince && now - tile.pendingSince > ANSWER_TIMEOUT)
                || (tile.unstableSince && now - tile.unstableSince > UNSTABLE_TIMEOUT)) {
                connect(tile);
            }
        }
    }

    /* ---------- Roster + paging ---------- */
    async function loadRoster() {
        let data;
        try { data = await call(urls.roster + '?page=' + page); } catch (e) { return; }
        lastPage = data.last_page;
        if (page > lastPage) { setPage(lastPage); return; }
        if (!videoToggle.disabled) setVideo(data.video); // another lecturer may have changed it
        banner.hidden = true;

        const first = data.total ? (data.page - 1) * PER_PAGE + 1 : 0;
        const last = (data.page - 1) * PER_PAGE + data.candidates.length;
        summary.innerHTML = data.total
            ? 'Showing <b>' + first + '–' + last + '</b> of <b>' + data.total + '</b> writing · <b>' + data.online + '</b> online'
            : '<b>0</b> candidates writing';
        renderPager();

        const seen = new Set();
        data.candidates.forEach((c, i) => {
            seen.add(c.id);
            let tile = tiles.get(c.id);
            if (!tile) {
                tile = makeTile(c);
                tiles.set(c.id, tile);
            }
            tile.info = c;
            const flags = [];
            if (c.talk_count) flags.push('Talking ' + c.talk_count);
            if (c.head_turn_count) flags.push('Head turns ' + c.head_turn_count);
            if (c.penalty) flags.push('−' + c.penalty + ' mark' + (c.penalty === 1 ? '' : 's'));
            tile.flags.textContent = flags.join(' · ');
            tile.flags.hidden = !flags.length;
            tile.endsAt = Date.now() + c.seconds_left * 1000;
            if (grid.children[i] !== tile.el) grid.insertBefore(tile.el, grid.children[i] || null);
        });
        for (const [id, tile] of tiles) {
            if (!seen.has(id)) { closePc(tile); tile.el.remove(); tiles.delete(id); }
        }

        let empty = grid.querySelector('.empty-state');
        if (!data.candidates.length && !empty) {
            empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.textContent = 'No candidates are writing right now. Tiles appear here as soon as candidates start the quiz.';
            grid.appendChild(empty);
        } else if (data.candidates.length && empty) {
            empty.remove();
        }
        watchdog();
        tickClocks();
    }

    function renderPager() {
        pager.innerHTML = '';
        if (lastPage <= 1) return;
        const add = (label, target, opts = {}) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            if (opts.current) b.setAttribute('aria-current', 'page');
            if (opts.title) b.title = opts.title;
            b.disabled = !!opts.disabled;
            b.addEventListener('click', () => setPage(target));
            pager.appendChild(b);
        };
        add('‹ Prev', page - 1, { disabled: page === 1 });
        for (let p = 1; p <= lastPage; p++) {
            add(String(p), p, { current: p === page, title: 'Candidates ' + ((p - 1) * PER_PAGE + 1) + '–' + p * PER_PAGE });
        }
        add('Next ›', page + 1, { disabled: page === lastPage });
    }

    function setPage(n) {
        n = Math.max(1, n);
        if (n === page && tiles.size) return;
        page = n;
        for (const tile of tiles.values()) closePc(tile);
        tiles.clear();
        grid.innerHTML = '';
        const url = new URL(location.href);
        url.searchParams.set('page', page);
        history.replaceState(null, '', url);
        window.scrollTo(0, 0);
        loadRoster();
    }

    /* ---------- Time left on each tile ---------- */
    function tickClocks() {
        const now = Date.now();
        for (const tile of tiles.values()) {
            const left = Math.max(0, Math.round((tile.endsAt - now) / 1000));
            tile.time.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
        }
    }

    /* ---------- Live video on/off (for everyone) ---------- */
    const videoToggle = document.getElementById('video-toggle');
    const videoLabel = document.getElementById('video-toggle-label');
    function setVideo(on) {
        videoOn = !!on;
        videoToggle.checked = videoOn;
        videoLabel.textContent = 'Live video ' + (videoOn ? 'on' : 'off');
        if (!videoOn) closeZoom();
        watchdog();
    }
    videoToggle.addEventListener('change', async () => {
        const want = videoToggle.checked;
        if (!want === !videoOn) return;
        if (!want && !confirm('Turn live video off for all candidates?\n\nTheir cameras stop streaming to the monitor. Candidates still see themselves, and camera-off and proctoring flags keep working.')) {
            videoToggle.checked = videoOn;
            return;
        }
        videoToggle.disabled = true;
        try {
            setVideo((await call(urls.video, { on: want })).video);
        } catch (e) {
            videoToggle.checked = videoOn;
            if (e.message !== 'session') showBanner('Could not change live video. Check your connection and try again.');
        } finally {
            videoToggle.disabled = false;
        }
    });

    /* ---------- Enlarge one camera ---------- */
    const zoom = document.getElementById('zoom');
    const zoomVideo = document.getElementById('zoom-video');
    function openZoom(tile) {
        if (!videoOn) return;
        zoomedId = tile.id;
        zoomVideo.srcObject = tile.video.srcObject;
        document.getElementById('zoom-label').textContent = tile.info.index_number + ' · UIN ' + tile.info.uin;
        zoom.classList.add('show');
    }
    function closeZoom() { zoomedId = null; zoomVideo.srcObject = null; zoom.classList.remove('show'); }
    document.getElementById('zoom-close').addEventListener('click', closeZoom);
    zoom.addEventListener('click', e => { if (e.target === zoom) closeZoom(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeZoom(); });

    /* ---------- Loops ---------- */
    loadRoster();
    setInterval(loadRoster, 8000);
    setInterval(pollAnswers, 1500);
    setInterval(watchdog, 2000);
    setInterval(tickClocks, 1000);
    window.addEventListener('pagehide', () => { for (const tile of tiles.values()) closePc(tile); });
})();
</script>
@endpush
