<?php

namespace App\Http\Controllers;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\IrrObject;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    private const RESULT_LIMIT = 8;

    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        $results = [
            'pages' => collect(),
            'clients' => collect(),
            'autonomousSystems' => collect(),
            'prefixes' => collect(),
            'irrObjects' => collect(),
            'incidents' => collect(),
        ];

        if ($query !== '') {
            $results = $this->search($query, $request);
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results,
            'totalResults' => collect($results)->sum(
                fn ($items): int => $items->count()
            ),
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));

        if (Str::length($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $results = $this->search($query, $request);
        $suggestions = collect();

        foreach ($results['pages'] as $page) {
            $suggestions->push([
                'title' => $page['title'],
                'detail' => $page['detail'],
                'type' => 'Página',
                'url' => route($page['route']),
            ]);
        }

        foreach ($results['clients'] as $client) {
            $suggestions->push([
                'title' => $client->displayName(),
                'detail' => collect([$client->client_code, $client->legal_name])->filter()->implode(' · '),
                'type' => 'Cliente',
                'url' => route('clients.show', $client),
            ]);
        }

        foreach ($results['autonomousSystems'] as $autonomousSystem) {
            $suggestions->push([
                'title' => $autonomousSystem->formattedAsn().' · '.$autonomousSystem->name,
                'detail' => $autonomousSystem->client?->displayName(),
                'type' => 'ASN',
                'url' => route('autonomous-systems.show', $autonomousSystem),
            ]);
        }

        foreach ($results['prefixes'] as $prefix) {
            $suggestions->push([
                'title' => $prefix->prefix,
                'detail' => collect([$prefix->autonomousSystem?->formattedAsn(), $prefix->client?->displayName()])->filter()->implode(' · '),
                'type' => 'Prefixo',
                'url' => route('prefixes.show', $prefix),
            ]);
        }

        foreach ($results['irrObjects'] as $irrObject) {
            $suggestions->push([
                'title' => $irrObject->object_key,
                'detail' => $irrObject->displayType().' · '.$irrObject->source,
                'type' => 'IRR',
                'url' => route('irr-objects.show', $irrObject),
            ]);
        }

        foreach ($results['incidents'] as $incident) {
            $suggestions->push([
                'title' => $incident->reference.' · '.$incident->title,
                'detail' => $incident->statusLabel(),
                'type' => 'Incidente',
                'url' => route('routing-incidents.show', $incident),
            ]);
        }

        return response()->json([
            'suggestions' => $suggestions->take(self::RESULT_LIMIT)->values(),
        ]);
    }

    /** @return array<string, \Illuminate\Support\Collection<int, mixed>> */
    private function search(string $query, Request $request): array
    {
        return [
            'pages' => $this->searchPages($query, $request),
            'clients' => Client::query()
                ->where(fn (Builder $builder) => $this->whereLike(
                    $builder,
                    ['legal_name', 'trade_name', 'client_code', 'contract_number', 'document', 'email', 'city'],
                    $query
                ))
                ->orderBy('trade_name')
                ->limit(self::RESULT_LIMIT)
                ->get(),

            'autonomousSystems' => AutonomousSystem::query()
                ->with('client')
                ->where(fn (Builder $builder) => $this->whereLike(
                    $builder,
                    ['asn', 'name', 'description', 'noc_contact', 'noc_email'],
                    $query
                ))
                ->orderBy('asn')
                ->limit(self::RESULT_LIMIT)
                ->get(),

            'prefixes' => Prefix::query()
                ->with(['client', 'autonomousSystem'])
                ->where(fn (Builder $builder) => $this->whereLike(
                    $builder,
                    ['prefix', 'description', 'purpose'],
                    $query
                ))
                ->orderBy('prefix')
                ->limit(self::RESULT_LIMIT)
                ->get(),

            'irrObjects' => IrrObject::query()
                ->with('client')
                ->where(fn (Builder $builder) => $this->whereLike(
                    $builder,
                    ['object_key', 'object_type', 'source', 'maintainer', 'description'],
                    $query
                ))
                ->orderBy('object_key')
                ->limit(self::RESULT_LIMIT)
                ->get(),

            'incidents' => RoutingIncident::query()
                ->with('client')
                ->where(fn (Builder $builder) => $this->whereLike(
                    $builder,
                    ['reference', 'title', 'summary', 'external_reference'],
                    $query
                ))
                ->latest('detected_at')
                ->limit(self::RESULT_LIMIT)
                ->get(),
        ];
    }

    private function searchPages(string $query, Request $request): \Illuminate\Support\Collection
    {
        $pages = collect([
            ['title' => 'Dashboard', 'detail' => 'Visão geral operacional', 'route' => 'dashboard', 'keywords' => 'inicio painel indicadores'],
            ['title' => 'Incidentes', 'detail' => 'Monitoramento e resposta a incidentes', 'route' => 'routing-incidents.index', 'keywords' => 'seguranca rotas alertas'],
            ['title' => 'Clientes', 'detail' => 'Organizações e contatos', 'route' => 'clients.index', 'keywords' => 'empresas contratos contatos'],
            ['title' => 'Financeiro', 'detail' => 'Contratos, faturas e cobranças', 'route' => 'finance.dashboard', 'keywords' => 'financeiro cobranca fatura boleto pix'],
            ['title' => 'Sistemas autônomos', 'detail' => 'Cadastro e gestão de ASNs', 'route' => 'autonomous-systems.index', 'keywords' => 'asn redes'],
            ['title' => 'Prefixos IPv4', 'detail' => 'Recursos de numeração IPv4', 'route' => 'prefixes.ipv4', 'keywords' => 'ip blocos cidr'],
            ['title' => 'Prefixos IPv6', 'detail' => 'Recursos de numeração IPv6', 'route' => 'prefixes.ipv6', 'keywords' => 'ip blocos cidr'],
            ['title' => 'Relatórios', 'detail' => 'Consultas e exportações', 'route' => 'reports.index', 'keywords' => 'csv dados'],
            ['title' => 'Assistente IRR', 'detail' => 'Fluxos assistidos de configuração', 'route' => 'irr-workflows.index', 'keywords' => 'routing registry rotas'],
            ['title' => 'Objetos IRR', 'detail' => 'Registros route, aut-num e maintainers', 'route' => 'irr-objects.index', 'keywords' => 'routing registry route aut-num maintainer'],
            ['title' => 'RPKI', 'detail' => 'Validação de origem e ROAs', 'route' => 'rpki.index', 'keywords' => 'roa rotas validacao'],
        ]);

        if (config('finance_fiscal.fiscal.enabled', false)) {
            $pages->push([
                'title' => 'Fiscal',
                'detail' => 'Documentos fiscais e configurações',
                'route' => 'fiscal.dashboard',
                'keywords' => 'nota nfe nfse impostos tributario emissao',
            ]);
        }

        if ($request->user()?->isAdministrator()) {
            $pages->push(
                ['title' => 'Integrações', 'detail' => 'Serviços e conexões externas', 'route' => 'external-integrations.index', 'keywords' => 'api servicos'],
                ['title' => 'Usuários', 'detail' => 'Contas, perfis e acessos', 'route' => 'users.index', 'keywords' => 'pessoas login permissoes'],
                ['title' => 'Diagnóstico', 'detail' => 'Saúde e configuração da plataforma', 'route' => 'system-diagnostic.index', 'keywords' => 'sistema saude status'],
                ['title' => 'Auditoria', 'detail' => 'Histórico de ações no sistema', 'route' => 'audit.index', 'keywords' => 'logs historico seguranca'],
            );
        }

        $needle = Str::of($query)->ascii()->lower()->toString();

        return $pages
            ->filter(function (array $page) use ($needle): bool {
                $haystack = Str::of(
                    $page['title'].' '.$page['detail'].' '.$page['keywords']
                )->ascii()->lower()->toString();

                return str_contains($haystack, $needle);
            })
            ->take(self::RESULT_LIMIT)
            ->values();
    }

    /** @param list<string> $columns */
    private function whereLike(
        Builder $builder,
        array $columns,
        string $query
    ): Builder {
        foreach ($columns as $index => $column) {
            $method = $index === 0 ? 'whereLike' : 'orWhereLike';
            $builder->{$method}($column, "%{$query}%", caseSensitive: false);
        }

        return $builder;
    }
}
