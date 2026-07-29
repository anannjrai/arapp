<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => User::roles(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $user = User::create($validated);

        $auditLogger->record('user_create', $user, [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return back()->with('status', 'User created.');
    }

    public function update(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validated($request, $user);

        if (! $this->adminWouldRemain($user, $validated)) {
            return back()->withErrors(['user' => 'At least one active administrator must remain.']);
        }

        $user->update($validated);

        $auditLogger->record('user_update', $user, [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);

        return back()->with('status', 'User updated.');
    }

    public function destroy(User $user, AuditLogger $auditLogger): RedirectResponse
    {
        if ($user->is(Auth::user())) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        if (! $this->adminWouldRemain($user, ['role' => $user->role, 'is_active' => false])) {
            return back()->withErrors(['user' => 'At least one active administrator must remain.']);
        }

        $user->update(['is_active' => false]);

        $auditLogger->record('user_deactivate', $user, [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return back()->with('status', 'User deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $existing = null): array
    {
        $request->merge([
            'username' => Str::lower((string) $request->input('username')),
            'email' => Str::lower((string) $request->input('email')),
        ]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($existing)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing)],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($existing) {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        } else {
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        $validated = $request->validate($rules);

        $validated['is_active'] = $request->boolean('is_active');

        if ($existing && blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $newValues
     */
    private function adminWouldRemain(User $user, array $newValues): bool
    {
        $willStillBeActiveAdmin = ($newValues['role'] ?? $user->role) === User::ROLE_ADMIN
            && (bool) ($newValues['is_active'] ?? $user->is_active);

        if (! $user->isAdmin() || $willStillBeActiveAdmin) {
            return true;
        }

        return User::query()
            ->where('id', '!=', $user->id)
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->exists();
    }
}
