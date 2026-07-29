<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MasterField;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterFieldController extends Controller
{
    public function index(): View
    {
        return view('settings.master-fields', [
            'fields' => MasterField::query()->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $field = MasterField::create($validated);

        $auditLogger->record('master_update', $field, ['master' => 'master_fields', 'operation' => 'create']);

        return back()->with('status', 'Master field added.');
    }

    public function update(Request $request, MasterField $masterField, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request, $masterField);
        $masterField->update($validated);

        $auditLogger->record('master_update', $masterField, ['master' => 'master_fields', 'operation' => 'update']);

        return back()->with('status', 'Master field updated.');
    }

    public function destroy(MasterField $masterField, AuditLogger $auditLogger): RedirectResponse
    {
        $masterField->update(['is_active' => false]);
        $auditLogger->record('master_update', $masterField, ['master' => 'master_fields', 'operation' => 'deactivate']);

        return back()->with('status', 'Master field deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?MasterField $existing = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('master_fields', 'key')->ignore($existing)],
            'label' => ['required', 'string', 'max:120'],
            'data_type' => ['required', Rule::in(['text', 'number', 'date', 'currency', 'country', 'reason_code'])],
            'is_required' => ['nullable', 'boolean'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'import_aliases_text' => ['nullable', 'string', 'max:1000'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['import_aliases'] = collect(explode(',', $validated['import_aliases_text'] ?? ''))
            ->map(fn ($alias) => trim($alias))
            ->filter()
            ->values()
            ->all();

        unset($validated['import_aliases_text']);

        return $validated;
    }
}
