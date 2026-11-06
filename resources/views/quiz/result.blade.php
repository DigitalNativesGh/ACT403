@extends('layouts.app')

@section('page_title', $title.' · Result')

@php
    $pct = $attempt->percentage();
    $headline = match (true) {
        $pct >= 80 => 'Excellent work!',
        $pct >= 60 => 'Well done!',
        $pct >= 40 => 'Good effort!',
        default => 'Thank you for taking part!',
    };
    $deg = round($pct * 3.6, 1);
@endphp

@push('styles')
<style>
    .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; }
    .panel { width: 100%; max-width: 480px; text-align: center; }
    .badge { font-size: 2.6rem; line-height: 1; margin-bottom: 10px; }
    .ring {
        --deg: {{ $deg }}deg;
        width: 180px; height: 180px; margin: 24px auto;
        border-radius: 50%;
        background: conic-gradient(var(--brand) var(--deg), var(--brand-soft) 0);
        display: grid; place-items: center;
    }
    .ring-inner {
        width: 146px; height: 146px; border-radius: 50%; background: var(--card);
        display: grid; place-items: center; align-content: center;
    }
    .score { font-size: 2.6rem; font-weight: 800; line-height: 1; color: var(--brand); font-variant-numeric: tabular-nums; }
    .score-of { color: var(--muted); font-weight: 600; margin-top: 4px; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0 0 22px; text-align: left; }
    .meta div { background: var(--paper); border-radius: 8px; padding: 10px 12px; }
    .meta dt { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
    .meta dd { margin: 2px 0 0; font-weight: 700; }
</style>
@endpush

@section('content')
<div class="wrap">
    <div class="panel card">
        @if ($attempt->closed_reason === \App\Models\Attempt::CLOSED_FOR_TALKING)
            <div class="alert alert-error" style="text-align:left">
                <strong>Your exam was closed by the proctor</strong> because you were detected talking {{ $attempt->talk_count }} times.
                Your answers up to that point were marked.
            </div>
            <h1>Exam closed</h1>
            <p class="muted" style="margin:0">Here is your result for <strong>{{ $title }}</strong>.</p>
        @else
            <div class="badge" aria-hidden="true">🎉</div>
            <h1>Congratulations!</h1>
            <p class="muted" style="margin:0">You have completed <strong>{{ $title }}</strong>. {{ $headline }}</p>
        @endif

        <div class="ring" role="img" aria-label="Score {{ $attempt->score }} out of {{ $attempt->total }}">
            <div class="ring-inner">
                <div class="score">{{ $attempt->score }}<span style="font-size:1.4rem;color:var(--muted)">/{{ $attempt->total }}</span></div>
                <div class="score-of">{{ rtrim(rtrim(number_format($pct, 1), '0'), '.') }}%</div>
            </div>
        </div>

        @if ($attempt->penalty > 0)
            <dl class="meta deductions">
                <div><dt>Raw score</dt><dd>{{ $attempt->raw_score }} / {{ $attempt->total }}</dd></div>
                <div><dt>Deducted</dt><dd style="color:var(--danger)">−{{ $attempt->penalty }}</dd></div>
                <div style="grid-column:1/-1"><dt>Why</dt><dd style="font-weight:500">
                    @php
                        // Sum what was actually deducted per type, so attempts graded under older rules stay right.
                        $deducted = $attempt->proctorEvents()->selectRaw('type, sum(penalty) as marks')->groupBy('type')->pluck('marks', 'type');
                    @endphp
                    @if ($attempt->talk_count) Talking detected {{ $attempt->talk_count }}× (−{{ (int) ($deducted[\App\Models\ProctorEvent::TALKING] ?? 0) }})@endif
                    @if ($attempt->talk_count && $attempt->head_turn_count) · @endif
                    @if ($attempt->head_turn_count) Head turned away {{ $attempt->head_turn_count }}× (−{{ (int) ($deducted[\App\Models\ProctorEvent::HEAD_TURN] ?? 0) }})@endif
                </dd></div>
            </dl>
        @endif

        <dl class="meta">
            <div><dt>Index number</dt><dd>{{ $attempt->index_number }}</dd></div>
            <div><dt>UIN</dt><dd>{{ $attempt->uin }}</dd></div>
            <div><dt>Time taken</dt><dd>{{ $attempt->timeTaken() }}</dd></div>
            <div><dt>Submitted</dt><dd>{{ $attempt->submitted_at->format('d M Y, H:i') }}</dd></div>
        </dl>

        <form method="POST" action="{{ route('quiz.finish') }}">
            @csrf
            <button type="submit" class="btn btn-block">Finish</button>
        </form>
    </div>
</div>
@endsection
