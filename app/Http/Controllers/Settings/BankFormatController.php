<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BankFileColumn;
use App\Models\BankFileFormat;
use App\Models\MasterField;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankFormatController extends Controller
{
    public function index(): View
    {
        $format = BankFileFormat::query()->with('columns')->orderBy('name')->firstOrFail();

        return view('settings.bank-format', [
            'format' => $format,
            'fields' => MasterField::query()->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function updateFormat(Request $request, BankFileFormat $bankFileFormat, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('bank_file_formats', 'name')->ignore($bankFileFormat)],
            'delimiter' => ['required', 'string', 'max:5'],
            'extension' => ['required', 'string', 'max:10'],
            'include_header' => ['nullable', 'boolean'],
            'filename_pattern' => ['required', 'string', 'max:160'],
            'trailing_delimiter' => ['nullable', 'boolean'],
            'date_format' => ['required', 'string', 'max:30'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['include_header'] = $request->boolean('include_header');
        $validated['trailing_delimiter'] = $request->boolean('trailing_delimiter');
        $validated['is_active'] = $request->boolean('is_active');
        $bankFileFormat->update($validated);

        $auditLogger->record('master_update', $bankFileFormat, ['master' => 'bank_file_formats', 'operation' => 'update']);

        return back()->with('status', 'Bank format settings saved.');
    }

    public function storeColumn(Request $request, BankFileFormat $bankFileFormat, AuditLogger $auditLogger): RedirectResponse
    {
        $column = $bankFileFormat->columns()->create($this->validatedColumn($request));
        $auditLogger->record('master_update', $column, ['master' => 'bank_file_columns', 'operation' => 'create']);

        return back()->with('status', 'Bank format column added.');
    }

    public function updateColumn(Request $request, BankFileColumn $bankFileColumn, AuditLogger $auditLogger): RedirectResponse
    {
        $bankFileColumn->update($this->validatedColumn($request, $bankFileColumn));
        $auditLogger->record('master_update', $bankFileColumn, ['master' => 'bank_file_columns', 'operation' => 'update']);

        return back()->with('status', 'Bank format column updated.');
    }

    public function destroyColumn(BankFileColumn $bankFileColumn, AuditLogger $auditLogger): RedirectResponse
    {
        $bankFileColumn->update(['is_active' => false]);
        $auditLogger->record('master_update', $bankFileColumn, ['master' => 'bank_file_columns', 'operation' => 'deactivate']);

        return back()->with('status', 'Bank format column deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedColumn(Request $request, ?BankFileColumn $existing = null): array
    {
        $formatId = $existing?->bank_file_format_id ?? $request->route('bankFileFormat')?->id;

        $validated = $request->validate([
            'position' => [
                'required',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('bank_file_columns', 'position')->where('bank_file_format_id', $formatId)->ignore($existing),
            ],
            'column_label' => ['required', 'string', 'max:120'],
            'source_field' => ['nullable', 'string', 'max:120', Rule::exists('master_fields', 'key')],
            'static_value' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'padding_direction' => ['required', Rule::in(['none', 'left', 'right'])],
            'padding_character' => ['nullable', 'string', 'max:5'],
            'format' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['source_field'] = $validated['source_field'] ?: null;
        $validated['static_value'] = $validated['static_value'] ?: null;
        $validated['padding_character'] = $validated['padding_character'] ?? ' ';

        return $validated;
    }
}
