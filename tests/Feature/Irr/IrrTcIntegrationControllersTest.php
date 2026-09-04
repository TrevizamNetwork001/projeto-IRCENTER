<?php

namespace Tests\Feature\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrObject;
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

    public function test_route_store_warns_but_does_not_block_when_prefix_already_exists_as_irr_object(): void
    {
        $maintainer = $this->maintainer(64500);

        IrrObject::query()->create([
            'object_type' => 'route',
            'object_key' => '192.0.2.0/24',
            'source' => 'LOCAL',
            'status' => 'active',
            'active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('irr-routes.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'prefix' => '192.0.2.0/24',
                'version' => 4,
                'origin_asn' => 64500,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');
        $response->assertSessionHas('success');

        $this->assertSame(1, IrrRoute::query()->count(), 'o aviso não deve bloquear o cadastro');
    }

    public function test_route_store_does_not_warn_when_no_conflicting_irr_object_exists(): void
    {
        $maintainer = $this->maintainer(64500);

        $response = $this->actingAs($this->admin())
            ->post(route('irr-routes.store'), [
                'irr_maintainer_id' => $maintainer->id,
                'prefix' => '192.0.2.0/24',
                'version' => 4,
                'origin_asn' => 64500,
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('warning');
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

    public function test_route_show_displays_the_generated_rpsl_preview_always_visible(): void
    {
        $maintainer = $this->maintainer(64500);
        $route = IrrRoute::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
        ]);

        $response = $this->actingAs($this->admin())->get(route('irr-routes.show', $route));

        $response->assertOk();
        $response->assertSee('route: 192.0.2.0/24', false);
        $response->assertSee('source: TC', false);
        $response->assertSee('id="rpsl-publish-dialog"', false);
        $response->assertDontSee('Corrija o RPSL', false);
    }

    public function test_route_show_shows_error_and_disables_publish_when_rpsl_generation_fails(): void
    {
        $maintainer = $this->maintainer(64500);
        $route = IrrRoute::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'descr' => "Cliente X\nmnt-by: MAINT-AS99999",
        ]);

        $response = $this->actingAs($this->admin())->get(route('irr-routes.show', $route));

        $response->assertOk();
        $response->assertSee('Não foi possível gerar o RPSL', false);
        $response->assertSee('title="Corrija o RPSL abaixo antes de publicar"', false);
        $response->assertDontSee('id="rpsl-publish-dialog"', false);
    }

    public function test_publish_route_returns_friendly_error_without_calling_tc_when_rpsl_generation_fails(): void
    {
        Http::fake();

        $maintainer = $this->maintainer(64500);
        $route = IrrRoute::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'descr' => "Cliente X\nmnt-by: MAINT-AS99999",
        ]);

        $response = $this->actingAs($this->admin())->post(route('irr-routes.publish', $route));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertSame(IrrRoute::STATUS_PENDING, $route->fresh()->status);
    }

    public function test_as_set_show_displays_the_generated_rpsl_preview_always_visible(): void
    {
        $maintainer = $this->maintainer(64500);
        $asSet = IrrAsSet::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'name' => 'AS64500:AS-CLIENTES',
            'members' => ['AS64501'],
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
        ]);

        $response = $this->actingAs($this->admin())->get(route('irr-as-sets.show', $asSet));

        $response->assertOk();
        $response->assertSee('as-set: AS64500:AS-CLIENTES', false);
        $response->assertSee('id="rpsl-publish-dialog"', false);
    }

    public function test_as_set_show_shows_error_and_disables_publish_when_admin_c_missing(): void
    {
        $maintainer = $this->maintainer(64500);
        $asSet = IrrAsSet::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'name' => 'AS64500:AS-CLIENTES',
            'members' => ['AS64501'],
            'admin_c' => '',
            'tech_c' => 'JD1-TC',
        ]);

        $response = $this->actingAs($this->admin())->get(route('irr-as-sets.show', $asSet));

        $response->assertOk();
        $response->assertSee('Não foi possível gerar o RPSL', false);
        $response->assertSee('title="Corrija o RPSL abaixo antes de publicar"', false);
        $response->assertDontSee('id="rpsl-publish-dialog"', false);
    }

    public function test_publish_as_set_returns_friendly_error_without_calling_tc_when_rpsl_generation_fails(): void
    {
        Http::fake();

        $maintainer = $this->maintainer(64500);
        $asSet = IrrAsSet::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'name' => 'AS64500:AS-CLIENTES',
            'members' => ['AS64501'],
            'admin_c' => '',
            'tech_c' => 'JD1-TC',
        ]);

        $response = $this->actingAs($this->admin())->post(route('irr-as-sets.publish', $asSet));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertSame(IrrAsSet::STATUS_PENDING, $asSet->fresh()->status);
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
                'admin_c' => 'jd1-tc',
                'tech_c' => 'jd1-tc',
                'notify' => ['noc@example.test'],
            ])
            ->assertRedirect();

        $asSet = IrrAsSet::query()->first();
        $this->assertSame('AS64500:AS-CLIENTES', $asSet->name);
        $this->assertSame(['AS64501', 'AS64502'], $asSet->members);
        $this->assertSame('JD1-TC', $asSet->admin_c);
        $this->assertSame('JD1-TC', $asSet->tech_c);
        $this->assertSame(['noc@example.test'], $asSet->notify);
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
