<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irr_routes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('irr_maintainer_id')
                ->constrained('irr_maintainers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('prefix', 64);
            $table->unsignedTinyInteger('version');
            $table->unsignedInteger('origin_asn');
            $table->string('descr')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('last_published_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(['prefix', 'origin_asn']);
            $table->index('status');
            $table->index('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_routes');
    }
};
