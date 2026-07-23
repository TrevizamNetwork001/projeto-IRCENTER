<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\IrrWorkflow;
use App\Models\IrrWorkflowStep;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_irr_assistant(): void
    {
        $this->get(route('irr-workflows.index'))
            ->assertRedirect(route('login'));
    }

    public function test_administrator_can_start_workflow(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 65000,
        ]);

        Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('irr-workflows.store'), [
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'name' => 'Implantação IRR AS65000',
                'irr_source' => 'local',
                'destination_email' => 'irr@example.net',
                'maintainer' => 'maint-as65000',
                'as_set' => 'as65000:as-all',
                'route_set' => 'as65000:rs-routes',
                'contact_name' => 'Contato Exemplo',
                'contact_handle' => 'contact-example',
                'contact_email' => 'noc@example.net',
                'contact_phone' => '+55 11 0000-0000',
                'contact_address' => 'Endereço de documentação',
            ]);

        $workflow = IrrWorkflow::query()->firstOrFail();

        $response->assertRedirect(
            route('irr-workflows.show', $workflow)
        );

        $this->assertSame(6, $workflow->steps()->count());

        $this->assertDatabaseHas('irr_workflow_steps', [
            'irr_workflow_id' => $workflow->id,
            'step_number' => 1,
            'status' => IrrWorkflowStep::STATUS_READY,
        ]);

        $this->assertDatabaseHas('irr_workflow_steps', [
            'irr_workflow_id' => $workflow->id,
            'step_number' => 2,
            'status' => IrrWorkflowStep::STATUS_LOCKED,
        ]);

        $firstStep = $workflow->steps()
            ->where('step_number', 1)
            ->firstOrFail();

        $this->assertStringContainsString(
            'person: Contato Exemplo',
            (string) $firstStep->rpsl_content
        );

        $this->assertStringContainsString(
            'mntner: MAINT-AS65000',
            (string) $firstStep->rpsl_content
        );
    }

    public function test_non_administrator_cannot_start_workflow(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
            'active' => true,
        ]);

        $autonomousSystem = AutonomousSystem::factory()->create();

        $this->actingAs($user)
            ->post(route('irr-workflows.store'), [
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'name' => 'Implantação IRR',
                'irr_source' => 'LOCAL',
                'maintainer' => 'MAINT-EXAMPLE',
                'as_set' => 'AS65000:AS-ALL',
                'route_set' => 'AS65000:RS-ROUTES',
                'contact_name' => 'Contato',
                'contact_handle' => 'CONTACT-EXAMPLE',
                'contact_email' => 'noc@example.net',
            ])
            ->assertForbidden();
    }

    public function test_asn_must_belong_to_selected_client(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();
        $otherAsn = AutonomousSystem::factory()->create();

        $this->actingAs($admin)
            ->post(route('irr-workflows.store'), [
                'client_id' => $client->id,
                'autonomous_system_id' => $otherAsn->id,
                'name' => 'Implantação IRR',
                'irr_source' => 'LOCAL',
                'maintainer' => 'MAINT-EXAMPLE',
                'as_set' => 'AS65000:AS-ALL',
                'route_set' => 'AS65000:RS-ROUTES',
                'contact_name' => 'Contato',
                'contact_handle' => 'CONTACT-EXAMPLE',
                'contact_email' => 'noc@example.net',
            ])
            ->assertSessionHasErrors('autonomous_system_id');

        $this->assertDatabaseCount('irr_workflows', 0);
    }

    public function test_step_must_be_sent_before_confirmation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $workflow = $this->createWorkflow();
        $step = $workflow->steps()->where('step_number', 1)->firstOrFail();

        $this->actingAs($admin)
            ->post(route(
                'irr-workflows.steps.confirm',
                [$workflow, $step]
            ))
            ->assertSessionHasErrors('step');

        $this->assertDatabaseHas('irr_workflow_steps', [
            'id' => $step->id,
            'status' => IrrWorkflowStep::STATUS_READY,
        ]);
    }

    public function test_confirming_step_unlocks_and_prepares_next_step(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $workflow = $this->createWorkflow();
        $firstStep = $workflow->steps()
            ->where('step_number', 1)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route(
                'irr-workflows.steps.sent',
                [$workflow, $firstStep]
            ))
            ->assertRedirect();

        $this->assertDatabaseHas('irr_workflow_steps', [
            'id' => $firstStep->id,
            'status' => IrrWorkflowStep::STATUS_WAITING_CONFIRMATION,
        ]);

        $this->actingAs($admin)
            ->post(route(
                'irr-workflows.steps.confirm',
                [$workflow, $firstStep]
            ))
            ->assertRedirect();

        $workflow->refresh();

        $secondStep = $workflow->steps()
            ->where('step_number', 2)
            ->firstOrFail();

        $this->assertSame(2, $workflow->current_step);
        $this->assertSame(
            IrrWorkflowStep::STATUS_READY,
            $secondStep->status
        );

        $this->assertStringContainsString(
            'aut-num: AS65000',
            (string) $secondStep->rpsl_content
        );
    }

    public function test_workflow_completes_after_six_confirmed_steps(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $workflow = $this->createWorkflow();

        for ($number = 1; $number <= 6; $number++) {
            $workflow->refresh();

            $step = $workflow->steps()
                ->where('step_number', $number)
                ->firstOrFail();

            $this->actingAs($admin)
                ->post(route(
                    'irr-workflows.steps.sent',
                    [$workflow, $step]
                ))
                ->assertRedirect();

            $step->refresh();

            $this->actingAs($admin)
                ->post(route(
                    'irr-workflows.steps.confirm',
                    [$workflow, $step]
                ))
                ->assertRedirect();
        }

        $workflow->refresh();

        $this->assertSame(
            IrrWorkflow::STATUS_COMPLETED,
            $workflow->status
        );

        $this->assertNotNull($workflow->completed_at);

        $this->assertSame(
            6,
            $workflow->steps()
                ->where('status', IrrWorkflowStep::STATUS_COMPLETED)
                ->count()
        );
    }

    private function createWorkflow(): IrrWorkflow
    {
        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 65000,
        ]);

        Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        return app(\App\Services\IrrWorkflowService::class)
            ->create([
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'name' => 'Implantação IRR AS65000',
                'irr_source' => 'LOCAL',
                'destination_email' => 'irr@example.net',
                'maintainer' => 'MAINT-AS65000',
                'as_set' => 'AS65000:AS-ALL',
                'route_set' => 'AS65000:RS-ROUTES',
                'contact_name' => 'Contato Exemplo',
                'contact_handle' => 'CONTACT-EXAMPLE',
                'contact_email' => 'noc@example.net',
                'contact_phone' => '+55 11 0000-0000',
                'contact_address' => 'Endereço de documentação',
            ]);
    }
}
