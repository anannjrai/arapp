<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function record(string $action, ?Model $auditable = null, array $metadata = [], ?Authenticatable $user = null): AuditLog
    {
        $request = request();
        $actor = $user ?? Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
