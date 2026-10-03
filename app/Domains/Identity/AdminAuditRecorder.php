<?php

namespace App\Domains\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The one way an administrative action gets recorded.
 *
 * Maveren's equivalent, logAdminAction(), existed and was never called from a
 * single site; its table was never created. Keeping the write behind one
 * service means there is one place to get the actor, IP and session right, and
 * one place to look when asking whether something is recorded at all.
 */
class AdminAuditRecorder
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record an administrative action.
     *
     * @param  array<string, mixed>|null  $before  domain state before the write
     * @param  array<string, mixed>|null  $after  domain state after the write
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): AdminAuditEvent {
        return AdminAuditEvent::create([
            'admin_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            // A subject that has not been persisted has no id to point at;
            // recording null beats recording a key that resolves to nothing.
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => $this->request->ip(),
            // A console-dispatched action has no session. Audit rows from the
            // scheduler are still worth having, so this is nullable rather
            // than a reason to skip the write.
            'session_id' => $this->request->hasSession()
                ? $this->request->session()->getId()
                : null,
        ]);
    }
}
