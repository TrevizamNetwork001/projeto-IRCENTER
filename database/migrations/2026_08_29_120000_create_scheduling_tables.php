<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduling_event_types', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->string('name');
            $table->string('slug')->unique(); $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes'); $table->unsignedSmallInteger('slot_interval_minutes')->default(15);
            $table->string('location_type', 20)->default('online'); $table->string('location_value')->nullable();
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0); $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('minimum_notice_minutes')->default(60); $table->unsignedSmallInteger('maximum_days_ahead')->default(90);
            $table->string('accent', 20)->nullable(); $table->boolean('active')->default(true)->index();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('scheduling_availability_rules', function (Blueprint $table): void {
            $table->id(); $table->foreignId('event_type_id')->constrained('scheduling_event_types')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week'); $table->time('start_time'); $table->time('end_time');
            $table->string('timezone', 64); $table->boolean('active')->default(true); $table->timestamps();
            $table->index(['event_type_id', 'day_of_week', 'active'], 'sched_rules_lookup_idx');
        });
        Schema::create('scheduling_availability_exceptions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('event_type_id')->constrained('scheduling_event_types')->restrictOnDelete();
            $table->date('date'); $table->time('start_time')->nullable(); $table->time('end_time')->nullable();
            $table->string('type', 24); $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['event_type_id', 'date'], 'sched_exceptions_lookup_idx');
        });
        Schema::create('scheduling_appointments', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique();
            $table->foreignId('event_type_id')->constrained('scheduling_event_types')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestampTz('scheduled_start_at'); $table->timestampTz('scheduled_end_at'); $table->string('timezone', 64);
            $table->string('status', 20)->default('scheduled'); $table->string('guest_name'); $table->string('guest_email');
            $table->string('guest_phone', 40)->nullable(); $table->string('guest_company')->nullable(); $table->text('notes')->nullable();
            $table->string('cancellation_token_hash', 64); $table->string('reschedule_token_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable(); $table->timestampTz('completed_at')->nullable(); $table->timestamps();
            $table->index(['event_type_id', 'scheduled_start_at', 'scheduled_end_at'], 'sched_appointments_overlap_idx');
            $table->index(['status', 'scheduled_start_at'], 'sched_appointments_status_idx'); $table->index('client_id');
        });
        Schema::create('scheduling_appointment_attendees', function (Blueprint $table): void {
            $table->id(); $table->foreignId('appointment_id')->constrained('scheduling_appointments')->restrictOnDelete();
            $table->string('name'); $table->string('email'); $table->timestamps();
        });
        Schema::create('scheduling_appointment_events', function (Blueprint $table): void {
            $table->id(); $table->foreignId('appointment_id')->constrained('scheduling_appointments')->restrictOnDelete();
            $table->string('event_type', 30); $table->json('metadata')->nullable(); $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['appointment_id', 'created_at'], 'sched_events_timeline_idx');
        });
        Schema::create('scheduling_reminder_deliveries', function (Blueprint $table): void {
            $table->id(); $table->foreignId('appointment_id')->constrained('scheduling_appointments')->restrictOnDelete();
            $table->unsignedSmallInteger('minutes_before'); $table->timestampTz('sent_at');
            $table->unique(['appointment_id', 'minutes_before'], 'sched_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_reminder_deliveries'); Schema::dropIfExists('scheduling_appointment_events');
        Schema::dropIfExists('scheduling_appointment_attendees'); Schema::dropIfExists('scheduling_appointments');
        Schema::dropIfExists('scheduling_availability_exceptions'); Schema::dropIfExists('scheduling_availability_rules');
        Schema::dropIfExists('scheduling_event_types');
    }
};
