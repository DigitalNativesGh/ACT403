@extends('layouts.app')

@section('page_title', 'Lecturer dashboard')

@push('styles')
<style>
    .page { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
    .stat { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 14px 16px; }
    .stat b { display: block; font-size: 1.6rem; font-variant-numeric: tabular-nums; }
    .stat span { font-size: .8rem; color: var(--muted); }
    a.stat-link { color: inherit; text-decoration: none; }
    a.stat-link:hover { border-color: var(--brand); }
    a.stat-link span { color: var(--brand); font-weight: 600; }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .section-title { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .section-title h2 { font-size: 1.1rem; margin: 0; flex: 1; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .check { display: flex; gap: 10px; align-items: center; font-weight: 600; margin-bottom: 16px; }
    .check input { width: 18px; height: 18px; accent-color: var(--brand); }
    .help { font-size: .85rem; color: var(--muted); margin: -8px 0 16px; }
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
    th { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); background: var(--paper); }
    td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .table-wrap { overflow-x: auto; }
    .correct { color: var(--ok); font-weight: 700; }
    .inline { display: inline; }
    code.k { background: var(--paper); border: 1px solid var(--line); padding: 1px 6px; border-radius: 4px; font-size: .85em; }
    .empty { text-align: center; color: var(--muted); padding: 24px; }
    @media (max-width: 860px) {
        .stats { grid-template-columns: repeat(2, 1fr); }
        .grid2, .row { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
@include('admin._nav', ['active' => 'dashboard', 'title' => $settings['quiz_title']])

<div class="page">
    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif
    @if (session('import_errors'))
        <div class="alert alert-error">
            <strong>Nothing was imported. Fix {{ session('import_error_count') }} problem(s) in your CSV and upload again:</strong>
            <ul>
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
                @if (session('import_error_count') > count(session('import_errors')))
                    <li>…and {{ session('import_error_count') - count(session('import_errors')) }} more.</li>
                @endif
            </ul>
        </div>
    @endif

    <div class="stats">
        <div class="stat"><b>{{ $questions->count() }}</b><span>Questions in bank</span></div>
        <a class="stat stat-link" href="{{ route('admin.monitor') }}"><b>{{ $stats['in_progress'] }}</b><span>Writing now · watch live →</span></a>
        <a class="stat stat-link" href="{{ route('admin.results') }}"><b>{{ $stats['submitted'] }}</b><span>Submitted · see results →</span></a>
        <div class="stat"><b>{{ $stats['average'] !== null ? $stats['average'].'%' : '—' }}</b><span>Class average</span></div>
    </div>

    <div class="grid2">
        {{-- Upload questions --}}
        <section class="card">
            <div class="section-title">
                <h2>Upload questions (CSV)</h2>
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.questions.template') }}">Download template</a>
            </div>
            <p class="help" style="margin-top:0">
                One question per row. Fill <code class="k">option_a</code>–<code class="k">option_e</code>
                (C, D, E may be blank) and put the letter of the right option in
                <code class="k">correct_answer</code>, e.g. <code class="k">B</code>.
                In Excel, save as <em>CSV UTF-8</em>.
            </p>
            <form method="POST" action="{{ route('admin.questions.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label for="csv_file">CSV file</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                </div>
                <label class="check">
                    <input type="checkbox" name="replace_existing" value="1" {{ $questions->isEmpty() ? '' : 'checked' }}>
                    Replace the existing {{ $questions->count() }} question(s)
                </label>
                <button type="submit" class="btn">Upload</button>
            </form>
        </section>

        {{-- Settings --}}
        <section class="card">
            <div class="section-title">
                <h2>Quiz settings</h2>
                @if ($settings['quiz_open'])
                    <span class="pill pill-ok">Open to students</span>
                @else
                    <span class="pill pill-off">Closed</span>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.settings') }}">
                @csrf
                <div class="field">
                    <label for="quiz_title">Quiz title</label>
                    <input type="text" id="quiz_title" name="quiz_title" value="{{ old('quiz_title', $settings['quiz_title']) }}" required maxlength="120">
                </div>
                <div class="row">
                    <div class="field">
                        <label for="duration_minutes">Duration (minutes)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" min="1" max="600" value="{{ old('duration_minutes', $settings['duration_minutes']) }}" required>
                    </div>
                    <div class="field">
                        <label for="questions_per_student">Questions per student</label>
                        <input type="number" id="questions_per_student" name="questions_per_student" min="0" max="1000" value="{{ old('questions_per_student', $settings['questions_per_student']) }}" required>
                    </div>
                </div>
                <p class="help">Questions are shuffled for each student. Set questions per student to <b>0</b> to give everyone all questions, or e.g. <b>20</b> to pick 20 at random from the bank.</p>
                <label class="check">
                    <input type="checkbox" name="quiz_open" value="1" @checked($settings['quiz_open'])>
                    Quiz is open — students can start
                </label>
                <label class="check">
                    <input type="checkbox" name="proctoring" value="1" @checked($settings['proctoring'])>
                    AI proctoring — talking −{{ config('quiz.proctoring.talk_penalty') }} each; head turns −{{ config('quiz.proctoring.head_turn_penalty') }} each (the exam is never closed)
                </label>
                <button type="submit" class="btn">Save settings</button>
            </form>
        </section>
    </div>

    {{-- Question bank --}}
    <section class="card">
        <div class="section-title">
            <h2>Question bank ({{ $questions->count() }})</h2>
            @if ($questions->isNotEmpty())
                <form method="POST" action="{{ route('admin.questions.destroyAll') }}" class="inline"
                      onsubmit="return confirm('Delete ALL questions?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm" type="submit">Delete all</button>
                </form>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Question &amp; options</th><th>Answer</th><th></th></tr></thead>
                <tbody>
                    @forelse ($questions as $i => $q)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td>
                                <div style="font-weight:600;white-space:pre-line">{{ $q->question }}</div>
                                <div class="small muted">
                                    @foreach ($q->options() as $letter => $text)
                                        <span class="{{ $letter === $q->correct_option ? 'correct' : '' }}">{{ $letter }}. {{ $text }}</span>@if (! $loop->last) &nbsp;·&nbsp; @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="correct">{{ $q->correct_option }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.questions.destroy', $q) }}" class="inline"
                                      onsubmit="return confirm('Delete question {{ $i + 1 }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No questions yet. Download the template, fill it in and upload it above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
