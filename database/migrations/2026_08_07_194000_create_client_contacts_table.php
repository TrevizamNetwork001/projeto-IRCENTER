<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'client_contacts',
            function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('client_id');

                $table->string('type', 20);

                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();

                $table->boolean('is_primary')
                    ->default(false);

                $table->boolean('active')
                    ->default(true);

                $table->timestampsTz();

                $table->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->restrictOnDelete();

                $table->unique([
                    'client_id',
                    'type',
                    'email',
                ]);

                $table->index([
                    'client_id',
                    'type',
                    'active',
                    'is_primary',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
