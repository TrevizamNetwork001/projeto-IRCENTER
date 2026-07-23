<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'irr_workflow_relationships',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('irr_workflow_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('remote_asn');
                $table->string('relationship_type', 30);

                $table->boolean('import_ipv4')->default(true);
                $table->boolean('import_ipv6')->default(false);
                $table->boolean('export_ipv4')->default(true);
                $table->boolean('export_ipv6')->default(false);

                $table->string('import_policy')
                    ->default('ANY');

                $table->text('export_policy')
                    ->nullable();

                $table->string('member_of')
                    ->nullable();

                $table->text('description')
                    ->nullable();

                $table->boolean('active')
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'irr_workflow_id',
                    'relationship_type',
                    'active',
                ]);

                $table->index('remote_asn');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_workflow_relationships');
    }
};
