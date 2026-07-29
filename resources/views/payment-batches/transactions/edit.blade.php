@extends('layouts.app', ['title' => 'Correct Transaction'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Correction</p>
            <h1>Transaction #{{ $transaction->id }}</h1>
        </div>
        <a class="button button-ghost" href="{{ route('payment-batches.show', $batch) }}">Back to Batch</a>
    </div>

    @if($transaction->validation_errors)
        <section class="panel">
            <h2>Validation</h2>
            <ul class="mini-list">
                @foreach($transaction->validation_errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="panel">
        <form method="post" action="{{ route('payment-transactions.update', [$batch, $transaction]) }}" class="stack">
            @csrf
            @method('PATCH')

            <div class="editor-grid fields">
                @foreach($fields as $field)
                    @php
                        $key = $field->key;
                        $value = old("fields.$key", $values[$key] ?? '');
                        $isLong = in_array($key, ['supplier_address', 'email', 'purpose_of_payment'], true);
                    @endphp
                    <label class="{{ $isLong ? 'wide' : '' }}">
                        {{ $field->label }}
                        @if($isLong)
                            <textarea name="fields[{{ $key }}]" rows="3">{{ $value }}</textarea>
                        @else
                            <input name="fields[{{ $key }}]" value="{{ $value }}">
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="filter-actions">
                <button class="button button-primary" type="submit">Save Correction</button>
                <a class="button button-ghost" href="{{ route('payment-batches.show', $batch) }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
