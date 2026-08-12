<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('finance_fiscal');

        $schema->create('fiscal_issuer_profiles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('document', 14)->unique();
            $table->string('municipal_registration', 40)->nullable();
            $table->string('municipality_code', 7);
            $table->string('municipality', 120);
            $table->char('state', 2);
            $table->string('postal_code', 8);
            $table->string('street');
            $table->string('address_number', 30);
            $table->string('address_complement', 100)->nullable();
            $table->string('district', 100);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->json('tax_settings')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['active', 'legal_name']);
        });

        $schema->create('fiscal_customer_profiles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('core_client_id')->unique();
            $table->string('document', 14)->nullable();
            $table->string('legal_name')->nullable();
            $table->string('municipal_registration', 40)->nullable();
            $table->string('state_registration', 40)->nullable();
            $table->string('fiscal_email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('address_number', 30)->nullable();
            $table->string('address_complement', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->string('municipality_code', 7)->nullable();
            $table->char('state', 2)->nullable();
            $table->char('country_code', 2)->default('BR');
            $table->string('country_numeric_code', 4)->nullable();
            $table->timestampsTz();
            $table->index('document');
        });

        $schema->create('fiscal_service_profiles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('billing_item_id')->nullable()->unique();
            $table->string('fiscal_description');
            $table->string('national_service_code', 20)->nullable();
            $table->string('municipal_service_code', 40)->nullable();
            $table->string('nbs_code', 20)->nullable();
            $table->string('municipality_code', 7)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->decimal('iss_rate', 7, 4)->nullable();
            $table->json('tax_settings')->nullable();
            $table->json('withholding_settings')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->foreign('billing_item_id')->references('id')->on('billing_items')->restrictOnDelete();
            $table->index(['active', 'fiscal_description']);
        });

        $schema->create('fiscal_documents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('core_client_id');
            $table->unsignedBigInteger('fiscal_issuer_profile_id');
            $table->unsignedBigInteger('fiscal_customer_profile_id');
            $table->unsignedBigInteger('billing_contract_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('charge_id')->nullable();
            $table->unsignedBigInteger('billing_item_id')->nullable();
            $table->string('environment', 20)->default('homologation');
            $table->string('provider', 40)->default('fake');
            $table->string('status', 20)->default('draft');
            $table->date('competence_date');
            $table->date('service_date')->nullable();
            $table->decimal('services_amount', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('deduction_amount', 14, 2)->default(0);
            $table->decimal('withholding_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->char('currency', 3)->default('BRL');
            $table->text('summary')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->string('external_reference', 160)->nullable();
            $table->string('nfse_number', 80)->nullable();
            $table->string('verification_code', 120)->nullable();
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('rejection_code', 80)->nullable();
            $table->text('rejection_message')->nullable();
            $table->json('issuer_snapshot')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('service_snapshot')->nullable();
            $table->json('values_snapshot')->nullable();
            $table->json('tax_snapshot')->nullable();
            $table->timestampTz('prepared_at')->nullable();
            $table->timestampsTz();
            $table->foreign('fiscal_issuer_profile_id')->references('id')->on('fiscal_issuer_profiles')->restrictOnDelete();
            $table->foreign('fiscal_customer_profile_id')->references('id')->on('fiscal_customer_profiles')->restrictOnDelete();
            $table->foreign('billing_contract_id')->references('id')->on('billing_contracts')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('charge_id')->references('id')->on('charges')->restrictOnDelete();
            $table->foreign('billing_item_id')->references('id')->on('billing_items')->restrictOnDelete();
            $table->index(['core_client_id', 'status']);
            $table->index(['status', 'competence_date']);
        });

        $schema->create('fiscal_document_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fiscal_document_id');
            $table->unsignedBigInteger('fiscal_service_profile_id')->nullable();
            $table->unsignedBigInteger('billing_item_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_amount', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->json('classification_snapshot')->nullable();
            $table->json('tax_snapshot')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->foreign('fiscal_document_id')->references('id')->on('fiscal_documents')->restrictOnDelete();
            $table->foreign('fiscal_service_profile_id')->references('id')->on('fiscal_service_profiles')->restrictOnDelete();
            $table->foreign('billing_item_id')->references('id')->on('billing_items')->restrictOnDelete();
            $table->index('fiscal_document_id');
        });

        $schema->create('fiscal_idempotencies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fiscal_document_id');
            $table->string('operation', 40);
            $table->string('idempotency_key', 120);
            $table->string('provider', 40);
            $table->timestampsTz();
            $table->foreign('fiscal_document_id')->references('id')->on('fiscal_documents')->restrictOnDelete();
            $table->unique(['operation', 'idempotency_key', 'provider'], 'fiscal_idempotency_operation_key_provider_unique');
        });

        $schema->create('fiscal_transmissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fiscal_document_id');
            $table->string('provider', 40);
            $table->string('operation', 40);
            $table->unsignedInteger('attempt_number');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->string('result', 30)->nullable();
            $table->string('response_code', 80)->nullable();
            $table->text('sanitized_message')->nullable();
            $table->string('correlation_id', 120)->nullable();
            $table->timestampsTz();
            $table->foreign('fiscal_document_id')->references('id')->on('fiscal_documents')->restrictOnDelete();
            $table->unique(['fiscal_document_id', 'operation', 'attempt_number'], 'fiscal_transmission_attempt_unique');
        });

        $schema->create('fiscal_artifacts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fiscal_document_id');
            $table->string('type', 40);
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path', 500);
            $table->string('mime_type', 120);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->timestampsTz();
            $table->foreign('fiscal_document_id')->references('id')->on('fiscal_documents')->restrictOnDelete();
            $table->unique(['storage_disk', 'storage_path']);
            $table->index(['fiscal_document_id', 'type']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('finance_fiscal');
        foreach (['fiscal_artifacts', 'fiscal_transmissions', 'fiscal_idempotencies', 'fiscal_document_items', 'fiscal_documents', 'fiscal_service_profiles', 'fiscal_customer_profiles', 'fiscal_issuer_profiles'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
