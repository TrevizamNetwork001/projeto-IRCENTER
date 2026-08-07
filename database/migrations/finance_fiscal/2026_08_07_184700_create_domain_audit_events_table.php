<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->create(
                'domain_audit_events',
                function (Blueprint $table): void {
                    $table->bigIncrements('id');

                    $table->string(
                        'module',
                        32
                    );

                    $table->string(
                        'action',
                        120
                    );

                    /*
                     * Referencia logica ao usuario do Core.
                     *
                     * Deliberadamente sem foreign key entre
                     * databases.
                     */
                    $table->unsignedBigInteger(
                        'actor_user_id'
                    )->nullable();

                    $table->string(
                        'entity_type',
                        120
                    )->nullable();

                    /*
                     * String para aceitar futuramente IDs
                     * numericos ou UUID sem migracao destrutiva.
                     */
                    $table->string(
                        'entity_id',
                        100
                    )->nullable();

                    $table->uuid(
                        'correlation_id'
                    )->nullable();

                    $table->json(
                        'metadata'
                    )->nullable();

                    $table->string(
                        'ip_address',
                        45
                    )->nullable();

                    $table->string(
                        'user_agent',
                        1000
                    )->nullable();

                    $table->timestampTz(
                        'created_at'
                    )->useCurrent();

                    $table->index([
                        'module',
                        'created_at',
                    ]);

                    $table->index([
                        'entity_type',
                        'entity_id',
                    ]);

                    $table->index(
                        'correlation_id'
                    );

                    $table->index(
                        'actor_user_id'
                    );
                }
            );
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->dropIfExists(
                'domain_audit_events'
            );
    }
};
