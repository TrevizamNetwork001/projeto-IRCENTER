<?php

namespace Tests\Feature\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IrrTcIntegrationControllersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);
    }

    private function maintainer(int $asn = 64500): IrrMaintainer
    {
        return IrrMaintainer::query()->create([
            'asn' => $asn,
            'mntner' => 'MAINT-AS'.$asn,
            'password' => 'segredo-do-mntner',
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
        ]);
    }

    public function test_maintainer_creation_screen_links_to_the_tc_wizard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('irr-maintainers.create'))
            ->assertOk()
            ->assertSee('bgp.net.br/wizard.html', false)
            ->assertSee('Criar um mntner novo não é possível', false);
    }

    public function test_maintainer_password_is_encrypted_at_rest(): void
    {
        $maintainer = $this->maintainer();

        $raw = DB::table('irr_maintainers')->where('id', $maintainer->id)->value('password');

        $this->assertStringNotContainsString('segredo-do-mntner', $raw);
        $this->assertSame('segredo-do-mntner', $maintainer->fresh()->password);
    }

    public function test_route_store_rejects_origin_asn_that_does_not_match_maintainer_asn(): void
    {
        $maintainer = $this->maintainer(64500);

        $this->actingAs($this->admin())
            ->post(route('irr-routes.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'prefix' => '192.0.2.0/24',
                'version' => 4,
                'origin_asn' => 64999,
            ])
            ->assertSessionHasErrors('origin_asn');

        $this->assertSame(0, IrrRoute::query()->count());
    }

    public function test_route_store_accepts_matching_origin_asn_and_normalizes_prefix(): void
    {
        $maintainer = $this->maintainer(64500);

        $this->actingAs($this->admin())
            ->post(route('irr-routes.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'prefix' => '192.0.2.5/24',
                'version' => 4,
                'origin_asn' => 64500,
            ])
            ->assertRedirect();

        $route = IrrRoute::query()->first();
        $this->assertSame('192.0.2.0/24', $route->prefix);
        $this->assertSame(IrrRoute::STATUS_PENDING, $route->status);
    }

    public function test_route_store_rejects_invalid_prefix(): void
    {
        $maintainer = $this->maintainer(64500);

        $this->actingAs($this->admin())
            ->post(route('irr-routes.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'prefix' => 'not-a-prefix',
                'version' => 4,
                'origin_asn' => 64500,
            ])
            ->assertSessionHasErrors('prefix');
    }

    public function test_route_unique_constraint_on_prefix_and_origin_asn(): void
    {
        $maintainer = $this->maintainer(64500);

        IrrRoute::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
        ]);

        $this->actingAs($this->admin())
            ->post(route('irr-routes.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'prefix' => '192.0.2.0/24',
                'version' => 4,
                'origin_asn' => 64500,
            ])
            ->assertSessionHasErrors('prefix');
    }

    public function test_publish_route_action_calls_tc_and_updates_status(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'objects' => [['successful' => true]],
            ], 200),
        ]);

        $maintainer = $this->maintainer(64500);
        $route = IrrRoute::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
        ]);

        $this->actingAs($this->admin())
            ->post(route('irr-routes.publish', $route))
            ->assertRedirect();

        $this->assertSame(IrrRoute::STATUS_PUBLISHED, $route->fresh()->status);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'bgp.net.br/v1/submit/')
                && str_contains($request['objects'][0]['object_text'], 'source: TC');
        });
    }

    public function test_as_set_store_requires_at_least_one_member_and_as_prefixed_name(): void
    {
        $maintainer = $this->maintainer(64500);

        $this->actingAs($this->admin())
            ->post(route('irr-as-sets.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'name' => 'CLIENTES',
                'members' => [],
            ])
            ->assertSessionHasErrors(['name', 'members']);

        $this->assertSame(0, IrrAsSet::query()->count());
    }

    public function test_as_set_store_succeeds_with_valid_data(): void
    {
        $maintainer = $this->maintainer(64500);

        $this->actingAs($this->admin())
            ->post(route('irr-as-sets.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'name' => 'as64500:as-clientes',
                'members' => ['AS64501', 'AS64502'],
            ])
            ->assertRedirect();

        $asSet = IrrAsSet::query()->first();
        $this->assertSame('AS64500:AS-CLIENTES', $asSet->name);
        $this->assertSame(['AS64501', 'AS64502'], $asSet->members);
    }

    public function test_viewer_cannot_create_maintainer_route_or_as_set(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('irr-maintainers.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('irr-routes.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('irr-as-sets.create'))->assertForbidden();
    }
}
