<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'irr_workflow_steps',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('irr_workflow_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->unsignedTinyInteger('step_number');
                $table->string('step_key', 50);
                $table->string('title');
                $table->text('instructions')->nullable();

                $table->string('status', 30)->default('locked');

                $table->string('email_to')->nullable();
                $table->string('email_subject')->nullable();
                $table->longText('email_body')->nullable();
                $table->longText('rpsl_content')->nullable();

                $table->timestampTz('prepared_at')->nullable();
                $table->timestampTz('sent_at')->nullable();
                $table->timestampTz('confirmed_at')->nullable();
                $table->timestampTz('completed_at')->nullable();

                $table->text('operator_notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['irr_workflow_id', 'step_number'],
                    'irr_workflow_step_number_unique'
                );

                $table->unique(
                    ['irr_workflow_id', 'step_key'],
                    'irr_workflow_step_key_unique'
                );

                $table->index('status');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('irr_workflow_steps');
    }
};
