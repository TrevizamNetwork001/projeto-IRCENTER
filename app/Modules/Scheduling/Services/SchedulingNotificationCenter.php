<?php

namespace App\Modules\Scheduling\Services;

use App\Models\Notification;
use App\Models\User;
use App\Modules\Scheduling\Models\Appointment;

final class SchedulingNotificationCenter
{
    public function publish(Appointment $appointment, string $event, int $eventId): void
    {
        $appointment->loadMissing(['eventType', 'client']);
        $at = $appointment->scheduled_start_at->setTimezone($appointment->timezone);
        $label = match ($event) {
            'cancelled' => 'Agendamento cancelado',
            'rescheduled' => 'Agendamento reagendado',
            default => 'Novo agendamento',
        };
        $client = $appointment->client?->displayName();
        $message = sprintf(
            '%s — %s, %s (%s)%s.',
            $appointment->guest_name,
            $appointment->eventType->name,
            $at->format('d/m/Y H:i'),
            $appointment->timezone,
            $client ? ' — cliente '.$client : ''
        );

        User::query()->where('active', true)->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])
            ->each(function (User $user) use ($appointment, $event, $eventId, $label, $message): void {
                Notification::query()->updateOrCreate(
                    ['user_id' => $user->id, 'unique_key' => "scheduling:{$event}:{$eventId}"],
                    [
                        'type' => 'scheduling',
                        'priority' => $event === 'cancelled' ? Notification::PRIORITY_WARNING : Notification::PRIORITY_INFO,
                        'title' => $label,
                        'message' => $message,
                        'action_url' => route('scheduling.admin.appointments.show', $appointment),
                        'resolved_at' => null,
                    ]
                );
            });
    }
}
