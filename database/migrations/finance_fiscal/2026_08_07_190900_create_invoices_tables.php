<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->create('invoices', function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->char('public_id', 26)->unique();

                $table->unsignedBigInteger(
                    'billing_contract_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'core_client_id'
                );

                $table->string(
                    'client_code_snapshot',
                    50
                )->nullable();

                $table->string(
                    'client_legal_name_snapshot'
                );

                $table->string(
                    'client_trade_name_snapshot'
                )->nullable();

                $table->string(
                    'client_document_snapshot',
                    32
                )->nullable();

                $table->string('source', 20);

                $table->date(
                    'competence_month'
                )->nullable();

                $table->string(
                    'generation_key',
                    120
                )->nullable()->unique();

                $table->date('issued_on');
                $table->date('due_on');

                $table->char('currency', 3)
                    ->default('BRL');

                $table->string('status', 20)
                    ->default('open');

                $table->decimal(
                    'subtotal',
                    14,
                    2
                );

                $table->decimal(
                    'discount',
                    14,
                    2
                )->default(0);

                $table->decimal(
                    'total',
                    14,
                    2
                );

                $table->timestampsTz();

                $table->foreign(
                    'billing_contract_id'
                )
                    ->references('id')
                    ->on('billing_contracts')
                    ->restrictOnDelete();

                $table->unique(
                    [
                        'billing_contract_id',
                        'competence_month',
                    ],
                    'invoices_contract_competence_unique'
                );

                $table->index([
                    'core_client_id',
                    'status',
                ]);

                $table->index([
                    'status',
                    'due_on',
                ]);
            });

        Schema::connection('finance_fiscal')
            ->create(
                'invoice_items',
                function (Blueprint $table): void {
                    $table->bigIncrements('id');

                    $table->unsignedBigInteger(
                        'invoice_id'
                    );

                    $table->unsignedBigInteger(
                        'billing_contract_item_id'
                    )->nullable();

                    $table->string(
                        'service_code',
                        80
                    )->nullable();

                    $table->string('description');

                    $table->decimal(
                        'quantity',
                        12,
                        4
                    );

                    $table->decimal(
                        'unit_amount',
                        14,
                        2
                    );

                    $table->decimal(
                        'line_total',
                        14,
                        2
                    );

                    $table->unsignedInteger(
                        'sort_order'
                    )->default(0);

                    $table->timestampsTz();

                    $table->foreign('invoice_id')
                        ->references('id')
                        ->on('invoices')
                        ->restrictOnDelete();

                    $table->foreign(
                        'billing_contract_item_id'
                    )
                        ->references('id')
                        ->on('billing_contract_items')
                        ->restrictOnDelete();

                    $table->index('invoice_id');
                }
            );
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->dropIfExists('invoice_items');

        Schema::connection('finance_fiscal')
            ->dropIfExists('invoices');
    }
};
