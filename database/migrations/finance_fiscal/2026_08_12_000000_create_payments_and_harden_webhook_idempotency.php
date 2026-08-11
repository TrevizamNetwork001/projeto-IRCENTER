<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->table('payment_webhook_receipts', function (Blueprint $table): void {
                $table->unique(
                    ['provider', 'token_hash'],
                    'payment_webhook_receipts_provider_token_unique'
                );
            });

        Schema::connection('finance_fiscal')
            ->table('payment_provider_events', function (Blueprint $table): void {
                $table->unique(
                    ['provider', 'provider_event_id'],
                    'payment_provider_events_provider_event_unique'
                );
            });

        Schema::connection('finance_fiscal')
            ->create('payments', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->char('public_id', 26)->unique();
                $table->unsignedBigInteger('charge_id')->unique();
                $table->unsignedBigInteger('invoice_id');
                $table->string('provider', 40);
                $table->string('provider_payment_id', 100);
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                $table->timestampTz('paid_at');
                $table->timestampsTz();

                $table->foreign('charge_id')
                    ->references('id')->on('charges')->restrictOnDelete();
                $table->foreign('invoice_id')
                    ->references('id')->on('invoices')->restrictOnDelete();
                $table->unique(
                    ['provider', 'provider_payment_id'],
                    'payments_provider_reference_unique'
                );
                $table->index(['invoice_id', 'paid_at']);
            });
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')->dropIfExists('payments');

        Schema::connection('finance_fiscal')
            ->table('payment_provider_events', function (Blueprint $table): void {
                $table->dropUnique('payment_provider_events_provider_event_unique');
            });

        Schema::connection('finance_fiscal')
            ->table('payment_webhook_receipts', function (Blueprint $table): void {
                $table->dropUnique('payment_webhook_receipts_provider_token_unique');
            });
    }
};
