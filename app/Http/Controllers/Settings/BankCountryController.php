<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BankCountry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BankCountryController extends Controller
{
    public function index(Request $request): View
    {
        return view('settings.bank-countries', [
            'countries' => BankCountry::query()
                ->when($request->filled('country_name'), fn ($q) => $q->where('country_name', 'like', '%'.$request->string('country_name')->toString().'%'))
                ->when($request->filled('capital'), fn ($q) => $q->where('capital', 'like', '%'.$request->string('capital')->toString().'%'))
                ->orderBy('country_name')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $request->all(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $country = BankCountry::updateOrCreate(['country_key' => $validated['country_key']], $validated);

        $auditLogger->record('master_update', $country, ['master' => 'bank_countries', 'operation' => 'save']);

        return back()->with('status', 'Bank country saved.');
    }

    public function update(Request $request, BankCountry $bankCountry, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request, $bankCountry);
        $bankCountry->update($validated);

        $auditLogger->record('master_update', $bankCountry, ['master' => 'bank_countries', 'operation' => 'update']);

        return back()->with('status', 'Bank country updated.');
    }

    public function destroy(BankCountry $bankCountry, AuditLogger $auditLogger): RedirectResponse
    {
        $bankCountry->update(['is_active' => false]);
        $auditLogger->record('master_update', $bankCountry, ['master' => 'bank_countries', 'operation' => 'deactivate']);

        return back()->with('status', 'Bank country deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BankCountry $existing = null): array
    {
        $validated = $request->validate([
            'country_name' => ['required', 'string', 'max:120'],
            'capital' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['country_name'] = trim($validated['country_name']);
        $validated['country_key'] = BankCountry::normalizeName($validated['country_name']);
        $validated['capital'] = trim($validated['capital']);
        $validated['is_active'] = $request->boolean('is_active');

        $duplicate = BankCountry::query()
            ->where('country_key', $validated['country_key'])
            ->when($existing !== null, fn ($q) => $q->whereKeyNot($existing->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'country_name' => 'This country is already maintained in the bank country master.',
            ]);
        }

        return $validated;
    }
}
