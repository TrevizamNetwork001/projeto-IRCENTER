<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance_fiscal')
            ->table('charges', function (Blueprint $table): void {
                $table->text('provider_billet_url')
                    ->nullable()
                    ->after('provider_checkout_url');

                $table->text('provider_billet_pdf_url')
                    ->nullable()
                    ->after('provider_billet_url');

                $table->string('provider_barcode', 255)
                    ->nullable()
                    ->after('provider_billet_pdf_url');
            });
    }

    public function down(): void
    {
        Schema::connection('finance_fiscal')
            ->table('charges', function (Blueprint $table): void {
                $table->dropColumn([
                    'provider_billet_url',
                    'provider_billet_pdf_url',
                    'provider_barcode',
                ]);
            });
    }
};
