<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'irr_workflow_prefixes',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('irr_workflow_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->foreignId('prefix_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->unsignedTinyInteger('ip_version');
                $table->string('prefix', 64);

                $table->string('route_set_mode', 30)
                    ->default('exact');

                $table->unsignedTinyInteger('maximum_length')
                    ->nullable();

                $table->boolean('generate_route_object')
                    ->default(true);

                $table->boolean('active')
                    ->default(true);

                $table->timestamps();

                $table->unique(
                    ['irr_workflow_id', 'prefix_id'],
                    'irr_workflow_prefix_unique'
                );

                $table->index([
                    'irr_workflow_id',
                    'ip_version',
                    'active',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_workflow_prefixes');
    }
};
