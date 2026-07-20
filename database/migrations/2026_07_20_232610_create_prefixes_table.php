<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefixes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('autonomous_system_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('prefix', 64)->unique();
            $table->unsignedTinyInteger('ip_version');
            $table->string('description')->nullable();
            $table->string('rir', 20)->nullable();
            $table->string('country', 2)->default('BR');
            $table->string('allocation_status', 30)->default('allocated');
            $table->string('purpose', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('ip_version');
            $table->index('active');
            $table->index('allocation_status');
            $table->index(['client_id', 'ip_version']);
            $table->index(['autonomous_system_id', 'ip_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prefixes');
    }
};
