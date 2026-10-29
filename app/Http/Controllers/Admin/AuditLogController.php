<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * FR14: read-only browser over the audit trail. Sensitive actions are recorded
 * elsewhere; nothing here mutates the log — the trail is append-only and the
 * only staff who can see it is the system administrator (enforced by the
 * role:admin route group).
 */
final class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $actionFilter = (string) $request->input('action');
        $userFilter = $request->integer('user') ?: null;

        $logs = AuditLog::query()
            ->with('user')
            ->when($actionFilter !== '', fn ($q) => $q->where('action', $actionFilter))
            ->when($userFilter, fn ($q) => $q->where('user_id', $userFilter))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->input('q').'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('description', 'like', $like)
                        ->orWhere('ip_address', 'like', $like);
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->orderBy('action')->distinct()->pluck('action'),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
