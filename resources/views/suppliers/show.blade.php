@extends('layouts.app', ['title' => $supplier->supplier_name])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Supplier</p>
            <h1>{{ $supplier->supplier_name }}</h1>
        </div>
        <a class="button button-ghost" href="{{ route('suppliers.index') }}">Back</a>
    </div>

    <section class="panel detail-grid">
        @foreach([
            'Beneficiary bank name' => $supplier->beneficiary_bank_name,
            'IBAN / Account No.' => $supplier->beneficiary_bank_account,
            'Supplier address' => $supplier->supplier_address,
            'Email' => $supplier->email,
            'Purpose of payment' => $supplier->purpose_of_payment,
            'Beneficiary bank country' => $supplier->beneficiary_bank_country,
            'BIC Code' => $supplier->bic_code,
            'US Routing No.' => $supplier->us_routing_no,
            'UK Sort code' => $supplier->uk_sort_code,
            'Bank Charges' => $supplier->bank_charges,
            'Purpose code' => $supplier->purpose_code,
            'Country purpose code' => $supplier->country_purpose_code,
            'Address / Country' => $supplier->address_country,
            'State' => $supplier->state,
            'City' => $supplier->city,
            'Last imported' => optional($supplier->last_imported_at)->format('Y-m-d H:i'),
        ] as $label => $value)
            <div>
                <span>{{ $label }}</span>
                <strong>{{ $value ?: '-' }}</strong>
            </div>
        @endforeach
    </section>

    <section class="data-section">
        <div class="section-title">
            <h2>Payment History</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Batch</th>
                    <th>Payment No.</th>
                    <th>Amount</th>
                    <th>Purpose</th>
                    <th>Country Purpose</th>
                    <th>Status</th>
                    <th>Imported</th>
                </tr>
                </thead>
                <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td><a href="{{ route('payment-batches.show', $transaction->batch) }}">{{ $transaction->batch->batch_reference }}</a></td>
                        <td>{{ $transaction->payment_no }}</td>
                        <td class="numeric">{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</td>
                        <td>{{ $transaction->purpose_code }}</td>
                        <td>{{ $transaction->country_purpose_code }}</td>
                        <td><span class="badge {{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span></td>
                        <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No payment history for this supplier.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </section>
@endsection
