@extends('layouts.app')

@section('page_title', $title.' · Student details')

@push('styles')
<style>
    .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; }
    .panel { width: 100%; max-width: 460px; }
    .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: .75rem; font-weight: 700; color: var(--accent); }
    .facts { display: flex; gap: 10px; margin: 14px 0 22px; flex-wrap: wrap; }
    .fact { background: var(--brand-soft); color: var(--brand); padding: 6px 12px; border-radius: 8px; font-size: .875rem; font-weight: 600; }
    .cam-check { display: flex; gap: 14px; align-items: center; padding: 12px; border: 1px dashed #c7d1cd; border-radius: 10px; margin-bottom: 18px; }
    .cam-check video { width: 96px; height: 72px; object-fit: cover; border-radius: 8px; background: #1c2321; transform: scaleX(-1); flex-shrink: 0; }
    .cam-status { font-size: .9rem; }
    .cam-status.ok { color: var(--ok); font-weight: 600; }
    .cam-status.bad { color: var(--danger); }
    .rules { font-size: .875rem; color: var(--muted); padding-left: 18px; margin: 0 0 18px; }
    .admin-link { text-align: center; margin-top: 16px; font-size: .8rem; }
    .admin-link a { color: var(--muted); }
</style>
@endpush

@section('content')
<div class="wrap">
    <div class="panel">
        <div class="card">
            <div class="eyebrow">Student details</div>
            <h1>{{ $title }}</h1>

            <div class="facts">
                <span class="fact">{{ $questionCount }} question{{ $questionCount === 1 ? '' : 's' }}</span>
                <span class="fact">{{ $duration }} minute{{ $duration === 1 ? '' : 's' }}</span>
                @if ($open)
                    <span class="pill pill-ok" style="align-self:center">Open</span>
                @else
                    <span class="pill pill-off" style="align-self:center">Not open yet</span>
                @endif
            </div>

            @if ($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('student.store') }}" id="details-form">
                @csrf
                <div class="field">
                    <label for="index_number">Index number</label>
                    <input type="text" id="index_number" name="index_number" value="{{ old('index_number') }}"
                           autocomplete="off" required maxlength="30" autofocus>
                </div>
                <div class="field">
                    <label for="uin">UIN</label>
                    <input type="text" id="uin" name="uin" value="{{ old('uin') }}"
                           autocomplete="off" required maxlength="30">
                </div>

                <div class="cam-check">
                    <video id="cam-preview" autoplay muted playsinline></video>
                    <div>
                        <div id="cam-status" class="cam-status">Checking your {{ $proctoring ? 'camera and microphone' : 'camera' }}…</div>
                        <button type="button" id="cam-retry" class="btn btn-secondary btn-sm" style="margin-top:6px;display:none">Try again</button>
                    </div>
                </div>

                <ul class="rules">
                    @if ($proctoring)
                        <li>Your camera and microphone must stay on for the whole quiz. Sound is checked on your device only and is never recorded.</li>
                        <li><strong>Talking:</strong> each time you are detected talking, <strong>{{ $rules['talk_penalty'] }} mark{{ $rules['talk_penalty'] === 1 ? ' is' : 's are' }} deducted</strong>.</li>
                        <li><strong>Turning your head away:</strong> each time, <strong>{{ $rules['head_turn_penalty'] }} mark{{ $rules['head_turn_penalty'] === 1 ? ' is' : 's are' }} deducted</strong>.</li>
                    @else
                        <li>Your camera must stay on for the whole quiz.</li>
                    @endif
                    <li>The timer starts as soon as you click Start and cannot be paused.</li>
                    <li>When time runs out, your answers are submitted automatically.</li>
                    <li>You can take the quiz only once.</li>
                </ul>

                <button type="submit" id="start-btn" class="btn btn-block" disabled>Start quiz</button>
            </form>
        </div>
        <div class="admin-link"><a href="{{ route('admin.login') }}">Lecturer login</a></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const video = document.getElementById('cam-preview');
    const status = document.getElementById('cam-status');
    const retry = document.getElementById('cam-retry');
    const startBtn = document.getElementById('start-btn');
    const form = document.getElementById('details-form');
    const PROCTORING = @json($proctoring);
    const device = PROCTORING ? 'camera and microphone' : 'camera';

    function fail(message) {
        status.textContent = message;
        status.className = 'cam-status bad';
        retry.style.display = 'inline-flex';
        startBtn.disabled = true;
    }

    async function check() {
        retry.style.display = 'none';
        status.textContent = 'Checking your ' + device + '…';
        status.className = 'cam-status';
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            fail('The ' + device + ' cannot be used. This page must be opened over HTTPS (or on localhost).');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: PROCTORING });
            video.srcObject = stream;
            status.textContent = PROCTORING ? 'Camera and microphone are working.' : 'Camera is working.';
            status.className = 'cam-status ok';
            startBtn.disabled = false;
            // Release the camera before moving to the quiz page.
            form.addEventListener('submit', function () {
                stream.getTracks().forEach(t => t.stop());
                startBtn.disabled = true;
                startBtn.textContent = 'Starting…';
            }, { once: true });
        } catch (e) {
            fail(e && e.name === 'NotAllowedError'
                ? 'Permission for your ' + device + ' was blocked. Allow access in your browser, then try again.'
                : 'No ' + device + ' found, or in use by another app.');
        }
    }

    retry.addEventListener('click', check);
    check();
})();
</script>
@endpush
