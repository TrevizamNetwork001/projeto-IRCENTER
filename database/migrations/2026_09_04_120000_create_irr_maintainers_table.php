<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irr_maintainers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedInteger('asn');
            $table->string('mntner', 100);
            $table->text('password');
            $table->string('admin_c', 100);
            $table->string('tech_c', 100);
            $table->string('descr')->nullable();
            $table->string('notify_email')->nullable();

            $table->timestamps();

            $table->unique('mntner');
            $table->index('asn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_maintainers');
    }
};
