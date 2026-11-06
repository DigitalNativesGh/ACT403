@extends('layouts.app')

@section('page_title', 'Lecturer login')

@section('content')
<div style="min-height:100vh;display:grid;place-items:center;padding:24px 16px">
    <div class="card" style="width:100%;max-width:380px">
        <h1>Lecturer login</h1>
        <p class="muted small">Upload questions, set the timer and view results.</p>

        @unless ($configured)
            <div class="alert alert-error">
                No admin password is set. Add <code>ADMIN_PASSWORD=your-password</code> to the <code>.env</code> file, then run <code>php artisan config:clear</code>.
            </div>
        @endunless

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.attempt') }}">
            @csrf
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit" class="btn btn-block">Log in</button>
        </form>
        <p class="small" style="text-align:center;margin:16px 0 0"><a href="{{ route('student.create') }}">← Student page</a></p>
    </div>
</div>
@endsection
