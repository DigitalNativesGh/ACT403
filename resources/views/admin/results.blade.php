@extends('layouts.app')

@section('page_title', 'Results · '.$title)

@push('styles')
<style>
    .page { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
    .stat { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 14px 16px; }
    .stat b { display: block; font-size: 1.6rem; font-variant-numeric: tabular-nums; }
    .stat span { font-size: .8rem; color: var(--muted); }
    .section-title { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
    .section-title h2 { font-size: 1.1rem; margin: 0; flex: 1; }
    .search { max-width: 260px; }
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: middle; }
    th { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); background: var(--paper); }
    td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .table-wrap { overflow-x: auto; }
    .inline { display: inline; }
    .empty { text-align: center; color: var(--muted); padding: 24px; }
    .deduct { color: var(--danger); font-weight: 700; }
    .pill-danger { background: var(--danger-soft); color: var(--danger); }
    @media (max-width: 860px) { .stats { grid-template-columns: repeat(2, 1fr); } .search { max-width: none; } }
</style>
@endpush

@section('content')
@include('admin._nav', ['active' => 'results'])

<div class="page">
    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif

    <div class="stats">
        <div class="stat"><b>{{ $stats['candidates'] }}</b><span>Candidates</span></div>
        <div class="stat"><b>{{ $stats['submitted'] }}</b><span>Submitted ({{ $stats['in_progress'] }} still writing)</span></div>
        <div class="stat"><b>{{ $stats['average'] !== null ? $stats['average'].'%' : '—' }}</b><span>Class average</span></div>
        <div class="stat"><b>{{ $stats['highest'] !== null ? $stats['highest'].'%' : '—' }}</b><span>Highest score</span></div>
    </div>

    <section class="card">
        <div class="section-title">
            <h2>All candidates</h2>
            <input type="text" class="search" id="search" placeholder="Search index number or UIN" aria-label="Search candidates">
            <a class="btn btn-sm" href="{{ route('admin.results.export') }}">Download CSV</a>
            @if ($attempts->isNotEmpty())
                <form method="POST" action="{{ route('admin.attempts.destroyAll') }}" class="inline"
                      onsubmit="return confirm('Delete ALL results? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm" type="submit">Clear all</button>
                </form>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Index number</th><th>UIN</th><th>Status</th><th title="Correct answers before deductions">Raw score</th><th title="Marks deducted by AI proctoring">Deducted</th><th title="Raw score minus deductions">Net score</th><th title="Net score as a percentage">Net %</th><th title="Talking / head turns detected">Talk / Head</th><th>Started</th><th>Time taken</th><th></th></tr>
                </thead>
                <tbody id="rows">
                    @forelse ($attempts as $a)
                        <tr data-search="{{ strtolower($a->index_number.' '.$a->uin) }}">
                            <td><b>{{ $a->index_number }}</b></td>
                            <td>{{ $a->uin }}</td>
                            <td>
                                @if ($a->closed_reason === \App\Models\Attempt::CLOSED_FOR_TALKING)
                                    <span class="pill pill-danger">Closed: talking</span>
                                @elseif ($a->isSubmitted())
                                    <span class="pill pill-ok">Submitted</span>
                                @else
                                    <span class="pill pill-warn">Writing</span>
                                @endif
                            </td>
                            <td class="num">{{ $a->isSubmitted() ? $a->raw_score.' / '.$a->total : '—' }}</td>
                            <td class="num">@if ($a->penalty)<span class="deduct">−{{ $a->penalty }}</span>@else 0 @endif</td>
                            <td class="num"><b>{{ $a->isSubmitted() ? $a->score.' / '.$a->total : '—' }}</b></td>
                            <td class="num">{{ $a->isSubmitted() ? $a->percentage().'%' : '—' }}</td>
                            <td class="num">{{ $a->talk_count }} / {{ $a->head_turn_count }}</td>
                            <td class="num">{{ $a->started_at->format('d M, H:i') }}</td>
                            <td class="num">{{ $a->timeTaken() ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.attempts.destroy', $a) }}" class="inline"
                                      onsubmit="return confirm('Remove this attempt so {{ $a->index_number }} can retake the quiz?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Allow retake</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="empty">No candidates yet.</td></tr>
                    @endforelse
                    <tr id="no-match" hidden><td colspan="11" class="empty">No candidate matches your search.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('search');
    const rows = document.querySelectorAll('#rows tr[data-search]');
    const none = document.getElementById('no-match');
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(r => { const hit = r.dataset.search.includes(q); r.hidden = !hit; if (hit) shown++; });
        none.hidden = shown > 0 || rows.length === 0;
    });
})();
</script>
@endpush
