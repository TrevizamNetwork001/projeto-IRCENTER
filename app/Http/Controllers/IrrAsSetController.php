<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIrrAsSetRequest;
use App\Http\Requests\UpdateIrrAsSetRequest;
use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrSubmission;
use App\Services\Irr\RpslBuilder;
use App\Services\Irr\TcIrrClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IrrAsSetController extends Controller
{
    public function index(): View
    {
        return view('irr-as-sets.index', [
            'asSets' => IrrAsSet::query()
                ->with('maintainer')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('irr-as-sets.create', [
            'asSet' => new IrrAsSet,
            'maintainers' => IrrMaintainer::query()->orderBy('mntner')->get(),
        ]);
    }

    public function store(StoreIrrAsSetRequest $request): RedirectResponse
    {
        $asSet = IrrAsSet::query()->create($request->validated());

        return redirect()
            ->route('irr-as-sets.show', $asSet)
            ->with('success', 'AS-set cadastrado. Publique-o para enviar ao TC.');
    }

    public function show(IrrAsSet $irrAsSet): View
    {
        $irrAsSet->load(['maintainer', 'submissions' => fn ($query) => $query->latest('id')]);

        return view('irr-as-sets.show', ['asSet' => $irrAsSet]);
    }

    public function edit(IrrAsSet $irrAsSet): View
    {
        $this->authorizeAdministrator();

        return view('irr-as-sets.edit', [
            'asSet' => $irrAsSet,
            'maintainers' => IrrMaintainer::query()->orderBy('mntner')->get(),
        ]);
    }

    public function update(UpdateIrrAsSetRequest $request, IrrAsSet $irrAsSet): RedirectResponse
    {
        $irrAsSet->update($request->validated());

        return redirect()
            ->route('irr-as-sets.show', $irrAsSet)
            ->with('success', 'AS-set atualizado. Publique novamente para refletir a alteração no TC.');
    }

    public function destroy(IrrAsSet $irrAsSet): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrAsSet->delete();

        return redirect()
            ->route('irr-as-sets.index')
            ->with('success', 'AS-set removido do IRCENTER.');
    }

    public function publish(IrrAsSet $irrAsSet, RpslBuilder $builder, TcIrrClient $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrAsSet->loadMissing('maintainer');

        $operation = $irrAsSet->last_published_at === null
            ? IrrSubmission::OPERATION_CREATE
            : IrrSubmission::OPERATION_MODIFY;

        $client->publish($irrAsSet->maintainer, $irrAsSet, $builder->asSet($irrAsSet), $operation);

        return $this->publicationRedirect($irrAsSet->fresh());
    }

    public function destroyRemote(IrrAsSet $irrAsSet, RpslBuilder $builder, TcIrrClient $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrAsSet->loadMissing('maintainer');

        $client->delete($irrAsSet->maintainer, $irrAsSet, $builder->asSet($irrAsSet), 'Removido pelo IRCENTER');

        return back()->with('success', 'Solicitação de remoção enviada ao TC — acompanhe o resultado no histórico de submissões.');
    }

    private function publicationRedirect(IrrAsSet $irrAsSet): RedirectResponse
    {
        if ($irrAsSet->status === IrrAsSet::STATUS_PUBLISHED) {
            return back()->with('success', 'AS-set publicado no TC com sucesso.');
        }

        return back()->with('error', 'Falha ao publicar no TC: '.$irrAsSet->last_error);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator() === true, 403);
    }
}
