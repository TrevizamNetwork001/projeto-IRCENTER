<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('client_code', 20)
                ->nullable()
                ->unique()
                ->after('id');

            $table->string('contract_number', 50)
                ->nullable()
                ->unique()
                ->after('client_code');

            $table->string('postal_code', 8)
                ->nullable()
                ->after('website');

            $table->string('street')
                ->nullable()
                ->after('postal_code');

            $table->string('address_number', 30)
                ->nullable()
                ->after('street');

            $table->string('address_complement', 100)
                ->nullable()
                ->after('address_number');

            $table->string('district', 100)
                ->nullable()
                ->after('address_complement');
        });

        DB::table('clients')
            ->orderBy('id')
            ->eachById(function (object $client): void {
                DB::table('clients')
                    ->where('id', $client->id)
                    ->update([
                        'client_code' => sprintf(
                            'CLI-%06d',
                            $client->id
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique(['client_code']);
            $table->dropUnique(['contract_number']);

            $table->dropColumn([
                'client_code',
                'contract_number',
                'postal_code',
                'street',
                'address_number',
                'address_complement',
                'district',
            ]);
        });
    }
};
