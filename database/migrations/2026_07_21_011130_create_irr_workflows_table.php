<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irr_workflows', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('autonomous_system_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name');
            $table->string('irr_source', 50)->default('LOCAL');
            $table->string('destination_email')->nullable();

            $table->string('maintainer', 100);
            $table->string('as_set', 100)->nullable();
            $table->string('route_set', 100)->nullable();

            $table->string('contact_name');
            $table->string('contact_handle', 100);
            $table->string('contact_email');
            $table->string('contact_phone', 50)->nullable();
            $table->text('contact_address')->nullable();

            $table->string('status', 30)->default('in_progress');
            $table->unsignedTinyInteger('current_step')->default(1);

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index(['autonomous_system_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_workflows');
    }
};
