@once
@push('styles')
<style>
    .top { background: var(--brand); color: #fff; }
    .top-inner { max-width: var(--top-max, 1100px); margin: 0 auto; padding: 12px 24px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .top h1 { font-size: 1.05rem; margin: 0 12px 0 0; }
    .top nav { display: flex; gap: 4px; flex: 1; flex-wrap: wrap; }
    .top nav a { color: rgba(255,255,255,.82); text-decoration: none; font-weight: 600; font-size: .9rem; padding: 6px 12px; border-radius: 8px; }
    .top nav a:hover { color: #fff; background: rgba(255,255,255,.1); }
    .top nav a[aria-current=page] { color: #fff; background: rgba(255,255,255,.18); }
    .top .btn { background: transparent; border-color: rgba(255,255,255,.5); }
</style>
@endpush
@endonce
<div class="top">
    <div class="top-inner">
        <h1>{{ $title ?? 'Lecturer' }}</h1>
        <nav aria-label="Lecturer">
            <a href="{{ route('admin.dashboard') }}" @if ($active === 'dashboard') aria-current="page" @endif>Dashboard</a>
            <a href="{{ route('admin.monitor') }}" @if ($active === 'monitor') aria-current="page" @endif>Live monitor</a>
            <a href="{{ route('admin.results') }}" @if ($active === 'results') aria-current="page" @endif>Results</a>
        </nav>
        <a class="btn btn-sm" href="{{ route('student.create') }}" target="_blank">Student page ↗</a>
        <form method="POST" action="{{ route('admin.logout') }}" class="inline" style="display:inline">
            @csrf
            <button class="btn btn-sm" type="submit">Log out</button>
        </form>
    </div>
</div>
