<?php

namespace Tests\Feature\Irr;

use App\Models\IrrMaintainer;
use App\Models\IrrObject;
use App\Models\IrrRoute;
use App\Models\IrrSubmission;
use App\Services\Irr\RpslBuilder;
use App\Services\Irr\TcIrrClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TcIrrClientTest extends TestCase
{
    use RefreshDatabase;

    private function maintainer(): IrrMaintainer
    {
        return IrrMaintainer::query()->create([
            'asn' => 64500,
            'mntner' => 'MAINT-AS64500',
            'password' => 'segredo-do-mntner',
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
        ]);
    }

    private function route(IrrMaintainer $maintainer): IrrRoute
    {
        return IrrRoute::query()->create([
            'irr_maintainer_id' => $maintainer->id,
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
        ]);
    }

    public function test_publish_reads_success_from_body_even_with_http_200(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'summary' => ['objects_found' => 1, 'successful' => 1, 'failed' => 0],
                'objects' => [
                    ['successful' => true, 'type' => 'route', 'object_class' => 'route', 'rpsl_pk' => '192.0.2.0/24AS64500'],
                ],
            ], 200),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);
        $rpsl = (new RpslBuilder)->route($route->fresh(['maintainer']));

        (new TcIrrClient)->publish($maintainer, $route, $rpsl, 'create');

        $route->refresh();

        $this->assertSame(IrrRoute::STATUS_PUBLISHED, $route->status);
        $this->assertNotNull($route->last_published_at);
        $this->assertNull($route->last_error);
        $this->assertSame(1, IrrSubmission::query()->count());

        $submission = IrrSubmission::query()->first();
        $this->assertTrue($submission->successful);
        $this->assertSame('create', $submission->operation);
    }

    public function test_publish_marks_failed_when_body_reports_failure_despite_http_200(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'summary' => ['objects_found' => 1, 'successful' => 0, 'failed' => 1],
                'objects' => [
                    ['successful' => false, 'error_messages' => ['Invalid maintainer password']],
                ],
            ], 200),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->publish($maintainer, $route, 'route: 192.0.2.0/24', 'create');

        $route->refresh();

        $this->assertSame(IrrRoute::STATUS_FAILED, $route->status);
        $this->assertStringContainsString('Invalid maintainer password', (string) $route->last_error);

        $submission = IrrSubmission::query()->first();
        $this->assertFalse($submission->successful);
    }

    public function test_password_is_masked_in_stored_request_payload(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'objects' => [['successful' => true]],
            ], 200),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->publish($maintainer, $route, 'route: 192.0.2.0/24', 'create');

        $submission = IrrSubmission::query()->first();

        $this->assertSame(['***'], $submission->request_payload['passwords']);
        $this->assertStringNotContainsString('segredo-do-mntner', json_encode($submission->request_payload));
    }

    public function test_malformed_json_response_is_treated_as_failure(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response('bad request', 400, ['Content-Type' => 'text/plain']),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->publish($maintainer, $route, 'route: 192.0.2.0/24', 'create');

        $route->refresh();

        $this->assertSame(IrrRoute::STATUS_FAILED, $route->status);
        $this->assertNotNull($route->last_error);

        $submission = IrrSubmission::query()->first();
        $this->assertFalse($submission->successful);
    }

    public function test_connection_error_retries_once_and_then_marks_failed(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('Connection refused');
        });

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->publish($maintainer, $route, 'route: 192.0.2.0/24', 'create');

        $this->assertSame(2, $attempts, 'deve tentar uma vez e repetir mais uma (1 retry)');

        $route->refresh();
        $this->assertSame(IrrRoute::STATUS_FAILED, $route->status);

        $submission = IrrSubmission::query()->first();
        $this->assertFalse($submission->successful);
        $this->assertNull($submission->response_payload);
    }

    public function test_delete_sends_delete_reason_and_operation(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'objects' => [['successful' => true]],
            ], 200),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->delete($maintainer, $route, 'route: 192.0.2.0/24', 'Removido pelo IRCENTER');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request['delete_reason'] === 'Removido pelo IRCENTER';
        });

        $submission = IrrSubmission::query()->first();
        $this->assertSame(IrrSubmission::OPERATION_DELETE, $submission->operation);
    }

    public function test_successful_publish_syncs_the_manual_irr_object_catalog(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'objects' => [[
                    'successful' => true,
                    'new_object_text' => "route: 192.0.2.0/24\norigin: AS64500\nmnt-by: MAINT-AS64500\nsource: TC",
                ]],
            ], 200),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->publish($maintainer, $route, 'route: 192.0.2.0/24', 'create');

        $catalogEntry = IrrObject::query()
            ->where('object_type', 'route')
            ->where('object_key', '192.0.2.0/24')
            ->where('source', 'TC')
            ->first();

        $this->assertNotNull($catalogEntry, 'deveria criar/atualizar a entrada correspondente em irr_objects');
        $this->assertSame('MAINT-AS64500', $catalogEntry->maintainer);
        $this->assertTrue($catalogEntry->active);
        $this->assertStringContainsString('source: TC', $catalogEntry->raw_text);
        $this->assertNotNull($catalogEntry->last_synced_at);
    }

    public function test_delete_does_not_sync_the_manual_irr_object_catalog(): void
    {
        Http::fake([
            'bgp.net.br/*' => Http::response([
                'objects' => [['successful' => true]],
            ], 200),
        ]);

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->delete($maintainer, $route, 'route: 192.0.2.0/24', 'Removido pelo IRCENTER');

        $this->assertSame(0, IrrObject::query()->count());
    }

    public function test_never_calls_object_level_failure_more_than_once(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response([
                'objects' => [['successful' => false, 'error_messages' => ['x']]],
            ], 200);
        });

        $maintainer = $this->maintainer();
        $route = $this->route($maintainer);

        (new TcIrrClient)->publish($maintainer, $route, 'route: 192.0.2.0/24', 'create');

        $this->assertSame(1, $attempts, 'falha de objeto (200 com successful=false) nunca deve disparar retry');
    }
}
