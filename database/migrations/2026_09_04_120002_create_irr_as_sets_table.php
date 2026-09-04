<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irr_as_sets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('irr_maintainer_id')
                ->constrained('irr_maintainers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 100);
            $table->string('descr')->nullable();
            $table->json('members');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('last_published_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique('name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_as_sets');
    }
};
