@extends('layouts.app', ['title' => $batch->batch_reference])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Batch</p>
            <h1>{{ $batch->batch_reference }}</h1>
        </div>
        <div class="filter-actions">
            @if($transactions->isNotEmpty())
                <a class="button button-primary" href="{{ route('payment-transactions.form', [$batch, $transactions->first()]) }}">Payment Form</a>
            @endif
            <a class="button button-ghost" href="{{ route('payment-batches.index') }}">Back</a>
            @if(auth()->user()->isAdmin())
                <form method="post" action="{{ route('payment-batches.destroy', $batch) }}" onsubmit="return confirm('Delete this batch and all its transactions? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button class="button button-danger" type="submit">Delete Batch</button>
                </form>
            @endif
        </div>
    </div>

    <section class="stats-grid">
        <div class="stat-card"><span>Status</span><strong>{{ str_replace('_', ' ', ucfirst($batch->status)) }}</strong></div>
        <div class="stat-card"><span>Rows</span><strong>{{ number_format($batch->row_count) }}</strong></div>
        <div class="stat-card"><span>Invalid</span><strong>{{ number_format($batch->invalid_count) }}</strong></div>
        <div class="stat-card"><span>Total</span><strong>{{ $batch->currency }} {{ number_format((float) $batch->total_amount, 2) }}</strong></div>
    </section>

    <section class="panel batch-actions">
        <div>
            <strong>Source</strong>
            <span>{{ $batch->source_file_name }}</span>
        </div>
        <div>
            <strong>Uploaded</strong>
            <span>{{ $batch->uploadedBy?->name }} · {{ $batch->created_at->format('Y-m-d H:i') }}</span>
        </div>
        @if($batch->reviewed_at)
            <div><strong>Verified</strong><span>{{ $batch->reviewedBy?->name }} · {{ $batch->reviewed_at->format('Y-m-d H:i') }}</span></div>
        @endif
        @if($batch->exported_at)
            <div><strong>Exported</strong><span>{{ $batch->exportedBy?->name }} · {{ $batch->exported_at->format('Y-m-d H:i') }}</span></div>
        @endif
        <div class="action-row">
            @if(auth()->user()->hasRole(['reviewer']))
                <form method="post" action="{{ route('payment-batches.review', $batch) }}">
                    @csrf
                    <button class="button button-primary" type="submit" @disabled(!$batch->isReviewable())>Mark Verified</button>
                </form>
            @endif
            @if(auth()->user()->hasRole(['exporter']))
                <form id="exportBatchForm" method="post" action="{{ route('payment-batches.export', $batch) }}" class="inline-form">
                    @csrf
                    <select name="bank_file_format_id" required>
                        @foreach($formats as $format)
                            <option value="{{ $format->id }}">{{ $format->name }}</option>
                        @endforeach
                    </select>
                    <button class="button button-primary" type="submit" @disabled(!$batch->isExportable())>Export Bank File</button>
                </form>
            @endif
        </div>
    </section>

    <section class="data-section">
        <div class="section-title">
            <h2>Payment Output Preview</h2>
            @if($previewFormat)
                <span class="muted">{{ $previewFormat->name }} · {{ $previewColumns->count() }} columns</span>
            @endif
        </div>
        @if($previewColumns->isEmpty())
            <div class="panel">
                <span class="empty">No active bank output columns.</span>
            </div>
        @else
            <div class="table-wrap output-preview">
                <table class="excel-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        @foreach($previewColumns as $column)
                            <th>
                                <span class="column-position">{{ $column->position }}</span>
                                {{ $column->column_label }}
                            </th>
                        @endforeach
                        <th>Status</th>
                        <th>Validation</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            @foreach($previewColumns as $column)
                                @php($value = $previewRows[$transaction->id][$loop->index] ?? '')
                                <td @class(['numeric' => $column->source_field === 'amount'])>{{ $value }}</td>
                            @endforeach
                            <td><span class="badge {{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span></td>
                            <td>
                                @if($transaction->validation_errors)
                                    <ul class="mini-list">
                                        @foreach($transaction->validation_errors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="muted">OK</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('payment-transactions.form', [$batch, $transaction]) }}">Form</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $previewColumns->count() + 4 }}" class="empty">No transactions.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
