<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuthenticationAudit
{
    public function record(User $user, string $action, Request $request): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'resource_label' => 'User #'.$user->id,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
