@extends('layouts.app', ['title' => 'Bank Format'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Export</p>
            <h1>Bank Format</h1>
        </div>
    </div>

    <section class="panel">
        <h2>Format Settings</h2>
        <form method="post" action="{{ route('bank-format.update', $format) }}" class="editor-grid">
            @csrf
            @method('PATCH')
            <label>Name<input name="name" value="{{ $format->name }}" required></label>
            <label>Delimiter<input name="delimiter" value="{{ $format->delimiter }}" maxlength="5" required></label>
            <label>Extension<input name="extension" value="{{ $format->extension }}" maxlength="10" required></label>
            <label class="wide">Filename Pattern<input name="filename_pattern" value="{{ $format->filename_pattern }}" required></label>
            <label>Date Format<input name="date_format" value="{{ $format->date_format }}" required></label>
            <label>Decimals<input name="decimal_places" type="number" min="0" max="6" value="{{ $format->decimal_places }}" required></label>
            <label class="check-row"><input name="include_header" type="checkbox" value="1" @checked($format->include_header)> Header</label>
            <label class="check-row"><input name="trailing_delimiter" type="checkbox" value="1" @checked($format->trailing_delimiter)> Trailing delimiter</label>
            <label class="check-row"><input name="is_active" type="checkbox" value="1" @checked($format->is_active)> Active</label>
            <button class="button button-primary" type="submit">Save</button>
        </form>
    </section>

    <section class="panel">
        <h2>Add Column</h2>
        <form method="post" action="{{ route('bank-format.columns.store', $format) }}" class="editor-grid columns">
            @csrf
            @include('settings.partials.bank-column-fields', [
                'column' => null,
                'fields' => $fields,
                'nextPosition' => ($format->columns->max('position') ?? 0) + 1,
            ])
            <button class="button button-primary" type="submit">Add Column</button>
        </form>
    </section>

    <section class="record-list">
        @foreach($format->columns as $column)
            <div class="record-row">
                <form method="post" action="{{ route('bank-format.columns.update', $column) }}" class="editor-grid columns">
                    @csrf
                    @method('PATCH')
                    @include('settings.partials.bank-column-fields', [
                        'column' => $column,
                        'fields' => $fields,
                        'nextPosition' => $column->position,
                    ])
                    <button class="button button-primary" type="submit">Update</button>
                </form>
                <form method="post" action="{{ route('bank-format.columns.destroy', $column) }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-ghost" type="submit">Deactivate</button>
                </form>
            </div>
        @endforeach
    </section>
@endsection
