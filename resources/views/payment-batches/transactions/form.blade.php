@extends('layouts.app', ['title' => 'Payment Form'])

@php
    $sections = [
        'Payment' => ['transfer_type', 'payment_no', 'amount', 'currency', 'bank_charges', 'purpose_code', 'country_purpose_code'],
        'Beneficiary' => ['supplier_name', 'supplier_address', 'email', 'address_country'],
        'Bank' => ['beneficiary_bank_name', 'beneficiary_bank_account', 'beneficiary_bank_country', 'bic_code', 'us_routing_no', 'uk_sort_code'],
        'Reference' => ['purpose_of_payment', 'future1', 'future2', 'future3'],
    ];
    $labels = $fields->keyBy('key')->map(fn ($field) => $field->label);
@endphp

@section('content')
    <div class="page-heading no-print">
        <div>
            <p class="eyebrow">Payment Form</p>
            <h1>{{ $batch->batch_reference }}</h1>
        </div>
        <div class="filter-actions">
            <button class="button button-primary" type="button" onclick="window.print()">Print</button>
            <a class="button button-ghost" href="{{ route('payment-batches.show', $batch) }}">Back to Batch</a>
        </div>
    </div>

    <section class="payment-form-sheet">
        <header class="payment-form-header">
            <div>
                <span>Batch</span>
                <strong>{{ $batch->batch_reference }}</strong>
            </div>
            <div>
                <span>Record</span>
                <strong>{{ $position }} / {{ $total }}</strong>
            </div>
            <div>
                <span>Payment No.</span>
                <strong>{{ $transaction->payment_no ?: '-' }}</strong>
            </div>
            <div>
                <span>Status</span>
                <strong>{{ ucfirst($transaction->status) }}</strong>
            </div>
            <div>
                <span>Amount</span>
                <strong>{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</strong>
            </div>
        </header>

        @if($transaction->validation_errors)
            <div class="payment-form-errors">
                <strong>Validation</strong>
                <ul class="mini-list">
                    @foreach($transaction->validation_errors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="payment-form-sections">
            @foreach($sections as $sectionTitle => $keys)
                <section class="payment-form-section">
                    <h2>{{ $sectionTitle }}</h2>
                    <div class="payment-form-grid">
                        @foreach($keys as $key)
                            @php($value = $values[$key] ?? null)
                            <div @class(['span-2' => in_array($key, ['supplier_address', 'email', 'purpose_of_payment'], true)])>
                                <span>{{ $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</span>
                                <strong>{{ filled($value) ? $value : '-' }}</strong>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <footer class="payment-form-footer">
            <div>
                <span>Source</span>
                <strong>{{ $batch->source_file_name ?: '-' }}</strong>
            </div>
            <div>
                <span>Uploaded</span>
                <strong>{{ $batch->uploadedBy?->name ?: '-' }} · {{ $batch->created_at->format('Y-m-d H:i') }}</strong>
            </div>
            <div>
                <span>Generated</span>
                <strong>{{ now()->format('Y-m-d H:i') }}</strong>
            </div>
        </footer>
    </section>

    <nav class="payment-form-nav no-print" aria-label="Payment record navigation">
        @if($previousTransaction)
            <a class="button button-ghost" href="{{ route('payment-transactions.form', [$batch, $previousTransaction]) }}">Previous Payment</a>
        @else
            <span class="button button-ghost disabled-button">Previous Payment</span>
        @endif

        <span class="muted">Payment {{ $position }} of {{ $total }}</span>

        @if($nextTransaction)
            <a class="button button-primary" href="{{ route('payment-transactions.form', [$batch, $nextTransaction]) }}">Next Payment</a>
        @else
            <span class="button button-ghost disabled-button">Next Payment</span>
        @endif
    </nav>
@endsection
