<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')->table('fiscal_documents', function (Blueprint $table): void {
            $table->string('emission_origin', 20)->default('manual');
            $table->string('access_key', 100)->nullable();
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->text('manual_authorization_notes')->nullable();
            $table->index(['emission_origin', 'status']);
            $table->index('access_key');
            $table->index('registered_by_user_id');
        });

        Schema::connection('finance_fiscal')->table('fiscal_artifacts', function (Blueprint $table): void {
            $table->string('original_filename')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')->table('fiscal_artifacts', function (Blueprint $table): void {
            $table->dropColumn('original_filename');
        });

        Schema::connection('finance_fiscal')->table('fiscal_documents', function (Blueprint $table): void {
            $table->dropIndex(['emission_origin', 'status']);
            $table->dropIndex(['access_key']);
            $table->dropIndex(['registered_by_user_id']);
            $table->dropColumn(['emission_origin', 'access_key', 'registered_by_user_id', 'manual_authorization_notes']);
        });
    }
};
