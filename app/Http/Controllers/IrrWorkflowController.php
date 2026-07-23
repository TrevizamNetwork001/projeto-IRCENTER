<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIrrWorkflowRequest;
use App\Http\Requests\UpdateIrrWorkflowStepRequest;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\IrrWorkflow;
use App\Models\IrrWorkflowStep;
use App\Services\IrrWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IrrWorkflowController extends Controller
{
    public function index(): View
    {
        return view('irr-workflows.index', [
            'workflows' => IrrWorkflow::query()
                ->with(['client', 'autonomousSystem'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('irr-workflows.create', [
            'clients' => Client::query()
                ->where('active', true)
                ->orderBy('legal_name')
                ->get(),
            'autonomousSystems' => AutonomousSystem::query()
                ->with('client')
                ->where('active', true)
                ->orderBy('asn')
                ->get(),
        ]);
    }

    public function store(
        StoreIrrWorkflowRequest $request,
        IrrWorkflowService $service
    ): RedirectResponse {
        $workflow = $service->create($request->validated());

        return redirect()
            ->route('irr-workflows.show', $workflow)
            ->with('success', 'Assistente IRR iniciado com sucesso.');
    }

    public function show(IrrWorkflow $irrWorkflow): View
    {
        $irrWorkflow->load([
            'client',
            'autonomousSystem',
            'autonomousSystem.prefixes',
            'steps',
        ]);

        return view('irr-workflows.show', [
            'workflow' => $irrWorkflow,
        ]);
    }

    public function markSent(
        UpdateIrrWorkflowStepRequest $request,
        IrrWorkflow $irrWorkflow,
        IrrWorkflowStep $step,
        IrrWorkflowService $service
    ): RedirectResponse {
        $service->markSent(
            $irrWorkflow,
            $step,
            $request->validated('operator_notes')
        );

        return back()->with(
            'success',
            'Etapa marcada como enviada.'
        );
    }

    public function confirm(
        UpdateIrrWorkflowStepRequest $request,
        IrrWorkflow $irrWorkflow,
        IrrWorkflowStep $step,
        IrrWorkflowService $service
    ): RedirectResponse {
        $service->confirm(
            $irrWorkflow,
            $step,
            $request->validated('operator_notes')
        );

        return back()->with(
            'success',
            'Etapa confirmada. A próxima etapa foi liberada.'
        );
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
