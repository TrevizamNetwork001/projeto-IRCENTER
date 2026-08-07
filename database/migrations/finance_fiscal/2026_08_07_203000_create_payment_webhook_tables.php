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
                'payment_webhook_receipts',
                function (Blueprint $table): void {
                    $table->bigIncrements('id');

                    $table->char(
                        'public_id',
                        26
                    )->unique();

                    $table->string(
                        'provider',
                        40
                    );

                    /*
                     * Hash para correlação.
                     * O token puro nunca é indexado.
                     */
                    $table->char(
                        'token_hash',
                        64
                    )->index();

                    /*
                     * Conteúdo criptografado com APP_KEY.
                     */
                    $table->text(
                        'token_encrypted'
                    );

                    $table->string(
                        'status',
                        20
                    )->default('received');

                    $table->unsignedInteger(
                        'attempt_count'
                    )->default(0);

                    $table->timestampTz(
                        'received_at'
                    );

                    $table->timestampTz(
                        'processing_started_at'
                    )->nullable();

                    $table->timestampTz(
                        'processed_at'
                    )->nullable();

                    $table->text(
                        'last_error'
                    )->nullable();

                    $table->timestampsTz();

                    $table->index([
                        'provider',
                        'status',
                    ]);
                }
            );

        Schema::connection('finance_fiscal')
            ->create(
                'payment_provider_events',
                function (Blueprint $table): void {
                    $table->bigIncrements('id');

                    $table->unsignedBigInteger(
                        'webhook_receipt_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'charge_id'
                    )->nullable();

                    $table->string(
                        'provider',
                        40
                    );

                    $table->char(
                        'notification_token_hash',
                        64
                    );

                    /*
                     * Na Efí é o campo data[].id.
                     */
                    $table->string(
                        'provider_event_id',
                        100
                    );

                    $table->string(
                        'provider_charge_id',
                        160
                    )->nullable();

                    $table->string(
                        'event_type',
                        60
                    );

                    $table->string(
                        'status_current',
                        40
                    )->nullable();

                    $table->string(
                        'status_previous',
                        40
                    )->nullable();

                    $table->unsignedBigInteger(
                        'value_cents'
                    )->nullable();

                    $table->date(
                        'received_by_bank_at'
                    )->nullable();

                    /*
                     * Preserva literalmente a data da Efí.
                     * Não assumimos timezone externo.
                     */
                    $table->string(
                        'provider_created_at_raw',
                        50
                    )->nullable();

                    $table->json(
                        'payload_json'
                    )->nullable();

                    $table->timestampTz(
                        'processed_at'
                    )->nullable();

                    $table->timestampsTz();

                    $table->foreign(
                        'webhook_receipt_id'
                    )
                        ->references('id')
                        ->on(
                            'payment_webhook_receipts'
                        )
                        ->restrictOnDelete();

                    $table->foreign(
                        'charge_id'
                    )
                        ->references('id')
                        ->on('charges')
                        ->restrictOnDelete();

                    $table->unique(
                        [
                            'provider',
                            'notification_token_hash',
                            'provider_event_id',
                        ],
                        'payment_provider_events_unique'
                    );

                    $table->index([
                        'provider',
                        'provider_charge_id',
                    ]);
                }
            );

        Schema::connection('finance_fiscal')
            ->table(
                'charges',
                function (Blueprint $table): void {
                    $table->string(
                        'last_provider_event_id',
                        100
                    )->nullable();

                    $table->timestampTz(
                        'last_provider_event_at'
                    )->nullable();
                }
            );
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->table(
                'charges',
                function (Blueprint $table): void {
                    $table->dropColumn([
                        'last_provider_event_id',
                        'last_provider_event_at',
                    ]);
                }
            );

        Schema::connection('finance_fiscal')
            ->dropIfExists(
                'payment_provider_events'
            );

        Schema::connection('finance_fiscal')
            ->dropIfExists(
                'payment_webhook_receipts'
            );
    }
};
