@extends('layouts.app', ['title' => 'Payment Batches'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Workflow</p>
            <h1>Payment Batches</h1>
        </div>
        @if(auth()->user()->hasRole(['preparer']))
            <a class="button button-primary" href="{{ route('payment-batches.create') }}">Import Payments</a>
        @endif
    </div>

    <section class="panel">
        <form method="get" class="filter-grid compact">
            <label>Batch Ref<input name="batch_reference" value="{{ $filters['batch_reference'] ?? '' }}"></label>
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    @foreach(['draft', 'needs_review', 'verified', 'exported'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('payment-batches.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="data-section">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Batch Reference</th>
                    <th>Status</th>
                    <th>Rows</th>
                    <th>Invalid</th>
                    <th>Total</th>
                    <th>Uploaded By</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td><a href="{{ route('payment-batches.show', $batch) }}">{{ $batch->batch_reference }}</a></td>
                        <td><span class="badge {{ $batch->status }}">{{ str_replace('_', ' ', ucfirst($batch->status)) }}</span></td>
                        <td class="numeric">{{ number_format($batch->row_count) }}</td>
                        <td class="numeric">{{ number_format($batch->invalid_count) }}</td>
                        <td class="numeric">{{ $batch->currency }} {{ number_format((float) $batch->total_amount, 2) }}</td>
                        <td>{{ $batch->uploadedBy?->name }}</td>
                        <td>{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            @if(auth()->user()->isAdmin())
                                <form method="post" action="{{ route('payment-batches.destroy', $batch) }}" onsubmit="return confirm('Delete this batch and all its transactions? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-danger button-small" type="submit">Delete</button>
                                </form>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No payment batches.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $batches->links() }}
    </section>
@endsection
