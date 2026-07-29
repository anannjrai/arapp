@extends('layouts.app', ['title' => 'Bank Countries'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Master Data</p>
            <h1>Bank Countries</h1>
        </div>
    </div>

    <section class="panel">
        <form method="get" class="filter-grid compact">
            <label>Country<input name="country_name" value="{{ $filters['country_name'] ?? '' }}"></label>
            <label>Capital<input name="capital" value="{{ $filters['capital'] ?? '' }}"></label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('bank-countries.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Add Country</h2>
        <form method="post" action="{{ route('bank-countries.store') }}" class="editor-grid">
            @csrf
            <label>Country<input name="country_name" required></label>
            <label>Capital<input name="capital" required></label>
            <label class="check-row"><input name="is_active" type="checkbox" value="1" checked> Active</label>
            <button class="button button-primary" type="submit">Save</button>
        </form>
    </section>

    <section class="record-list">
        @forelse($countries as $country)
            <div class="record-row">
                <form id="country-{{ $country->id }}" method="post" action="{{ route('bank-countries.update', $country) }}" class="editor-grid">
                    @csrf
                    @method('PATCH')
                    <label>Country<input name="country_name" value="{{ $country->country_name }}" required></label>
                    <label>Capital<input name="capital" value="{{ $country->capital }}" required></label>
                    <label class="check-row"><input name="is_active" type="checkbox" value="1" @checked($country->is_active)> Active</label>
                    <button class="button button-primary" type="submit">Update</button>
                </form>
                <form method="post" action="{{ route('bank-countries.destroy', $country) }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-ghost" type="submit">Deactivate</button>
                </form>
            </div>
        @empty
            <div class="empty panel">No bank countries found.</div>
        @endforelse
        {{ $countries->links() }}
    </section>
@endsection
