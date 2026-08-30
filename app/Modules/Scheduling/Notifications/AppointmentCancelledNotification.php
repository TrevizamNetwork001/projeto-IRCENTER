<?php

namespace App\Modules\Scheduling\Notifications;

use App\Modules\Scheduling\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AppointmentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->loadMissing('eventType');
        $at = $appointment->scheduled_start_at->setTimezone($appointment->timezone);

        return (new MailMessage)
            ->subject('Agendamento cancelado — '.$appointment->eventType->name)
            ->greeting('Olá, '.$appointment->guest_name.'!')
            ->line('Seu agendamento foi cancelado.')
            ->line($at->format('d/m/Y H:i').' ('.$appointment->timezone.')');
    }
}
