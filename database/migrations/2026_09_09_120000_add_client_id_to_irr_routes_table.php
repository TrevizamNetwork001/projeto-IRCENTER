<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('irr_routes', function (Blueprint $table): void {
            $table->foreignId('client_id')
                ->nullable()
                ->after('irr_maintainer_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('irr_routes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
