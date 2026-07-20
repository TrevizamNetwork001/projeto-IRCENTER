<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autonomous_systems', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedBigInteger('asn')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rir', 20)->nullable();
            $table->string('country', 2)->default('BR');
            $table->string('website')->nullable();
            $table->string('noc_contact')->nullable();
            $table->string('noc_email')->nullable();
            $table->string('noc_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('rir');
            $table->index('active');
            $table->index(['client_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonomous_systems');
    }
};
