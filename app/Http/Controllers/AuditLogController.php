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
        $this->authorizeAdministrator();

        $search = trim((string) $request->query('search', ''));
        $action = trim((string) $request->query('action', ''));
        $resourceType = trim(
            (string) $request->query('resource_type', '')
        );
        $userId = $request->integer('user');

        $logs = AuditLog::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where(
                            'resource_label',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'resource_type',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'action',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'ip_address',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'user',
                            fn ($query) => $query
                                ->where('name', 'ilike', "%{$search}%")
                                ->orWhere(
                                    'email',
                                    'ilike',
                                    "%{$search}%"
                                )
                        );
                });
            })
            ->when(
                $action !== '',
                fn ($query) => $query->where('action', $action)
            )
            ->when(
                $resourceType !== '',
                fn ($query) => $query->where(
                    'resource_type',
                    $resourceType
                )
            )
            ->when(
                $userId > 0,
                fn ($query) => $query->where('user_id', $userId)
            )
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'search' => $search,
            'action' => $action,
            'resourceType' => $resourceType,
            'userId' => $userId,
            'users' => User::query()
                ->orderBy('name')
                ->get(),
            'actions' => AuditLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'resourceTypes' => AuditLog::query()
                ->distinct()
                ->orderBy('resource_type')
                ->pluck('resource_type'),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorizeAdministrator();

        $auditLog->load('user');

        return view('audit.show', [
            'auditLog' => $auditLog,
        ]);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
