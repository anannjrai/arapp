@extends('layouts.app', ['title' => 'Country Reason Codes'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Master Data</p>
            <h1>Country Reason Codes</h1>
        </div>
    </div>

    <section class="panel">
        <form method="get" class="filter-grid compact">
            <label>Country<input name="country_code" value="{{ $filters['country_code'] ?? '' }}"></label>
            <label>Reason<input name="reason_code" value="{{ $filters['reason_code'] ?? '' }}"></label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('country-reason-codes.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Add Code</h2>
        <form method="post" action="{{ route('country-reason-codes.store') }}" class="editor-grid">
            @csrf
            <label>Country<input name="country_code" required></label>
            <label>Reason<input name="reason_code" maxlength="20" required></label>
            <label class="wide">Description<input name="description" required></label>
            <label class="check-row"><input name="is_active" type="checkbox" value="1" checked> Active</label>
            <button class="button button-primary" type="submit">Save</button>
        </form>
    </section>

    <section class="record-list">
        @forelse($codes as $code)
            <div class="record-row">
                <form id="code-{{ $code->id }}" method="post" action="{{ route('country-reason-codes.update', $code) }}" class="editor-grid">
                    @csrf
                    @method('PATCH')
                    <label>Country<input name="country_code" value="{{ $code->country_code }}" required></label>
                    <label>Reason<input name="reason_code" maxlength="20" value="{{ $code->reason_code }}" required></label>
                    <label class="wide">Description<input name="description" value="{{ $code->description }}" required></label>
                    <label class="check-row"><input name="is_active" type="checkbox" value="1" @checked($code->is_active)> Active</label>
                    <button class="button button-primary" type="submit">Update</button>
                </form>
                <form method="post" action="{{ route('country-reason-codes.destroy', $code) }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-ghost" type="submit">Deactivate</button>
                </form>
            </div>
        @empty
            <div class="empty panel">No reason codes found.</div>
        @endforelse
        {{ $codes->links() }}
    </section>
@endsection
