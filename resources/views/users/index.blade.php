@extends('layouts.app', ['title' => 'Users'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Users</h1>
        </div>
    </div>

    <section class="panel">
        <h2>Add User</h2>
        <form method="post" action="{{ route('users.store') }}" class="editor-grid fields">
            @csrf
            <label>User name<input name="name" value="{{ old('name') }}" required></label>
            <label>Login username<input name="username" value="{{ old('username') }}" required></label>
            <label>Email<input name="email" type="email" value="{{ old('email') }}" required></label>
            <label>Password<input name="password" type="password" required></label>
            <label>Role
                <select name="role" required>
                    @foreach($roles as $role => $label)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="check-row"><input name="is_active" type="checkbox" value="1" checked> Active</label>
            <button class="button button-primary" type="submit">Create User</button>
        </form>
    </section>

    <section class="record-list">
        @foreach($users as $user)
            <div class="record-row">
                <form method="post" action="{{ route('users.update', $user) }}" class="editor-grid fields">
                    @csrf
                    @method('PATCH')
                    <label>User name<input name="name" value="{{ $user->name }}" required></label>
                    <label>Login username<input name="username" value="{{ $user->username }}" required></label>
                    <label>Email<input name="email" type="email" value="{{ $user->email }}" required></label>
                    <label>New Password<input name="password" type="password" placeholder="Leave blank"></label>
                    <label>Role
                        <select name="role" required>
                            @foreach($roles as $role => $label)
                                <option value="{{ $role }}" @selected($user->role === $role)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="check-row"><input name="is_active" type="checkbox" value="1" @checked($user->is_active)> Active</label>
                    <button class="button button-primary" type="submit">Update</button>
                </form>
                <div class="user-row-meta">
                    <span class="badge neutral">{{ $user->roleLabel() }}</span>
                    <span class="badge {{ $user->is_active ? 'verified' : 'invalid' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="muted">Created {{ $user->created_at->format('Y-m-d H:i') }}</span>
                </div>
                <form method="post" action="{{ route('users.destroy', $user) }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-ghost" type="submit" @disabled($user->is(auth()->user()))>Deactivate</button>
                </form>
            </div>
        @endforeach
    </section>
@endsection
