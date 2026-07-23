<?php

namespace Database\Factories;

use App\Models\IrrWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\IrrWorkflowStep>
 */
class IrrWorkflowStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'irr_workflow_id' => IrrWorkflow::factory(),
            'step_number' => 1,
            'step_key' => 'identity',
            'title' => 'Identidade e contatos',
            'instructions' => 'Revise os dados e prepare o primeiro envio.',
            'status' => 'ready',
            'email_to' => 'irr@example.net',
            'email_subject' => 'Cadastro inicial de contato IRR',
            'email_body' => 'Conteúdo de documentação.',
            'rpsl_content' => 'person: Contato Exemplo',
            'prepared_at' => now(),
            'sent_at' => null,
            'confirmed_at' => null,
            'completed_at' => null,
            'operator_notes' => null,
        ];
    }
}
