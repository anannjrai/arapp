@extends('layouts.app', ['title' => 'Import Payments'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Workflow</p>
            <h1>Import Payments</h1>
        </div>
        <a class="button button-ghost" href="{{ route('payment-batches.template') }}">Download Template</a>
    </div>

    <section class="panel narrow">
        <form method="post" action="{{ route('payment-batches.store') }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label>
                Payment file
                <input type="file" name="payment_file" accept=".csv,.txt,text/csv,text/plain" required>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="4">{{ old('notes') }}</textarea>
            </label>
            <button class="button button-primary" type="submit">Import</button>
        </form>
    </section>

    <section class="data-section">
        <div class="section-title">
            <h2>Template Headers</h2>
        </div>
        <div class="chips">
            @foreach($headers as $header)
                <span>{{ $header }}</span>
            @endforeach
        </div>
    </section>
@endsection
