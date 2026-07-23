<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpki_validations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('prefix_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('rpki_roa_id')
                ->nullable()
                ->constrained('rpki_roas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('status', 30);
            $table->string('reason', 100)->nullable();
            $table->string('validated_prefix', 64);
            $table->unsignedTinyInteger('ip_version');
            $table->unsignedBigInteger('validated_asn')->nullable();
            $table->unsignedSmallInteger('matched_max_length')->nullable();
            $table->unsignedInteger('matching_roas_count')->default(0);
            $table->string('source', 50)->default('LOCAL');
            $table->text('details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('checked_at');
            $table->timestamps();

            $table->index('status');
            $table->index('checked_at');
            $table->index(['prefix_id', 'checked_at']);
            $table->index(['prefix_id', 'status']);
            $table->index(['validated_asn', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpki_validations');
    }
};
