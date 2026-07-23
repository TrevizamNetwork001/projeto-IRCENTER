<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irr_objects', function (Blueprint $table): void {
            $table->id();

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

            $table->string('object_type', 20);
            $table->string('object_key', 100);
            $table->string('source', 50)->default('LOCAL');
            $table->string('maintainer', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('description')->nullable();
            $table->longText('raw_text')->nullable();
            $table->json('attributes')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(
                ['object_type', 'object_key', 'source'],
                'irr_objects_identity_unique'
            );

            $table->index('object_type');
            $table->index('source');
            $table->index('status');
            $table->index('active');
            $table->index(['client_id', 'object_type']);
            $table->index(['autonomous_system_id', 'object_type']);
            $table->index(['prefix_id', 'object_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_objects');
    }
};
