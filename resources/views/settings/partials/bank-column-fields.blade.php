<label>Position
    <input name="position" type="number" min="1" value="{{ old('position', $nextPosition) }}" required>
</label>
<label>Label
    <input name="column_label" value="{{ old('column_label', $column?->column_label) }}" required>
</label>
<label>Source
    <select name="source_field">
        <option value="">Static/blank</option>
        @foreach($fields as $field)
            <option value="{{ $field->key }}" @selected(old('source_field', $column?->source_field) === $field->key)>{{ $field->label }}</option>
        @endforeach
    </select>
</label>
<label>Static Value
    <input name="static_value" value="{{ old('static_value', $column?->static_value) }}">
</label>
<label>Max Length
    <input name="max_length" type="number" min="1" value="{{ old('max_length', $column?->max_length) }}">
</label>
<label>Padding
    <select name="padding_direction">
        @foreach(['none', 'left', 'right'] as $direction)
            <option value="{{ $direction }}" @selected(old('padding_direction', $column?->padding_direction ?? 'none') === $direction)>{{ ucfirst($direction) }}</option>
        @endforeach
    </select>
</label>
<label>Pad Char
    <input name="padding_character" maxlength="5" value="{{ old('padding_character', $column?->padding_character === ' ' ? '' : $column?->padding_character) }}" placeholder="space">
</label>
<label>Format
    <input name="format" value="{{ old('format', $column?->format) }}">
</label>
<label class="check-row"><input name="is_required" type="checkbox" value="1" @checked(old('is_required', $column?->is_required ?? false))> Required</label>
<label class="check-row"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $column?->is_active ?? true))> Active</label>
