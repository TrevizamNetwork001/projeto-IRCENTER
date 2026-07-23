<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpki_roas', function (Blueprint $table): void {
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

            $table->string('prefix', 64);
            $table->unsignedTinyInteger('ip_version');
            $table->unsignedBigInteger('asn');
            $table->unsignedSmallInteger('max_length');
            $table->string('source', 50)->default('RPKI');
            $table->string('tal', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('payload_hash', 64)->nullable();
            $table->timestampTz('not_before')->nullable();
            $table->timestampTz('not_after')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(
                ['prefix', 'asn', 'max_length', 'source'],
                'rpki_roas_identity_unique'
            );

            $table->index('ip_version');
            $table->index('asn');
            $table->index('source');
            $table->index('status');
            $table->index('active');
            $table->index(['prefix_id', 'active']);
            $table->index(['autonomous_system_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpki_roas');
    }
};
