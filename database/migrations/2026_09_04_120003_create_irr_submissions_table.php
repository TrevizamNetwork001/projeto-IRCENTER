<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irr_submissions', function (Blueprint $table): void {
            $table->id();

            $table->morphs('submittable');
            $table->string('operation', 20);
            $table->string('source', 50)->default('TC');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->boolean('successful');

            $table->timestampTz('created_at')->useCurrent();

            $table->index('operation');
            $table->index('successful');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_submissions');
    }
};
