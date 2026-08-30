<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\Appointment;
use Illuminate\View\View;

final class AppointmentController extends Controller
{
    public function index(): View
    {
        $appointments = Appointment::query()
            ->with('eventType')
            ->orderByDesc('scheduled_start_at')
            ->paginate(20);

        return view('portal.appointments.index', [
            'appointments' => $appointments,
        ]);
    }
}
