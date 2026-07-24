<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        NotificationService $notificationService
    ): View {
        $user = $request->user();

        $notificationService->syncFor($user);

        $status = $request->string('status')->toString();

        if (! in_array($status, ['all', 'unread'], true)) {
            $status = 'all';
        }

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'unreadTotal' => Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('resolved_at')
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(
        Request $request,
        Notification $notification,
        AuditService $auditService
    ): RedirectResponse {
        abort_unless(
            $notification->user_id === $request->user()->id,
            404
        );

        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
            ]);

            $auditService->record(
                'read',
                $notification,
                null,
                ['read_at' => $notification->read_at?->toIso8601String()],
                $notification->title,
                $request
            );
        }

        return back()->with(
            'success',
            'Notificação marcada como lida.'
        );
    }

    public function markAllRead(
        Request $request,
        AuditService $auditService
    ): RedirectResponse {
        $user = $request->user();

        $updated = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $auditService->record(
                'notifications_read_all',
                $user,
                null,
                ['notifications_marked' => $updated],
                $user->name,
                $request
            );
        }

        return back()->with(
            'success',
            $updated > 0
                ? 'Todas as notificações foram marcadas como lidas.'
                : 'Não há notificações não lidas.'
        );
    }
}
