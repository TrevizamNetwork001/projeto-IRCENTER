<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\IrrObject;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_use_global_search(): void
    {
        $this->get(route('search.index', ['q' => 'rede']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_search_resources(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'trade_name' => 'Conectividade Aurora',
        ]);
        $autonomousSystem = AutonomousSystem::factory()->create([
            'client_id' => $client->id,
            'asn' => 64555,
            'name' => 'Backbone Aurora',
        ]);
        Prefix::factory()->create([
            'client_id' => $client->id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '203.0.113.0/24',
            'description' => 'Bloco Aurora',
        ]);
        IrrObject::factory()->create([
            'client_id' => $client->id,
            'object_key' => 'MAINT-AURORA',
        ]);

        $this->actingAs($user)
            ->get(route('search.index', ['q' => 'Aurora']))
            ->assertOk()
            ->assertSee('Conectividade Aurora')
            ->assertSee('Backbone Aurora')
            ->assertSee('Bloco Aurora')
            ->assertSee('MAINT-AURORA');
    }

    public function test_search_accepts_empty_query_and_rejects_oversized_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search.index'))
            ->assertOk()
            ->assertSee('Digite o que deseja encontrar');

        $this->actingAs($user)
            ->get(route('search.index', ['q' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('q');
    }

    public function test_search_finds_enabled_fiscal_module(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search.index', ['q' => 'fiscal']))
            ->assertOk()
            ->assertSee('Páginas e módulos')
            ->assertSee('Fiscal')
            ->assertSee(route('fiscal.dashboard'));
    }

    public function test_search_hides_disabled_and_restricted_modules(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', false);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)
            ->get(route('search.index', ['q' => 'fiscal']))
            ->assertOk()
            ->assertSee('Nenhum recurso encontrado')
            ->assertDontSee(route('fiscal.dashboard'));

        $this->actingAs($viewer)
            ->get(route('search.index', ['q' => 'auditoria']))
            ->assertOk()
            ->assertDontSee(route('audit.index'));
    }

    public function test_live_suggestions_find_module_from_partial_term(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('search.suggestions', ['q' => 'config']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Fiscal',
                'type' => 'Página',
                'url' => route('fiscal.dashboard'),
            ]);
    }

    public function test_live_suggestions_require_two_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('search.suggestions', ['q' => 'f']))
            ->assertOk()
            ->assertExactJson(['suggestions' => []]);
    }
}
