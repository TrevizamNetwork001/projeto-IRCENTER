<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table): void {
            $table->json('scopes')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
