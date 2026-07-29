<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Supplier Payments' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="app-shell">
    @auth
        <aside class="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark">SP</span>
                <span>Supplier Payments</span>
            </a>
            <nav class="nav-list">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('payment-batches.index') }}" class="{{ request()->routeIs('payment-batches.*') ? 'active' : '' }}">Payment Batches</a>
                <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">Suppliers</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Users</a>
                    <a href="{{ route('bank-countries.index') }}" class="{{ request()->routeIs('bank-countries.*') ? 'active' : '' }}">Bank Countries</a>
                    <a href="{{ route('country-reason-codes.index') }}" class="{{ request()->routeIs('country-reason-codes.*') ? 'active' : '' }}">Reason Codes</a>
                    <a href="{{ route('master-fields.index') }}" class="{{ request()->routeIs('master-fields.*') ? 'active' : '' }}">Master Fields</a>
                    <a href="{{ route('bank-format.index') }}" class="{{ request()->routeIs('bank-format.*') ? 'active' : '' }}">Bank Format</a>
                    <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">Audit Log</a>
                @endif
            </nav>
            <div class="sidebar-footer">
                <div class="user-chip">
                    <span>{{ auth()->user()->name }}</span>
                    <small>{{ auth()->user()->username }} · {{ auth()->user()->roleLabel() }}</small>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-ghost full-width" type="submit">Log Out</button>
                </form>
            </div>
        </aside>
    @endauth

    <main class="main-content {{ auth()->check() ? '' : 'centered' }}">
        @if(session('status'))
            <div class="notice success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="notice error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
