<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        $totals = [
            'clients' => Client::query()->count(),
            'asns' => AutonomousSystem::query()->count(),
            'ipv4' => Prefix::query()
                ->where('ip_version', 4)
                ->count(),
            'ipv6' => Prefix::query()
                ->where('ip_version', 6)
                ->count(),
        ];

        $totalResources = array_sum($totals);

        $operationalIssues = collect([
            [
                'label' => 'Clientes sem ASN',
                'value' => Client::query()
                    ->where('active', true)
                    ->whereDoesntHave(
                        'autonomousSystems',
                        fn ($query) => $query->where('active', true)
                    )
                    ->count(),
                'description' => 'Clientes ativos sem ASN ativo vinculado',
                'icon' => 'clients',
                'tone' => 'cyan',
                'route' => route('clients.index'),
            ],
            [
                'label' => 'ASNs sem prefixos',
                'value' => AutonomousSystem::query()
                    ->where('active', true)
                    ->whereDoesntHave(
                        'prefixes',
                        fn ($query) => $query->where('active', true)
                    )
                    ->count(),
                'description' => 'ASNs ativos sem prefixos ativos',
                'icon' => 'asn',
                'tone' => 'violet',
                'route' => route('autonomous-systems.index'),
            ],
            [
                'label' => 'Prefixos inativos',
                'value' => Prefix::query()
                    ->where('active', false)
                    ->count(),
                'description' => 'Blocos desativados no inventário',
                'icon' => 'ipv4',
                'tone' => 'amber',
                'route' => route('prefixes.index', [
                    'status' => 'inactive',
                ]),
            ],
        ]);

        if ($user?->isAdministrator()) {
            $operationalIssues->push(
                [
                    'label' => 'Usuários bloqueados',
                    'value' => User::query()
                        ->where('active', false)
                        ->count(),
                    'description' => 'Contas sem acesso à plataforma',
                    'icon' => 'clients',
                    'tone' => 'red',
                    'route' => route('users.index', [
                        'status' => 'inactive',
                    ]),
                ],
                [
                    'label' => 'Troca de senha pendente',
                    'value' => User::query()
                        ->where('active', true)
                        ->where('must_change_password', true)
                        ->count(),
                    'description' => 'Usuários aguardando nova senha',
                    'icon' => 'shield',
                    'tone' => 'green',
                    'route' => route('users.index'),
                ],
            );
        }

        $recentAuditLogs = $user?->isAdministrator()
            ? AuditLog::query()
                ->with('user')
                ->latest()
                ->limit(8)
                ->get()
            : collect();

        return view('dashboard.index', [
            'totals' => $totals,
            'totalResources' => $totalResources,
            'distributionGradient' => $this->distributionGradient(
                $totals,
                $totalResources
            ),
            'metrics' => $this->metrics($totals),
            'operationalIssues' => $operationalIssues,
            'operationalIssueTotal' => $operationalIssues->sum('value'),
            'recentAuditLogs' => $recentAuditLogs,
            'isAdministrator' => $user?->isAdministrator() === true,
        ]);
    }

    /**
     * @param array{clients: int, asns: int, ipv4: int, ipv6: int} $totals
     * @return array<int, array<string, mixed>>
     */
    private function metrics(array $totals): array
    {
        return [
            [
                'label' => 'Clientes',
                'value' => $totals['clients'],
                'description' => 'Organizações cadastradas',
                'icon' => 'clients',
                'tone' => 'cyan',
                'state' => 'Total',
                'route' => route('clients.index'),
            ],
            [
                'label' => 'ASNs',
                'value' => $totals['asns'],
                'description' => 'Sistemas autônomos',
                'icon' => 'asn',
                'tone' => 'violet',
                'state' => 'Total',
                'route' => route('autonomous-systems.index'),
            ],
            [
                'label' => 'Prefixos IPv4',
                'value' => $totals['ipv4'],
                'description' => 'Blocos IPv4 gerenciados',
                'icon' => 'ipv4',
                'tone' => 'amber',
                'state' => 'Total',
                'route' => route('prefixes.ipv4'),
            ],
            [
                'label' => 'Prefixos IPv6',
                'value' => $totals['ipv6'],
                'description' => 'Blocos IPv6 gerenciados',
                'icon' => 'ipv6',
                'tone' => 'green',
                'state' => 'Total',
                'route' => route('prefixes.ipv6'),
            ],
        ];
    }

    /**
     * @param array{clients: int, asns: int, ipv4: int, ipv6: int} $totals
     */
    private function distributionGradient(
        array $totals,
        int $totalResources
    ): string {
        if ($totalResources === 0) {
            return 'conic-gradient(var(--border) 0 100%)';
        }

        $segments = [];
        $cursor = 0.0;

        foreach ([
            ['key' => 'clients', 'color' => 'var(--cyan)'],
            ['key' => 'asns', 'color' => 'var(--violet)'],
            ['key' => 'ipv4', 'color' => 'var(--amber)'],
            ['key' => 'ipv6', 'color' => 'var(--green)'],
        ] as $item) {
            $value = $totals[$item['key']];

            if ($value === 0) {
                continue;
            }

            $start = $cursor;
            $cursor += ($value / $totalResources) * 100;

            $segments[] = sprintf(
                '%s %.4f%% %.4f%%',
                $item['color'],
                $start,
                $cursor
            );
        }

        return 'conic-gradient('.implode(', ', $segments).')';
    }
}
