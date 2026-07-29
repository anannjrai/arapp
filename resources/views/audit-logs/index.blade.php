@extends('layouts.app', ['title' => 'Audit Log'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Security</p>
            <h1>Audit Log</h1>
        </div>
    </div>

    <section class="panel">
        <form method="get" class="filter-grid compact">
            <label>Action
                <select name="action">
                    <option value="">All</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </label>
            <label>User
                <select name="user_id">
                    <option value="">All</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('audit-logs.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="data-section">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>IP</th>
                    <th>Metadata</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><span class="badge neutral">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span></td>
                        <td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                        <td>{{ $log->ip_address }}</td>
                        <td><code>{{ $log->metadata ? json_encode($log->metadata) : '' }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No audit events.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </section>
@endsection
