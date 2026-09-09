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
use InvalidArgumentException;

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
        $asSet = IrrAsSet::query()->create($this->withDerivedClientId($request->validated()));

        return redirect()
            ->route('irr-as-sets.show', $asSet)
            ->with('success', 'AS-set cadastrado. Publique-o para enviar ao TC.');
    }

    public function show(IrrAsSet $irrAsSet, RpslBuilder $builder): View
    {
        $irrAsSet->load(['maintainer', 'submissions' => fn ($query) => $query->latest('id')]);

        [$rpslPreview, $rpslError] = $this->preview($irrAsSet, $builder);

        return view('irr-as-sets.show', [
            'asSet' => $irrAsSet,
            'rpslPreview' => $rpslPreview,
            'rpslError' => $rpslError,
        ]);
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
        $irrAsSet->update($this->withDerivedClientId($request->validated()));

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

        [$rpsl, $error] = $this->preview($irrAsSet, $builder);

        if ($error !== null) {
            return back()->with('error', 'Não foi possível gerar o RPSL: '.$error);
        }

        $operation = $irrAsSet->last_published_at === null
            ? IrrSubmission::OPERATION_CREATE
            : IrrSubmission::OPERATION_MODIFY;

        $client->publish($irrAsSet->maintainer, $irrAsSet, $rpsl, $operation);

        return $this->publicationRedirect($irrAsSet->fresh());
    }

    public function destroyRemote(IrrAsSet $irrAsSet, RpslBuilder $builder, TcIrrClient $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrAsSet->loadMissing('maintainer');

        [$rpsl, $error] = $this->preview($irrAsSet, $builder);

        if ($error !== null) {
            return back()->with('error', 'Não foi possível gerar o RPSL: '.$error);
        }

        $client->delete($irrAsSet->maintainer, $irrAsSet, $rpsl, 'Removido pelo IRCENTER');

        return back()->with('success', 'Solicitação de remoção enviada ao TC — acompanhe o resultado no histórico de submissões.');
    }

    /**
     * client_id nunca vem do formulário — é derivado do maintainer
     * escolhido. Ver o mesmo helper em IrrRouteController para o motivo
     * de usar IrrMaintainer::query() em vez de uma checagem direta na
     * tabela (fecha uma lacuna de Rule::exists não respeitar o escopo
     * por cliente do model).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withDerivedClientId(array $data): array
    {
        $maintainer = IrrMaintainer::query()->find($data['irr_maintainer_id'] ?? null);

        abort_unless($maintainer !== null, 404);

        $data['client_id'] = $maintainer->client_id;

        return $data;
    }

    /**
     * @return array{0: string|null, 1: string|null} RPSL gerado, ou erro se
     *     RpslBuilder recusar o objeto (campo obrigatório ausente, injeção).
     */
    private function preview(IrrAsSet $irrAsSet, RpslBuilder $builder): array
    {
        try {
            return [$builder->asSet($irrAsSet), null];
        } catch (InvalidArgumentException $exception) {
            return [null, $exception->getMessage()];
        }
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
