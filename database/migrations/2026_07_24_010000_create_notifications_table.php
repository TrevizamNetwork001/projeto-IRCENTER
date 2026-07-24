<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('unique_key', 120);
            $table->string('type', 40)->default('operational');
            $table->string('priority', 20)->default('info');

            $table->string('title', 160);
            $table->text('message');
            $table->text('action_url')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'unique_key']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'resolved_at']);
            $table->index(['priority', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
