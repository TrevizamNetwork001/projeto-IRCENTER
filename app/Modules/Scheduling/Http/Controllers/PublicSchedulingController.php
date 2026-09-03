<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Actions\{CancelAppointment, CreateAppointment, RescheduleAppointment};
use App\Modules\Scheduling\Models\{Appointment, EventType};
use App\Modules\Scheduling\Services\{IcsGenerator, SlotGenerator};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PublicSchedulingController extends Controller
{
    private function enabled(): void { abort_unless(config('scheduling.enabled'), 404); }

    public function show(EventType $eventType, Request $request): View
    {
        $this->enabled(); abort_unless($eventType->active, 404);
        return view('scheduling.public.book', ['eventType' => $eventType, 'timezone' => $request->query('timezone', config('scheduling.default_timezone'))]);
    }

    public function availability(EventType $eventType, Request $request, SlotGenerator $slots): JsonResponse
    {
        $this->enabled(); abort_unless($eventType->active, 404);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'timezone' => ['required', 'timezone']]);
        return response()->json(['date' => $data['date'], 'timezone' => $data['timezone'], 'slots' => $slots->generate($eventType, $data['date'], $data['timezone'])]);
    }

    public function calendar(EventType $eventType, Request $request, SlotGenerator $slots): JsonResponse
    {
        $this->enabled(); abort_unless($eventType->active, 404);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'timezone' => ['required', 'timezone']]);
        return $this->calendarResponse($eventType, $data['month'], $data['timezone'], $slots);
    }

    public function rescheduleCalendar(Appointment $appointment, Request $request, SlotGenerator $slots): JsonResponse
    {
        $this->enabled();
        $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'month' => ['required', 'date_format:Y-m'], 'timezone' => ['required', 'timezone']]);
        $this->validToken($appointment, $data['token'], 'reschedule_token_hash');
        abort_unless(in_array($appointment->status, ['scheduled', 'confirmed'], true), 409);
        return $this->calendarResponse($appointment->eventType, $data['month'], $data['timezone'], $slots, $appointment->id);
    }

    private function calendarResponse(EventType $eventType, string $month, string $timezone, SlotGenerator $slots, ?int $ignoreAppointmentId = null): JsonResponse
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $timezone)->startOfMonth();
        $today = now($timezone)->startOfDay();
        $notice = now()->addMinutes($eventType->minimum_notice_minutes)->setTimezone($timezone);
        $horizon = now()->setTimezone($timezone)->addDays(min($eventType->maximum_days_ahead, (int) config('scheduling.max_booking_horizon')))->endOfDay();
        $days = [];
        for ($date = $start; $date->isSameMonth($start); $date = $date->addDay()) {
            $status = 'unavailable';
            if ($date->endOfDay()->lt($today) || $date->startOfDay()->gt($horizon)) $status = 'outside_horizon';
            elseif ($date->endOfDay()->lt($notice)) $status = 'minimum_notice';
            elseif ($slots->generate($eventType, $date->format('Y-m-d'), $timezone, null, $ignoreAppointmentId) !== []) $status = 'available';
            $days[$date->format('Y-m-d')] = $status;
        }
        return response()->json(['month' => $month, 'days' => $days]);
    }

    public function store(EventType $eventType, Request $request, CreateAppointment $create): RedirectResponse
    {
        $this->enabled(); abort_unless($eventType->active, 404);
        $data = $request->validate([
            'start' => ['required', 'date'], 'timezone' => ['required', 'timezone'],
            'guest_name' => ['required', 'string', 'max:120'], 'guest_email' => ['required', 'email:rfc', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:40'], 'guest_company' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'], 'website' => ['nullable', 'size:0'], 'form_started_at' => ['required', 'integer'],
        ]);
        if (time() - (int) $data['form_started_at'] < 2) return back()->withErrors(['guest_name' => 'Envio rápido demais. Tente novamente.'])->withInput();
        $result = $create->execute($eventType, $data);
        return redirect()->route('scheduling.public.confirmation', ['appointment' => $result['appointment'], 'token' => $result['cancel']])
            ->with('booking_tokens', ['cancel' => $result['cancel'], 'reschedule' => $result['reschedule']]);
    }

    public function confirmation(Appointment $appointment, Request $request): View
    {
        $this->enabled();
        $token = (string) $request->query('token');
        abort_unless($this->ownsAppointment($appointment, $token), 404);
        $tokens = session('booking_tokens');
        return view('scheduling.public.confirmation', [
            'appointment' => $appointment->load('eventType'),
            'token' => $token,
            'cancelUrl' => isset($tokens['cancel']) ? route('scheduling.public.cancel.show', [$appointment, 'token' => $tokens['cancel']]) : null,
            'rescheduleUrl' => isset($tokens['reschedule']) ? route('scheduling.public.reschedule.show', [$appointment, 'token' => $tokens['reschedule']]) : null,
        ]);
    }

    public function cancelShow(Appointment $appointment, Request $request): View
    {
        $this->enabled(); $this->validToken($appointment, (string) $request->query('token'), 'cancellation_token_hash');
        return view('scheduling.public.cancel', compact('appointment'));
    }

    public function cancel(Appointment $appointment, Request $request, CancelAppointment $cancel): RedirectResponse
    {
        $this->enabled(); $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $cancel->execute($appointment, $data['token']);
        return redirect()->route('scheduling.public.confirmation', ['appointment' => $appointment, 'token' => $data['token']])->with('status', 'Agendamento cancelado.');
    }

    public function rescheduleShow(Appointment $appointment, Request $request): View
    {
        $this->enabled(); $token = (string) $request->query('token');
        $this->validToken($appointment, $token, 'reschedule_token_hash');
        abort_unless(in_array($appointment->status, ['scheduled', 'confirmed'], true), 409);
        return view('scheduling.public.reschedule', ['appointment' => $appointment->load('eventType'), 'token' => $token, 'timezone' => $appointment->timezone]);
    }

    public function rescheduleAvailability(Appointment $appointment, Request $request, SlotGenerator $slots): JsonResponse
    {
        $this->enabled();
        $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'date' => ['required', 'date_format:Y-m-d'], 'timezone' => ['required', 'timezone']]);
        $this->validToken($appointment, $data['token'], 'reschedule_token_hash');
        return response()->json(['slots' => $slots->generate($appointment->eventType, $data['date'], $data['timezone'], null, $appointment->id)]);
    }

    public function reschedule(Appointment $appointment, Request $request, RescheduleAppointment $action): RedirectResponse
    {
        $this->enabled(); $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'start' => ['required', 'date'], 'timezone' => ['required', 'timezone']]);
        $result = $action->execute($appointment, $data['token'], $data['start'], $data['timezone']);
        return redirect()->route('scheduling.public.confirmation', ['appointment' => $result['appointment'], 'token' => $result['cancel']])
            ->with('status', 'Agendamento reagendado.')
            ->with('booking_tokens', ['cancel' => $result['cancel'], 'reschedule' => $result['reschedule']]);
    }

    public function ics(Appointment $appointment, Request $request, IcsGenerator $ics): Response
    {
        $this->enabled();
        abort_unless($this->ownsAppointment($appointment, (string) $request->query('token')), 404);
        return response($ics->generate($appointment), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="agendamento.ics"']);
    }

    private function validToken(Appointment $appointment, string $token, string $hashField): void
    {
        if (strlen($token) !== 64 || ! hash_equals($appointment->{$hashField}, hash('sha256', $token))) throw ValidationException::withMessages(['token' => 'Link inválido ou expirado.']);
    }

    /**
     * Confirmação e .ics não têm uma ação sensível própria (cancelar/reagendar já
     * exigem o hash correspondente): aceitar qualquer um dos dois tokens emitidos
     * no momento da criação é suficiente para provar posse do agendamento e evita
     * uma migration só para uma terceira coluna de hash dedicada a "visualização".
     */
    private function ownsAppointment(Appointment $appointment, string $token): bool
    {
        if (strlen($token) !== 64) return false;
        $hashed = hash('sha256', $token);
        return hash_equals($appointment->cancellation_token_hash, $hashed)
            || hash_equals($appointment->reschedule_token_hash, $hashed);
    }
}
