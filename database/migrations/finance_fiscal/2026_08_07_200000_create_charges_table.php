<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->create('charges', function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->char(
                    'public_id',
                    26
                )->unique();

                $table->unsignedBigInteger(
                    'invoice_id'
                );

                $table->string(
                    'provider',
                    40
                );

                $table->string(
                    'method',
                    20
                );

                $table->string(
                    'status',
                    20
                )->default('pending');

                /*
                 * Chave interna e canônica.
                 */
                $table->string(
                    'idempotency_key',
                    180
                )->unique();

                /*
                 * ID retornado pelo provider.
                 */
                $table->string(
                    'provider_charge_id',
                    160
                )->nullable();

                $table->decimal(
                    'amount',
                    14,
                    2
                );

                $table->char(
                    'currency',
                    3
                )->default('BRL');

                $table->date('due_on');

                $table->text(
                    'provider_checkout_url'
                )->nullable();

                $table->text(
                    'provider_pix_copy_paste'
                )->nullable();

                $table->timestampTz(
                    'provider_created_at'
                )->nullable();

                $table->timestampTz(
                    'last_synced_at'
                )->nullable();

                $table->timestampsTz();

                $table->foreign('invoice_id')
                    ->references('id')
                    ->on('invoices')
                    ->restrictOnDelete();

                $table->unique(
                    [
                        'provider',
                        'provider_charge_id',
                    ],
                    'charges_provider_external_unique'
                );

                $table->index([
                    'invoice_id',
                    'status',
                ]);

                $table->index([
                    'status',
                    'due_on',
                ]);
            });
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->dropIfExists('charges');
    }
};
