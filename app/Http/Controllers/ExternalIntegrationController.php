<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExternalIntegrationRequest;
use App\Http\Requests\UpdateExternalIntegrationRequest;
use App\Jobs\TestExternalIntegration;
use App\Models\ExternalIntegration;
use App\Models\ExternalIntegrationRun;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExternalIntegrationController extends Controller
{
    public function index(): View
    {
        $this->authorizeAdministrator();

        return view('external-integrations.index', [
            'integrations' => ExternalIntegration::query()
                ->withCount('runs')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('external-integrations.create', [
            'integration' => new ExternalIntegration([
                'type' => ExternalIntegration::TYPE_WEBHOOK,
                'authentication_type' =>
                    ExternalIntegration::AUTH_NONE,
                'timeout_seconds' => 10,
                'active' => true,
            ]),
        ]);
    }

    public function store(
        StoreExternalIntegrationRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        $integration = ExternalIntegration::create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        app(AuditService::class)->record(
            'created',
            $integration,
            null,
            $this->auditValues($integration),
            $integration->name
        );

        return redirect()
            ->route('external-integrations.show', $integration)
            ->with('success', 'Integração cadastrada com sucesso.');
    }

    public function show(
        ExternalIntegration $externalIntegration
    ): View {
        $this->authorizeAdministrator();

        $externalIntegration->load([
            'creator',
            'updater',
            'runs' => fn ($query) => $query
                ->with('requester')
                ->latest()
                ->limit(30),
        ]);

        return view('external-integrations.show', [
            'integration' => $externalIntegration,
        ]);
    }

    public function edit(
        ExternalIntegration $externalIntegration
    ): View {
        $this->authorizeAdministrator();

        return view('external-integrations.edit', [
            'integration' => $externalIntegration,
        ]);
    }

    public function update(
        UpdateExternalIntegrationRequest $request,
        ExternalIntegration $externalIntegration
    ): RedirectResponse {
        $oldValues = $this->auditValues($externalIntegration);
        $data = $request->validated();

        if (
            $externalIntegration->authentication_type
                !== ExternalIntegration::AUTH_NONE
            && $data['authentication_type']
                !== ExternalIntegration::AUTH_NONE
            && $data['secret'] === null
        ) {
            unset($data['secret']);
        }

        $externalIntegration->update([
            ...$data,
            'updated_by_user_id' => $request->user()->id,
        ]);

        app(AuditService::class)->record(
            'updated',
            $externalIntegration,
            $oldValues,
            $this->auditValues($externalIntegration->fresh()),
            $externalIntegration->name
        );

        return redirect()
            ->route(
                'external-integrations.show',
                $externalIntegration
            )
            ->with('success', 'Integração atualizada com sucesso.');
    }

    public function test(
        ExternalIntegration $externalIntegration
    ): RedirectResponse {
        $this->authorizeAdministrator();

        abort_unless(
            $externalIntegration->active,
            409,
            'A integração está desativada.'
        );

        $run = $externalIntegration->runs()->create([
            'requested_by_user_id' => auth()->id(),
            'operation' => 'connectivity_test',
            'status' => ExternalIntegration::TEST_PENDING,
        ]);

        TestExternalIntegration::dispatch(
            $externalIntegration->id,
            $run->id
        );

        app(AuditService::class)->record(
            'integration_test_queued',
            $externalIntegration,
            null,
            [
                'run_id' => $run->id,
                'operation' => $run->operation,
            ],
            $externalIntegration->name
        );

        return back()->with(
            'success',
            'Teste de conectividade enviado para a fila.'
        );
    }

    private function auditValues(
        ExternalIntegration $integration
    ): array {
        return [
            'name' => $integration->name,
            'type' => $integration->type,
            'endpoint' => $integration->endpoint,
            'authentication_type' =>
                $integration->authentication_type,
            'username' => $integration->username,
            'has_secret' => $integration->secret !== null,
            'timeout_seconds' => $integration->timeout_seconds,
            'active' => $integration->active,
        ];
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
