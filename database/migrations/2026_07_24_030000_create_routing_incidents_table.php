<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'routing_incidents',
            function (Blueprint $table): void {
                $table->id();

                $table->string('reference', 30)->unique();

                $table->foreignId('client_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId('autonomous_system_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId('prefix_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId('reported_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId('assigned_to_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->string('title');
                $table->string('type', 50);
                $table->string('severity', 20);
                $table->string('status', 30);
                $table->string('source', 100)->nullable();

                $table->text('summary');
                $table->text('impact')->nullable();
                $table->text('evidence')->nullable();
                $table->text('mitigation')->nullable();
                $table->text('root_cause')->nullable();

                $table->string('external_reference')->nullable();

                $table->timestamp('detected_at');
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->timestamps();

                $table->index(['status', 'severity']);
                $table->index(['type', 'detected_at']);
                $table->index(['client_id', 'status']);
                $table->index(['assigned_to_user_id', 'status']);
            }
        );

        Schema::create(
            'routing_incident_updates',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('routing_incident_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->string('kind', 30)->default('note');
                $table->string('old_status', 30)->nullable();
                $table->string('new_status', 30)->nullable();
                $table->text('message');

                $table->timestamps();

                $table->index([
                    'routing_incident_id',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_incident_updates');
        Schema::dropIfExists('routing_incidents');
    }
};
