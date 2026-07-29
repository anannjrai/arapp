@extends('layouts.app', ['title' => 'Log In'])

@section('content')
    <section class="auth-panel">
        <div>
            <div class="brand large">
                <span class="brand-mark">SP</span>
                <span>Supplier Payments</span>
            </div>
            <h1>Log in</h1>
        </div>

        <form method="post" action="{{ route('login.store') }}" class="stack">
            @csrf
            <label>
                Username or Email
                <input name="login" value="{{ old('login', 'admin') }}" autocomplete="username" required autofocus>
            </label>
            <label>
                Password
                <input name="password" type="password" autocomplete="current-password" required>
            </label>
            <label class="check-row">
                <input name="remember" type="checkbox" value="1">
                Remember me
            </label>
            <button class="button button-primary" type="submit">Log In</button>
        </form>
    </section>
@endsection
