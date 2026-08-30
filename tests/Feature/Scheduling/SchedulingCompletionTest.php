<?php

namespace Tests\Feature\Scheduling;

use App\Models\{AuditLog, Client, Notification as InternalNotification, User};
use App\Modules\Scheduling\Actions\{CancelAppointment, CreateAppointment, RescheduleAppointment};
use App\Modules\Scheduling\Jobs\SendAppointmentReminders;
use App\Modules\Scheduling\Models\{Appointment, AvailabilityException, AvailabilityRule, EventType};
use App\Modules\Scheduling\Notifications\{AppointmentCancelledNotification, AppointmentConfirmedNotification, AppointmentReminderNotification, AppointmentRescheduledNotification};
use App\Modules\Scheduling\Services\SlotGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchedulingCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true, 'scheduling.default_timezone' => 'America/Sao_Paulo']);
        CarbonImmutable::setTestNow('2026-09-06 12:00:00 UTC');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_buffers_block_conflicts_but_keep_exact_boundary(): void
    {
        $type = $this->type(['buffer_before_minutes' => 15, 'buffer_after_minutes' => 15]);
        $type->rules()->update(['end_time' => '12:30']);
        $this->appointment($type, '2026-09-07 13:00:00', '2026-09-07 14:00:00');
        $labels = array_column(app(SlotGenerator::class)->generate($type, '2026-09-07', 'America/Sao_Paulo'), 'label');
        $this->assertNotContains('09:00', $labels);
        $this->assertNotContains('11:00', $labels);
        $this->assertContains('11:30', $labels);
    }

    public function test_minimum_notice_and_horizon_use_exact_boundaries(): void
    {
        $type = $this->type(['minimum_notice_minutes' => 60, 'maximum_days_ahead' => 1]);
        CarbonImmutable::setTestNow('2026-09-07 11:59:00 UTC');
        $labels = array_column(app(SlotGenerator::class)->generate($type, '2026-09-07', 'America/Sao_Paulo'), 'label');
        $this->assertContains('10:00', $labels);
        $this->assertNotContains('09:45', $labels);
        $this->assertSame([], app(SlotGenerator::class)->generate($type, '2026-09-09', 'America/Sao_Paulo'));
    }

    public function test_weekends_are_never_available_even_with_override(): void
    {
        $type = $this->type([], false);
        AvailabilityException::create(['event_type_id' => $type->id, 'date' => '2026-09-12', 'start_time' => '09:00', 'end_time' => '12:00', 'type' => 'available_override']);

        $this->assertSame([], app(SlotGenerator::class)->generate($type, '2026-09-12', 'America/Sao_Paulo'));
        $this->getJson(route('scheduling.public.calendar', [$type, 'month' => '2026-09', 'timezone' => 'America/Sao_Paulo']))
            ->assertOk()
            ->assertJsonPath('days.2026-09-12', 'unavailable');
    }

    public function test_exceptions_block_full_and_partial_days_and_override_rules(): void
    {
        $type = $this->type();
        AvailabilityException::create(['event_type_id' => $type->id, 'date' => '2026-09-07', 'start_time' => '10:00', 'end_time' => '11:00', 'type' => 'unavailable']);
        $labels = array_column(app(SlotGenerator::class)->generate($type, '2026-09-07', 'America/Sao_Paulo'), 'label');
        $this->assertNotContains('10:00', $labels);
        AvailabilityException::create(['event_type_id' => $type->id, 'date' => '2026-09-07', 'type' => 'unavailable']);
        $this->assertSame([], app(SlotGenerator::class)->generate($type, '2026-09-07', 'America/Sao_Paulo'));
        AvailabilityException::query()->delete();
        AvailabilityException::create(['event_type_id' => $type->id, 'date' => '2026-09-08', 'start_time' => '14:00', 'end_time' => '15:00', 'type' => 'available_override']);
        $this->assertSame(['14:00'], array_column(app(SlotGenerator::class)->generate($type, '2026-09-08', 'America/Sao_Paulo'), 'label'));
    }

    public function test_weekend_dst_dates_are_closed_and_utc_conversion_changes_day(): void
    {
        $type = $this->type([], false);
        AvailabilityRule::create(['event_type_id' => $type->id, 'day_of_week' => 7, 'start_time' => '02:00', 'end_time' => '04:00', 'timezone' => 'America/New_York', 'active' => true]);
        CarbonImmutable::setTestNow('2027-03-01 00:00:00 UTC');
        $labels = array_column(app(SlotGenerator::class)->generate($type, '2027-03-14', 'America/New_York'), 'label');
        $this->assertSame([], $labels);

        $fall = $this->type(['slug' => 'fall-back', 'duration_minutes' => 30, 'slot_interval_minutes' => 30], false);
        AvailabilityRule::create(['event_type_id' => $fall->id, 'day_of_week' => 7, 'start_time' => '01:00', 'end_time' => '03:00', 'timezone' => 'America/New_York', 'active' => true]);
        CarbonImmutable::setTestNow('2027-11-01 00:00:00 UTC');
        $this->assertSame([], app(SlotGenerator::class)->generate($fall, '2027-11-07', 'America/New_York'));

        $type2 = $this->type(['slug' => 'utc-day', 'duration_minutes' => 15], false);
        AvailabilityRule::create(['event_type_id' => $type2->id, 'day_of_week' => 1, 'start_time' => '23:30', 'end_time' => '23:59', 'timezone' => 'America/Sao_Paulo', 'active' => true]);
        CarbonImmutable::setTestNow('2026-09-06 00:00:00 UTC');
        $slot = app(SlotGenerator::class)->generate($type2, '2026-09-07', 'UTC')[0];
        $this->assertSame('2026-09-08', CarbonImmutable::parse($slot['start'])->utc()->format('Y-m-d'));
    }

    public function test_public_reschedule_rotates_tokens_audits_notifies_and_clears_old_reminders(): void
    {
        User::factory()->create(['role' => User::ROLE_OPERATOR, 'active' => true]);
        $type = $this->type(); $oldToken = str_repeat('b', 64); $appointment = $this->appointment($type);
        DB::table('scheduling_reminder_deliveries')->insert(['appointment_id' => $appointment->id, 'minutes_before' => 1440, 'sent_at' => now()]);
        $result = app(RescheduleAppointment::class)->execute($appointment, $oldToken, '2026-09-07T11:00:00-03:00', 'America/Sao_Paulo');
        $appointment->refresh();
        $this->assertSame('11:00', $appointment->scheduled_start_at->setTimezone('America/Sao_Paulo')->format('H:i'));
        $this->assertFalse(hash_equals($appointment->reschedule_token_hash, hash('sha256', $oldToken)));
        $this->assertSame(0, DB::table('scheduling_reminder_deliveries')->where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.rescheduled', 'resource_id' => (string) $appointment->id]);
        $this->assertDatabaseHas('notifications', ['unique_key' => 'scheduling:rescheduled:'.$appointment->events()->latest('id')->value('id')]);
        Notification::assertSentOnDemand(AppointmentRescheduledNotification::class);
        $this->assertSame(64, strlen($result['cancel']));
    }

    public function test_cancel_is_idempotent_and_sends_one_internal_and_email_notification(): void
    {
        User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $appointment = $this->appointment($this->type());
        app(CancelAppointment::class)->execute($appointment, str_repeat('a', 64));
        app(CancelAppointment::class)->execute($appointment, str_repeat('a', 64));
        $this->assertSame(1, $appointment->events()->where('event_type', 'cancelled')->count());
        $this->assertSame(1, InternalNotification::where('type', 'scheduling')->count());
        Notification::assertSentOnDemandTimes(AppointmentCancelledNotification::class, 1);
    }

    public function test_admin_create_revalidates_slot_and_associates_client(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true, 'must_change_password' => false]);
        $client = Client::factory()->create(['active' => true]); $type = $this->type();
        $payload = ['event_type_id' => $type->id, 'start' => '2026-09-07T09:00:00-03:00', 'timezone' => 'America/Sao_Paulo', 'guest_name' => 'Ada', 'guest_email' => 'ada@example.test', 'client_id' => $client->id];
        $this->actingAs($admin)->post(route('scheduling.admin.appointments.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('scheduling_appointments', ['client_id' => $client->id, 'created_by' => $admin->id]);
        Notification::assertSentOnDemand(AppointmentConfirmedNotification::class);
        $this->actingAs($admin)->post(route('scheduling.admin.appointments.store'), $payload)->assertSessionHasErrors('start');
        $this->assertSame(1, Appointment::count());
    }

    public function test_exception_delete_enforces_parent_scope_and_audits(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true, 'must_change_password' => false]);
        $type = $this->type(); $other = $this->type(['slug' => 'other']);
        $exception = AvailabilityException::create(['event_type_id' => $type->id, 'date' => '2026-09-07', 'type' => 'unavailable']);
        $this->actingAs($admin)->delete(route('scheduling.admin.exceptions.delete', [$other, $exception]))->assertNotFound();
        $this->actingAs($admin)->delete(route('scheduling.admin.exceptions.delete', [$type, $exception]))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'availability.exception.deleted']);
    }

    public function test_invalid_public_tokens_are_rejected_before_rendering(): void
    {
        $appointment = $this->appointment($this->type());
        $this->get(route('scheduling.public.cancel.show', [$appointment, 'token' => str_repeat('x', 64)]))->assertSessionHasErrors('token');
        $this->get(route('scheduling.public.reschedule.show', [$appointment, 'token' => str_repeat('x', 64)]))->assertSessionHasErrors('token');
    }

    public function test_reminders_are_idempotent_and_skip_cancelled_past_or_rescheduled_times(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 12:00:00 UTC');
        $type = $this->type();
        $due = $this->appointment($type, '2026-09-08 12:00:00', '2026-09-08 13:00:00');
        $cancelled = $this->appointment($type, '2026-09-08 12:00:30', '2026-09-08 13:00:30', 'cancelled');
        $past = $this->appointment($type, '2026-09-06 12:00:00', '2026-09-06 13:00:00');
        app(SendAppointmentReminders::class)->handle(); app(SendAppointmentReminders::class)->handle();
        $this->assertDatabaseHas('scheduling_reminder_deliveries', ['appointment_id' => $due->id, 'minutes_before' => 1440]);
        $this->assertDatabaseMissing('scheduling_reminder_deliveries', ['appointment_id' => $cancelled->id]);
        $this->assertDatabaseMissing('scheduling_reminder_deliveries', ['appointment_id' => $past->id]);
        Notification::assertSentOnDemandTimes(AppointmentReminderNotification::class, 1);
    }

    public function test_public_calendar_states_come_from_backend(): void
    {
        $type = $this->type();
        $response = $this->getJson(route('scheduling.public.calendar', [$type, 'month' => '2026-09', 'timezone' => 'America/Sao_Paulo']))->assertOk();
        $response->assertJsonPath('days.2026-09-07', 'available')->assertJsonPath('days.2026-09-08', 'unavailable');
    }

    public function test_reschedule_calendar_requires_token_and_ignores_only_current_appointment(): void
    {
        $type = $this->type();
        $type->rules()->update(['end_time' => '10:00']);
        $appointment = $this->appointment($type, '2026-09-07 12:00:00', '2026-09-07 13:00:00');
        $url = route('scheduling.public.reschedule.calendar', [$appointment, 'month' => '2026-09', 'timezone' => 'America/Sao_Paulo']);

        $this->get($url.'&token='.str_repeat('x', 64))->assertSessionHasErrors('token');
        $this->getJson($url.'&token='.str_repeat('b', 64))->assertOk()->assertJsonPath('days.2026-09-07', 'available');

        $this->appointment($type, '2026-09-07 12:00:00', '2026-09-07 13:00:00');
        $this->getJson($url.'&token='.str_repeat('b', 64))->assertOk()->assertJsonPath('days.2026-09-07', 'unavailable');
    }

    public function test_queued_reminder_invalidates_itself_after_reschedule_or_cancel(): void
    {
        $appointment = $this->appointment($this->type());
        $expected = $appointment->scheduled_start_at->utc()->toIso8601String();
        $reminder = new AppointmentReminderNotification($appointment, 60, $expected);
        $this->assertSame(['mail'], $reminder->via(new \stdClass()));

        $appointment->update(['scheduled_start_at' => $appointment->scheduled_start_at->addHour(), 'scheduled_end_at' => $appointment->scheduled_end_at->addHour()]);
        $this->assertSame([], $reminder->via(new \stdClass()));

        $appointment->update(['scheduled_start_at' => CarbonImmutable::parse($expected), 'status' => 'cancelled']);
        $this->assertSame([], $reminder->via(new \stdClass()));
    }

    private function type(array $overrides = [], bool $rule = true): EventType
    {
        $type = EventType::create(array_merge(['name' => 'Consultoria', 'slug' => 'consultoria-'.bin2hex(random_bytes(3)), 'duration_minutes' => 60, 'slot_interval_minutes' => 15, 'location_type' => 'online', 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'minimum_notice_minutes' => 0, 'maximum_days_ahead' => 730, 'active' => true], $overrides));
        if ($rule) AvailabilityRule::create(['event_type_id' => $type->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'timezone' => 'America/Sao_Paulo', 'active' => true]);
        return $type;
    }

    private function appointment(EventType $type, string $start = '2026-09-07 12:00:00', string $end = '2026-09-07 13:00:00', string $status = 'confirmed'): Appointment
    {
        return Appointment::create(['event_type_id' => $type->id, 'scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'timezone' => 'America/Sao_Paulo', 'status' => $status, 'guest_name' => 'Ada', 'guest_email' => 'ada@example.test', 'cancellation_token_hash' => hash('sha256', str_repeat('a', 64)), 'reschedule_token_hash' => hash('sha256', str_repeat('b', 64))]);
    }
}
