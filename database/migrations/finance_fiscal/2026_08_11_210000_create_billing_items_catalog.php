<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')->create(
            'billing_items',
            function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->char('public_id', 26)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('default_amount', 14, 2);
                $table->boolean('active')->default(true);
                $table->timestampsTz();
                $table->index(['active', 'name']);
            }
        );

        Schema::connection('finance_fiscal')->table(
            'billing_contract_items',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('billing_item_id')
                    ->nullable()
                    ->after('billing_contract_id');
                $table->foreign('billing_item_id')
                    ->references('id')
                    ->on('billing_items')
                    ->restrictOnDelete();
                $table->index('billing_item_id');
            }
        );
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')->table(
            'billing_contract_items',
            function (Blueprint $table): void {
                $table->dropForeign(['billing_item_id']);
                $table->dropColumn('billing_item_id');
            }
        );
        Schema::connection('finance_fiscal')
            ->dropIfExists('billing_items');
    }
};
