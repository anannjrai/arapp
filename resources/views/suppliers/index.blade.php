@extends('layouts.app', ['title' => 'Suppliers'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Master Data</p>
            <h1>Suppliers</h1>
        </div>
        <div class="summary-strip">
            <div>
                <span>Suppliers</span>
                <strong>{{ number_format($summary['suppliers']) }}</strong>
            </div>
            <div>
                <span>Payments</span>
                <strong>{{ number_format($summary['payments']) }}</strong>
            </div>
            <div>
                <span>Total Amount</span>
                <strong>{{ number_format($summary['amount'], 2) }}</strong>
            </div>
        </div>
    </div>

    <section class="panel">
        @php($dateMode = $filters['date_mode'] ?? '')
        <form method="get" class="filter-grid">
            <label>Supplier
                <input name="supplier_name" list="supplierNameOptions" value="{{ $filters['supplier_name'] ?? '' }}">
            </label>
            <label>Bank Country
                <input name="bank_country" list="bankCountryOptions" value="{{ $filters['bank_country'] ?? '' }}">
            </label>
            <label>Purpose Code
                <input name="purpose_code" list="purposeCodeOptions" value="{{ $filters['purpose_code'] ?? '' }}">
            </label>
            <label>Account
                <input name="account" list="accountOptions" value="{{ $filters['account'] ?? '' }}">
            </label>
            <label>Payment Reference
                <input name="payment_reference" list="paymentReferenceOptions" value="{{ $filters['payment_reference'] ?? '' }}">
            </label>
            <label>Date Filter
                <select name="date_mode" id="supplierDateMode">
                    <option value="" @selected($dateMode === '')>All Dates</option>
                    <option value="date" @selected($dateMode === 'date')>Single Date</option>
                    <option value="period" @selected($dateMode === 'period')>Period</option>
                    <option value="month" @selected($dateMode === 'month')>Month</option>
                    <option value="year" @selected($dateMode === 'year')>Year</option>
                </select>
            </label>
            <label data-date-fields="date">
                Single Date
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}">
            </label>
            <label data-date-fields="period">
                From
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>
            <label data-date-fields="period">
                To
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>
            <label data-date-fields="month">
                Month
                <input type="month" name="month" value="{{ $filters['month'] ?? '' }}">
            </label>
            <label data-date-fields="year">
                Year
                <input name="year" type="number" min="2000" max="2100" step="1" list="yearOptions" value="{{ $filters['year'] ?? '' }}">
            </label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('suppliers.index') }}">Clear</a>
            </div>

            <datalist id="supplierNameOptions">
                @foreach($filterOptions['supplierNames'] as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
            <datalist id="bankCountryOptions">
                @foreach($filterOptions['bankCountries'] as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
            <datalist id="purposeCodeOptions">
                @foreach($filterOptions['purposeCodes'] as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
            <datalist id="accountOptions">
                @foreach($filterOptions['accounts'] as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
            <datalist id="paymentReferenceOptions">
                @foreach($filterOptions['paymentReferences'] as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
            <datalist id="yearOptions">
                @foreach($filterOptions['years'] as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
        </form>
    </section>

    <script>
        (() => {
            const dateMode = document.getElementById('supplierDateMode');
            const groups = document.querySelectorAll('[data-date-fields]');

            const syncDateFields = () => {
                const selectedMode = dateMode?.value || '';

                groups.forEach((group) => {
                    const visible = group.dataset.dateFields === selectedMode;
                    group.classList.toggle('is-hidden', !visible);
                    group.querySelectorAll('input, select, textarea').forEach((field) => {
                        field.disabled = !visible;
                    });
                });
            };

            dateMode?.addEventListener('change', syncDateFields);
            syncDateFields();
        })();
    </script>

    <section class="data-section">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Bank</th>
                    <th>Account</th>
                    <th>Country</th>
                    <th>Purpose</th>
                    <th>Payments</th>
                    <th>Total Amount</th>
                    <th>Last Imported</th>
                </tr>
                </thead>
                <tbody>
                @forelse($suppliers as $supplier)
                    <tr>
                        <td><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->supplier_name }}</a></td>
                        <td>{{ $supplier->beneficiary_bank_name }}</td>
                        <td>{{ $supplier->beneficiary_bank_account }}</td>
                        <td>{{ $supplier->beneficiary_bank_country }}</td>
                        <td>{{ $supplier->purpose_code }} {{ $supplier->country_purpose_code ? '/ '.$supplier->country_purpose_code : '' }}</td>
                        <td class="numeric">{{ number_format($supplier->transactions_count) }}</td>
                        <td class="numeric">{{ number_format((float) $supplier->transactions_sum_amount, 2) }}</td>
                        <td>{{ optional($supplier->last_imported_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No suppliers have been imported yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $suppliers->links() }}
    </section>
@endsection
