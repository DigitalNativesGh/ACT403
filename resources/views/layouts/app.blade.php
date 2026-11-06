<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page_title', 'Quiz')</title>
    <style>
        :root {
            --ink: #1c2321;
            --muted: #5f6b67;
            --line: #dfe5e2;
            --paper: #f6f8f7;
            --card: #ffffff;
            --brand: #0f5c4a;
            --brand-soft: #e3f0ec;
            --accent: #c98a1b;
            --danger: #b3261e;
            --danger-soft: #fbe9e7;
            --ok: #1e7d4f;
            --radius: 12px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background: var(--paper);
            line-height: 1.5;
            font-size: 16px;
        }
        a { color: var(--brand); }
        h1, h2, h3 { line-height: 1.25; margin: 0 0 .5rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 24px;
        }
        .muted { color: var(--muted); }
        .small { font-size: .875rem; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input[type=text], input[type=password], input[type=number], input[type=file] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #c7d1cd;
            border-radius: 8px;
            font: inherit;
            background: #fff;
        }
        input:focus { outline: 2px solid var(--brand); outline-offset: 1px; border-color: var(--brand); }
        .field { margin-bottom: 16px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px 18px;
            border-radius: 8px;
            border: 1px solid transparent;
            font: inherit; font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            background: var(--brand); color: #fff;
        }
        .btn:hover { filter: brightness(1.08); }
        .btn:disabled { opacity: .5; cursor: not-allowed; filter: none; }
        .btn-secondary { background: #fff; color: var(--brand); border-color: var(--brand); }
        .btn-danger { background: #fff; color: var(--danger); border-color: #e2b4b0; }
        .btn-sm { padding: 6px 10px; font-size: .85rem; }
        .btn-block { width: 100%; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .alert-error { background: var(--danger-soft); color: var(--danger); border: 1px solid #f1c6c1; }
        .alert-ok { background: var(--brand-soft); color: var(--brand); border: 1px solid #c3ded5; }
        .alert ul { margin: 6px 0 0; padding-left: 20px; }
        .error-text { color: var(--danger); font-size: .875rem; margin-top: 4px; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; }
        .pill-ok { background: var(--brand-soft); color: var(--ok); }
        .pill-off { background: #eef0ef; color: var(--muted); }
        .pill-warn { background: #fdf3e1; color: #8a5a00; }
    </style>
    @stack('styles')
</head>
<body class="@yield('body_class')">
    @yield('content')
    @stack('scripts')
</body>
</html>
