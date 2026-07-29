<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('audit-logs.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')->toString()))
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
                ->latest('created_at')
                ->paginate(40)
                ->withQueryString(),
            'users' => User::query()->orderBy('name')->get(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->all(),
        ]);
    }
}
