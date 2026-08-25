<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionRevoker
{
    public function revokeOthers(User $user, string $currentSessionId): int
    {
        $count = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        $this->audit($user, 'identity.sessions.others_revoked', $count);

        return $count;
    }

    public function revokeForAdministrator(User $actor, User $target): int
    {
        if (! $actor->isAdministrator()) {
            abort(403);
        }

        $count = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $target->id)
            ->delete();

        $this->audit($target, 'identity.sessions.admin_revoked', $count, $actor->id);

        return $count;
    }

    private function audit(User $target, string $action, int $count, ?int $actorId = null): void
    {
        AuditLog::create([
            'user_id' => $actorId ?? $target->id,
            'action' => $action,
            'resource_type' => 'User',
            'resource_id' => $target->id,
            'resource_label' => 'User #'.$target->id,
            'old_values' => null,
            'new_values' => ['revoked_sessions' => $count],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
