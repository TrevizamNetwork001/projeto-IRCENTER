<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->create('billing_contracts', function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->char('public_id', 26)->unique();

                /*
                 * Referencia logica ao Cliente no IRCENTER Core.
                 * Sem foreign key entre databases.
                 */
                $table->unsignedBigInteger('core_client_id');

                /*
                 * Snapshot minimo para preservar identidade
                 * do contrato mesmo se o Core mudar depois.
                 */
                $table->string('client_code_snapshot', 50)->nullable();
                $table->string('client_legal_name_snapshot');
                $table->string('client_trade_name_snapshot')->nullable();
                $table->string('client_document_snapshot', 32)->nullable();

                $table->string('frequency', 20)
                    ->default('monthly');

                $table->unsignedTinyInteger('generation_day')
                    ->default(5);

                $table->unsignedTinyInteger('due_day')
                    ->default(20);

                $table->char('currency', 3)
                    ->default('BRL');

                $table->string('status', 20)
                    ->default('draft');

                $table->string('billing_email_override')
                    ->nullable();

                /*
                 * Defaults deliberadamente seguros.
                 */
                $table->boolean('auto_charge')
                    ->default(false);

                $table->boolean('send_email')
                    ->default(false);

                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();

                $table->timestampsTz();

                $table->index('core_client_id');

                $table->index([
                    'core_client_id',
                    'status',
                ]);

                $table->index([
                    'status',
                    'generation_day',
                ]);
            });

        Schema::connection('finance_fiscal')
            ->create('billing_contract_items', function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->unsignedBigInteger(
                    'billing_contract_id'
                );

                $table->string(
                    'service_code',
                    80
                )->nullable();

                $table->string('description');

                /*
                 * Valores monetarios nunca usam float.
                 */
                $table->decimal(
                    'quantity',
                    12,
                    4
                )->default(1);

                $table->decimal(
                    'unit_amount',
                    14,
                    2
                );

                $table->boolean('active')
                    ->default(true);

                $table->unsignedInteger('sort_order')
                    ->default(0);

                $table->timestampsTz();

                $table->foreign('billing_contract_id')
                    ->references('id')
                    ->on('billing_contracts')
                    ->restrictOnDelete();

                $table->index([
                    'billing_contract_id',
                    'active',
                ]);
            });
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->dropIfExists('billing_contract_items');

        Schema::connection('finance_fiscal')
            ->dropIfExists('billing_contracts');
    }
};
