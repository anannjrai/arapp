@extends('layouts.app', ['title' => 'Master Fields'])

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Master Data</p>
            <h1>Master Fields</h1>
        </div>
    </div>

    <section class="panel">
        <h2>Add Field</h2>
        <form method="post" action="{{ route('master-fields.store') }}" class="editor-grid fields">
            @csrf
            <label>Key<input name="key" required pattern="[a-z][a-z0-9_]*"></label>
            <label>Label<input name="label" required></label>
            <label>Type
                <select name="data_type" required>
                    @foreach(['text', 'number', 'date', 'currency', 'country', 'reason_code'] as $type)
                        <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Default<input name="default_value"></label>
            <label>Order<input name="sort_order" type="number" min="0" value="0"></label>
            <label class="wide">Import Aliases<input name="import_aliases_text"></label>
            <label class="wide">Help Text<input name="help_text"></label>
            <label class="check-row"><input name="is_required" type="checkbox" value="1"> Required</label>
            <label class="check-row"><input name="is_active" type="checkbox" value="1" checked> Active</label>
            <button class="button button-primary" type="submit">Save</button>
        </form>
    </section>

    <section class="record-list">
        @foreach($fields as $field)
            <div class="record-row">
                <form method="post" action="{{ route('master-fields.update', $field) }}" class="editor-grid fields">
                    @csrf
                    @method('PATCH')
                    <label>Key<input name="key" value="{{ $field->key }}" required pattern="[a-z][a-z0-9_]*"></label>
                    <label>Label<input name="label" value="{{ $field->label }}" required></label>
                    <label>Type
                        <select name="data_type" required>
                            @foreach(['text', 'number', 'date', 'currency', 'country', 'reason_code'] as $type)
                                <option value="{{ $type }}" @selected($field->data_type === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Default<input name="default_value" value="{{ $field->default_value }}"></label>
                    <label>Order<input name="sort_order" type="number" min="0" value="{{ $field->sort_order }}"></label>
                    <label class="wide">Import Aliases<input name="import_aliases_text" value="{{ implode(', ', $field->import_aliases ?? []) }}"></label>
                    <label class="wide">Help Text<input name="help_text" value="{{ $field->help_text }}"></label>
                    <label class="check-row"><input name="is_required" type="checkbox" value="1" @checked($field->is_required)> Required</label>
                    <label class="check-row"><input name="is_active" type="checkbox" value="1" @checked($field->is_active)> Active</label>
                    <button class="button button-primary" type="submit">Update</button>
                </form>
                <form method="post" action="{{ route('master-fields.destroy', $field) }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-ghost" type="submit">Deactivate</button>
                </form>
            </div>
        @endforeach
    </section>
@endsection
