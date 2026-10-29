<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * FR14: single entry point for the audit trail of sensitive actions. Call sites
 * pass a stable action key (e.g. "rental.discount_approved"), a human-readable
 * description, and optionally the record acted upon plus a small property bag
 * (before/after amounts, reason, ...). The acting user, client IP and user
 * agent are captured from the current request; under a console command the user
 * is null and the request context is simply omitted.
 */
class AuditLogger
{
    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $actor = null): AuditLog
    {
        $request = request();
        $actor ??= auth()->user();

        return AuditLog::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) $request->userAgent() : null,
        ]);
    }
}
