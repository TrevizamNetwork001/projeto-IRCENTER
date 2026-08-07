<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->table('invoices', function (Blueprint $table): void {
                $table->string(
                    'billing_email_snapshot'
                )->nullable();

                $table->string(
                    'client_phone_snapshot',
                    50
                )->nullable();

                $table->string(
                    'client_postal_code_snapshot',
                    20
                )->nullable();

                $table->string(
                    'client_street_snapshot'
                )->nullable();

                $table->string(
                    'client_address_number_snapshot',
                    50
                )->nullable();

                $table->string(
                    'client_address_complement_snapshot'
                )->nullable();

                $table->string(
                    'client_district_snapshot'
                )->nullable();

                $table->string(
                    'client_city_snapshot'
                )->nullable();

                $table->string(
                    'client_state_snapshot',
                    20
                )->nullable();

                $table->string(
                    'client_country_snapshot',
                    80
                )->nullable();
            });
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->table('invoices', function (Blueprint $table): void {
                $table->dropColumn([
                    'billing_email_snapshot',
                    'client_phone_snapshot',
                    'client_postal_code_snapshot',
                    'client_street_snapshot',
                    'client_address_number_snapshot',
                    'client_address_complement_snapshot',
                    'client_district_snapshot',
                    'client_city_snapshot',
                    'client_state_snapshot',
                    'client_country_snapshot',
                ]);
            });
    }
};
