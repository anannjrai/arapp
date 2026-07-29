<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CountryReasonCode;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CountryReasonCodeController extends Controller
{
    public function index(Request $request): View
    {
        return view('settings.country-reason-codes', [
            'codes' => CountryReasonCode::query()
                ->when($request->filled('country_code'), fn ($q) => $q->where('country_code', 'like', '%'.$request->string('country_code')->upper()->toString().'%'))
                ->when($request->filled('reason_code'), fn ($q) => $q->where('reason_code', 'like', '%'.$request->string('reason_code')->upper()->toString().'%'))
                ->orderBy('country_code')
                ->orderBy('reason_code')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $request->all(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $code = CountryReasonCode::updateOrCreate([
            'country_code' => $validated['country_code'],
            'reason_code' => $validated['reason_code'],
        ], $validated);

        $auditLogger->record('master_update', $code, ['master' => 'country_reason_codes', 'operation' => 'save']);

        return back()->with('status', 'Country reason code saved.');
    }

    public function update(Request $request, CountryReasonCode $countryReasonCode, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request, $countryReasonCode);
        $countryReasonCode->update($validated);

        $auditLogger->record('master_update', $countryReasonCode, ['master' => 'country_reason_codes', 'operation' => 'update']);

        return back()->with('status', 'Country reason code updated.');
    }

    public function destroy(CountryReasonCode $countryReasonCode, AuditLogger $auditLogger): RedirectResponse
    {
        $countryReasonCode->update(['is_active' => false]);
        $auditLogger->record('master_update', $countryReasonCode, ['master' => 'country_reason_codes', 'operation' => 'deactivate']);

        return back()->with('status', 'Country reason code deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CountryReasonCode $existing = null): array
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:120'],
            'reason_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('country_reason_codes')
                    ->where(fn ($q) => $q->where('country_code', strtoupper(trim((string) $request->input('country_code')))))
                    ->ignore($existing),
            ],
            'description' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['country_code'] = strtoupper(trim($validated['country_code']));
        $validated['reason_code'] = strtoupper($validated['reason_code']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
