@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Operations</p>
            <div class="dashboard-title-row">
                <h1>Dashboard</h1>
                <form class="header-exit-form" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-ghost button-small" type="submit">Exit</button>
                </form>
            </div>
        </div>
        @if(auth()->user()->hasRole(['preparer']))
            <a class="button button-primary" href="{{ route('payment-batches.create') }}">Import Payments</a>
        @endif
    </div>

    <section class="stats-grid">
        <div class="stat-card"><span>Batches</span><strong>{{ number_format($summary['batches']) }}</strong></div>
        <div class="stat-card"><span>Transactions</span><strong>{{ number_format($summary['transactions']) }}</strong></div>
        <div class="stat-card"><span>Total Amount</span><strong>{{ number_format($summary['amount'], 2) }}</strong></div>
        <div class="stat-card"><span>Invalid Rows</span><strong>{{ number_format($summary['invalid']) }}</strong></div>
        <div class="stat-card"><span>Exported Batches</span><strong>{{ number_format($summary['exported_batches']) }}</strong></div>
    </section>

    <section class="panel">
        <form method="get" class="filter-grid">
            <label>Batch Ref<input name="batch_reference" value="{{ $filters['batch_reference'] ?? '' }}"></label>
            <label>Supplier<input name="supplier_name" value="{{ $filters['supplier_name'] ?? '' }}"></label>
            <label>Payment No.<input name="payment_reference" value="{{ $filters['payment_reference'] ?? '' }}"></label>
            <label>Country<input name="country_code" value="{{ $filters['country_code'] ?? '' }}"></label>
            <label>Purpose<input name="reason_code" value="{{ $filters['reason_code'] ?? '' }}"></label>
            <label>Currency
                <select name="currency">
                    <option value="">All</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </label>
            <label>Row Status
                <select name="status">
                    <option value="">All</option>
                    @foreach(['pending', 'verified', 'exported', 'invalid'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Batch Status
                <select name="batch_status">
                    <option value="">All</option>
                    @foreach(['draft', 'needs_review', 'verified', 'exported'] as $status)
                        <option value="{{ $status }}" @selected(($filters['batch_status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Imported From<input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label>Imported To<input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label>Min<input name="amount_min" type="number" step="0.01" value="{{ $filters['amount_min'] ?? '' }}"></label>
            <label>Max<input name="amount_max" type="number" step="0.01" value="{{ $filters['amount_max'] ?? '' }}"></label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('dashboard') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="data-section">
        <div class="section-title">
            <h2>Transaction Enquiry</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Batch</th>
                    <th>Supplier</th>
                    <th>Amount</th>
                    <th>Imported</th>
                    <th>Country</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Payment No.</th>
                </tr>
                </thead>
                <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td><a href="{{ route('payment-batches.show', $transaction->batch) }}">{{ $transaction->batch->batch_reference }}</a></td>
                        <td>{{ $transaction->supplier_name }}</td>
                        <td class="numeric">{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</td>
                        <td>{{ $transaction->created_at->format('Y-m-d') }}</td>
                        <td>{{ $transaction->country_code }}</td>
                        <td>{{ $transaction->reason_code }}</td>
                        <td><span class="badge {{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span></td>
                        <td>{{ $transaction->payment_reference }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No matching transactions.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </section>
@endsection
