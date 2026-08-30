<?php

namespace App\Modules\Scheduling\Notifications;

use App\Modules\Scheduling\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

final class AppointmentRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
        string $cancelToken,
        string $rescheduleToken
    ) { $this->cancelToken = Crypt::encryptString($cancelToken); $this->rescheduleToken = Crypt::encryptString($rescheduleToken); }

    private string $cancelToken;
    private string $rescheduleToken;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->loadMissing('eventType');
        $at = $appointment->scheduled_start_at->setTimezone($appointment->timezone);

        return (new MailMessage)
            ->subject('Agendamento reagendado — '.$appointment->eventType->name)
            ->greeting('Olá, '.$appointment->guest_name.'!')
            ->line('Seu novo horário foi confirmado.')
            ->line($at->format('d/m/Y H:i').' ('.$appointment->timezone.')')
            ->action('Ver agendamento', route('scheduling.public.confirmation', $appointment))
            ->line('Cancelar: '.route('scheduling.public.cancel.show', [$appointment, 'token' => Crypt::decryptString($this->cancelToken)]))
            ->line('Reagendar: '.route('scheduling.public.reschedule.show', [$appointment, 'token' => Crypt::decryptString($this->rescheduleToken)]));
    }
}
