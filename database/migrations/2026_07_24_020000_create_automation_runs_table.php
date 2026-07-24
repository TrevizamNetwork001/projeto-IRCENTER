<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'automation_runs',
            function (Blueprint $table): void {
                $table->id();

                $table->string('automation', 100);
                $table->string('trigger', 20)->default('manual');
                $table->string('status', 20)->default('running');

                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();

                $table->unsignedInteger('processed_items')
                    ->default(0);

                $table->unsignedInteger('result_items')
                    ->default(0);

                $table->json('metadata')->nullable();
                $table->text('error_message')->nullable();

                $table->timestamps();

                $table->index([
                    'automation',
                    'started_at',
                ]);

                $table->index([
                    'status',
                    'started_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
