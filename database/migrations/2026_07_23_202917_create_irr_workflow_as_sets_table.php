<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'irr_workflow_as_sets',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('irr_workflow_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->string('name', 100);
                $table->string('purpose', 30)->default('all');
                $table->text('description')->nullable();
                $table->json('members')->nullable();
                $table->boolean('active')->default(true);

                $table->timestamps();

                $table->unique(
                    ['irr_workflow_id', 'name'],
                    'irr_workflow_as_set_unique'
                );

                $table->index([
                    'irr_workflow_id',
                    'purpose',
                    'active',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_workflow_as_sets');
    }
};
