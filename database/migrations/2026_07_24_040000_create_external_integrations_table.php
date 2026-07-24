<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'external_integrations',
            function (Blueprint $table): void {
                $table->id();

                $table->string('name');
                $table->string('type', 40);
                $table->string('endpoint', 2048);

                $table->string('authentication_type', 20)
                    ->default('none');

                $table->string('username')->nullable();
                $table->text('secret')->nullable();

                $table->unsignedTinyInteger('timeout_seconds')
                    ->default(10);

                $table->boolean('active')->default(true);

                $table->timestamp('last_tested_at')->nullable();
                $table->string('last_test_status', 20)->nullable();
                $table->unsignedSmallInteger('last_http_status')
                    ->nullable();

                $table->text('last_error')->nullable();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(['active', 'type']);
                $table->index('last_test_status');
            }
        );

        Schema::create(
            'external_integration_runs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('external_integration_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->foreignId('requested_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->string('operation', 30)
                    ->default('connectivity_test');

                $table->string('status', 20)
                    ->default('pending');

                $table->unsignedSmallInteger('http_status')
                    ->nullable();

                $table->unsignedInteger('duration_ms')
                    ->nullable();

                $table->string('resolved_ip', 45)->nullable();
                $table->string('response_content_type', 150)
                    ->nullable();

                $table->text('error_message')->nullable();

                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();

                $table->timestamps();

                $table->index([
                    'external_integration_id',
                    'created_at',
                ]);

                $table->index([
                    'status',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('external_integration_runs');
        Schema::dropIfExists('external_integrations');
    }
};
