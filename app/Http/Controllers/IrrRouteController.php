<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIrrRouteRequest;
use App\Http\Requests\UpdateIrrRouteRequest;
use App\Models\IrrMaintainer;
use App\Models\IrrObject;
use App\Models\IrrRoute;
use App\Models\IrrSubmission;
use App\Services\Irr\RpslBuilder;
use App\Services\Irr\TcIrrClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class IrrRouteController extends Controller
{
    public function index(): View
    {
        return view('irr-routes.index', [
            'routes' => IrrRoute::query()
                ->with('maintainer')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('irr-routes.create', [
            'route' => new IrrRoute(['version' => 4]),
            'maintainers' => IrrMaintainer::query()->orderBy('mntner')->get(),
        ]);
    }

    public function store(StoreIrrRouteRequest $request): RedirectResponse
    {
        $route = IrrRoute::query()->create($this->withDerivedClientId($request->validated()));

        $redirect = redirect()
            ->route('irr-routes.show', $route)
            ->with('success', 'Objeto route cadastrado. Publique-o para enviar ao TC.');

        return $this->withConflictWarning($redirect, $route->prefix);
    }

    public function show(IrrRoute $irrRoute, RpslBuilder $builder): View
    {
        $irrRoute->load(['maintainer', 'submissions' => fn ($query) => $query->latest('id')]);

        [$rpslPreview, $rpslError] = $this->preview($irrRoute, $builder);

        return view('irr-routes.show', [
            'route' => $irrRoute,
            'rpslPreview' => $rpslPreview,
            'rpslError' => $rpslError,
        ]);
    }

    public function edit(IrrRoute $irrRoute): View
    {
        $this->authorizeAdministrator();

        return view('irr-routes.edit', [
            'route' => $irrRoute,
            'maintainers' => IrrMaintainer::query()->orderBy('mntner')->get(),
        ]);
    }

    public function update(UpdateIrrRouteRequest $request, IrrRoute $irrRoute): RedirectResponse
    {
        $irrRoute->update($this->withDerivedClientId($request->validated()));

        $redirect = redirect()
            ->route('irr-routes.show', $irrRoute)
            ->with('success', 'Objeto route atualizado. Publique novamente para refletir a alteração no TC.');

        return $this->withConflictWarning($redirect, $irrRoute->prefix);
    }

    public function destroy(IrrRoute $irrRoute): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrRoute->delete();

        return redirect()
            ->route('irr-routes.index')
            ->with('success', 'Objeto route removido do IRCENTER.');
    }

    public function publish(IrrRoute $irrRoute, RpslBuilder $builder, TcIrrClient $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrRoute->loadMissing('maintainer');

        [$rpsl, $error] = $this->preview($irrRoute, $builder);

        if ($error !== null) {
            return back()->with('error', 'Não foi possível gerar o RPSL: '.$error);
        }

        $operation = $irrRoute->last_published_at === null
            ? IrrSubmission::OPERATION_CREATE
            : IrrSubmission::OPERATION_MODIFY;

        $client->publish($irrRoute->maintainer, $irrRoute, $rpsl, $operation);

        return $this->publicationRedirect($irrRoute->fresh());
    }

    public function destroyRemote(IrrRoute $irrRoute, RpslBuilder $builder, TcIrrClient $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrRoute->loadMissing('maintainer');

        [$rpsl, $error] = $this->preview($irrRoute, $builder);

        if ($error !== null) {
            return back()->with('error', 'Não foi possível gerar o RPSL: '.$error);
        }

        $client->delete($irrRoute->maintainer, $irrRoute, $rpsl, 'Removido pelo IRCENTER');

        return back()->with('success', 'Solicitação de remoção enviada ao TC — acompanhe o resultado no histórico de submissões.');
    }

    /**
     * client_id nunca vem do formulário — é derivado do maintainer
     * escolhido, para o isolamento por cliente (BelongsToClient) ficar
     * consistente com quem é dono de fato do objeto. Usar
     * IrrMaintainer::query() (em vez de DB::table) também fecha, de
     * quebra, uma lacuna do Rule::exists do FormRequest: ele confirma que
     * a linha existe na tabela, mas não respeita o escopo por cliente do
     * model — aqui sim, então um funcionário restrito que tentar forjar
     * o id de um maintainer de outro cliente recebe 404 em vez de
     * conseguir vincular o objeto a esse maintainer.
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
    private function preview(IrrRoute $irrRoute, RpslBuilder $builder): array
    {
        try {
            return [$builder->route($irrRoute), null];
        } catch (InvalidArgumentException $exception) {
            return [null, $exception->getMessage()];
        }
    }

    /**
     * Apenas avisa — o catálogo manual (irr_objects) e a publicação
     * automática (irr_routes) são cadastros independentes; um mesmo
     * prefixo existir nos dois não impede o cadastro, mas costuma
     * indicar duplicidade que vale a pena revisar.
     */
    private function withConflictWarning(RedirectResponse $redirect, string $prefix): RedirectResponse
    {
        $exists = IrrObject::query()
            ->where('object_key', $prefix)
            ->where('active', true)
            ->exists();

        if (! $exists) {
            return $redirect;
        }

        return $redirect->with(
            'warning',
            "Atenção: já existe um objeto IRR ativo no catálogo manual com a chave {$prefix}. Confira se não há duplicidade entre os dois cadastros."
        );
    }

    private function publicationRedirect(IrrRoute $irrRoute): RedirectResponse
    {
        if ($irrRoute->status === IrrRoute::STATUS_PUBLISHED) {
            return back()->with('success', 'Objeto publicado no TC com sucesso.');
        }

        return back()->with('error', 'Falha ao publicar no TC: '.$irrRoute->last_error);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator() === true, 403);
    }
}
