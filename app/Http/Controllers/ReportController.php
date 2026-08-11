<?php

namespace App\Http\Controllers;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use App\Services\AuditService;
use App\Support\CsvCellSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $incidentQuery = $this->incidentQuery($filters);

        $incidentTotals = [
            'total' => (clone $incidentQuery)->count(),
            'open' => (clone $incidentQuery)
                ->whereNotIn('status', [
                    RoutingIncident::STATUS_RESOLVED,
                    RoutingIncident::STATUS_CLOSED,
                ])
                ->count(),
            'critical' => (clone $incidentQuery)
                ->where(
                    'severity',
                    RoutingIncident::SEVERITY_CRITICAL
                )
                ->count(),
            'resolved' => (clone $incidentQuery)
                ->whereIn('status', [
                    RoutingIncident::STATUS_RESOLVED,
                    RoutingIncident::STATUS_CLOSED,
                ])
                ->count(),
        ];

        return view('reports.index', [
            'filters' => $filters,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'resourceTotals' => [
                'clients' => Client::query()->count(),
                'asns' => AutonomousSystem::query()->count(),
                'ipv4' => Prefix::query()
                    ->where('ip_version', 4)
                    ->count(),
                'ipv6' => Prefix::query()
                    ->where('ip_version', 6)
                    ->count(),
            ],
            'incidentTotals' => $incidentTotals,
            'recentIncidents' => $incidentQuery
                ->with([
                    'client',
                    'autonomousSystem',
                    'prefix',
                ])
                ->latest('detected_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function export(
        Request $request,
        string $report
    ): StreamedResponse {
        abort_unless(
            in_array($report, [
                'clients',
                'autonomous-systems',
                'prefixes',
                'incidents',
            ], true),
            404
        );

        $filters = $this->filters($request);
        [$headers, $rows, $label] = $this->exportDefinition(
            $report,
            $filters
        );

        app(AuditService::class)->record(
            'exported_report',
            $request->user(),
            null,
            [
                'report' => $report,
                'filters' => $filters,
                'format' => 'csv',
            ],
            'Relatório: '.$label
        );

        $filename = sprintf(
            'ircenter-%s-%s.csv',
            $report,
            now()->format('Ymd-His')
        );

        return response()->streamDownload(
            function () use ($headers, $rows): void {
                $output = fopen('php://output', 'wb');
                $sanitizer = app(CsvCellSanitizer::class);

                if ($output === false) {
                    return;
                }

                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers, ';');

                foreach ($rows as $row) {
                    fputcsv($output, $sanitizer->sanitizeRow($row), ';');
                }

                fclose($output);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $severity = trim((string) $request->query('severity', ''));
        $type = trim((string) $request->query('type', ''));

        return [
            'client' => max(0, $request->integer('client')),
            'status' => in_array(
                $status,
                RoutingIncident::statuses(),
                true
            ) ? $status : '',
            'severity' => in_array(
                $severity,
                RoutingIncident::severities(),
                true
            ) ? $severity : '',
            'type' => in_array(
                $type,
                RoutingIncident::types(),
                true
            ) ? $type : '',
            'date_from' => $this->dateFilter(
                $request->query('date_from')
            ),
            'date_to' => $this->dateFilter(
                $request->query('date_to')
            ),
        ];
    }

    private function dateFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (
            $value === ''
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
        ) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function incidentQuery(array $filters): Builder
    {
        return RoutingIncident::query()
            ->when(
                $filters['client'] > 0,
                fn (Builder $query) => $query->where(
                    'client_id',
                    $filters['client']
                )
            )
            ->when(
                $filters['status'] !== '',
                fn (Builder $query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->when(
                $filters['severity'] !== '',
                fn (Builder $query) => $query->where(
                    'severity',
                    $filters['severity']
                )
            )
            ->when(
                $filters['type'] !== '',
                fn (Builder $query) => $query->where(
                    'type',
                    $filters['type']
                )
            )
            ->when(
                $filters['date_from'] !== null,
                fn (Builder $query) => $query->whereDate(
                    'detected_at',
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                $filters['date_to'] !== null,
                fn (Builder $query) => $query->whereDate(
                    'detected_at',
                    '<=',
                    $filters['date_to']
                )
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: array<int, string>, 1: iterable, 2: string}
     */
    private function exportDefinition(
        string $report,
        array $filters
    ): array {
        return match ($report) {
            'clients' => $this->clientsExport(),
            'autonomous-systems' => $this->asnsExport($filters),
            'prefixes' => $this->prefixesExport($filters),
            'incidents' => $this->incidentsExport($filters),
        };
    }

    /**
     * @return array{0: array<int, string>, 1: iterable, 2: string}
     */
    private function clientsExport(): array
    {
        $rows = Client::query()
            ->withCount([
                'autonomousSystems',
                'prefixes',
            ])
            ->orderBy('legal_name')
            ->cursor()
            ->map(fn (Client $client): array => [
                $client->client_code,
                $client->contract_number,
                $client->legal_name,
                $client->trade_name,
                $client->document,
                $client->email,
                $client->phone,
                $client->city,
                $client->state,
                $client->country,
                $client->active ? 'Ativo' : 'Inativo',
                $client->autonomous_systems_count,
                $client->prefixes_count,
            ]);

        return [
            [
                'Código',
                'Contrato',
                'Razão social',
                'Nome fantasia',
                'Documento',
                'E-mail',
                'Telefone',
                'Cidade',
                'Estado',
                'País',
                'Status',
                'ASNs',
                'Prefixos',
            ],
            $rows,
            'Clientes',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: array<int, string>, 1: iterable, 2: string}
     */
    private function asnsExport(array $filters): array
    {
        $rows = AutonomousSystem::query()
            ->with('client')
            ->withCount('prefixes')
            ->when(
                $filters['client'] > 0,
                fn (Builder $query) => $query->where(
                    'client_id',
                    $filters['client']
                )
            )
            ->orderBy('asn')
            ->cursor()
            ->map(fn (AutonomousSystem $asn): array => [
                $asn->formattedAsn(),
                $asn->name,
                $asn->client?->displayName(),
                $asn->rir,
                $asn->country,
                $asn->noc_contact,
                $asn->noc_email,
                $asn->active ? 'Ativo' : 'Inativo',
                $asn->prefixes_count,
            ]);

        return [
            [
                'ASN',
                'Nome',
                'Cliente',
                'RIR',
                'País',
                'Contato NOC',
                'E-mail NOC',
                'Status',
                'Prefixos',
            ],
            $rows,
            'ASNs',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: array<int, string>, 1: iterable, 2: string}
     */
    private function prefixesExport(array $filters): array
    {
        $rows = Prefix::query()
            ->with([
                'client',
                'autonomousSystem',
            ])
            ->when(
                $filters['client'] > 0,
                fn (Builder $query) => $query->where(
                    'client_id',
                    $filters['client']
                )
            )
            ->orderBy('ip_version')
            ->orderBy('prefix')
            ->cursor()
            ->map(fn (Prefix $prefix): array => [
                $prefix->prefix,
                'IPv'.$prefix->ip_version,
                $prefix->client?->displayName(),
                $prefix->autonomousSystem?->formattedAsn(),
                $prefix->description,
                $prefix->purpose,
                $prefix->allocation_status,
                $prefix->rir,
                $prefix->country,
                $prefix->active ? 'Ativo' : 'Inativo',
            ]);

        return [
            [
                'Prefixo',
                'Versão',
                'Cliente',
                'ASN',
                'Descrição',
                'Finalidade',
                'Alocação',
                'RIR',
                'País',
                'Status',
            ],
            $rows,
            'Prefixos',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: array<int, string>, 1: iterable, 2: string}
     */
    private function incidentsExport(array $filters): array
    {
        $rows = $this->incidentQuery($filters)
            ->with([
                'client',
                'autonomousSystem',
                'prefix',
                'reporter',
                'assignee',
            ])
            ->latest('detected_at')
            ->cursor()
            ->map(fn (RoutingIncident $incident): array => [
                $incident->reference,
                $incident->title,
                $incident->typeLabel(),
                $incident->severityLabel(),
                $incident->statusLabel(),
                $incident->client?->displayName(),
                $incident->autonomousSystem?->formattedAsn(),
                $incident->prefix?->prefix,
                $incident->source,
                $incident->reporter?->name,
                $incident->assignee?->name,
                $incident->detected_at?->format('d/m/Y H:i:s'),
                $incident->acknowledged_at?->format('d/m/Y H:i:s'),
                $incident->resolved_at?->format('d/m/Y H:i:s'),
                $incident->closed_at?->format('d/m/Y H:i:s'),
                $incident->summary,
                $incident->impact,
                $incident->mitigation,
                $incident->root_cause,
                $incident->external_reference,
            ]);

        return [
            [
                'Referência',
                'Título',
                'Tipo',
                'Severidade',
                'Status',
                'Cliente',
                'ASN',
                'Prefixo',
                'Origem',
                'Registrado por',
                'Responsável',
                'Detectado em',
                'Reconhecido em',
                'Resolvido em',
                'Encerrado em',
                'Resumo',
                'Impacto',
                'Mitigação',
                'Causa raiz',
                'Referência externa',
            ],
            $rows,
            'Incidentes',
        ];
    }
}
