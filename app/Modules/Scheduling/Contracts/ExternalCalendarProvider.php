<?php
namespace App\Modules\Scheduling\Contracts;
use App\Modules\Scheduling\Models\Appointment;
interface ExternalCalendarProvider { public function busyPeriods(\DateTimeImmutable $from, \DateTimeImmutable $to): array; public function publish(Appointment $appointment): void; }
