<?php

namespace App\Http\Controllers;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totals = [
            'clients' => Client::query()->count(),
            'asns' => AutonomousSystem::query()->count(),
            'ipv4' => Prefix::query()->where('ip_version', 4)->count(),
            'ipv6' => Prefix::query()->where('ip_version', 6)->count(),
        ];

        $totalResources = array_sum($totals);

        return view('dashboard.index', [
            'totals' => $totals,
            'totalResources' => $totalResources,
            'distributionGradient' => $this->distributionGradient(
                $totals,
                $totalResources
            ),
            'metrics' => [
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
            ],
            'health' => [
                [
                    'label' => 'Aplicação',
                    'description' => 'Laravel operacional',
                    'status' => 'Online',
                    'icon' => 'activity',
                ],
                [
                    'label' => 'Banco de dados',
                    'description' => 'PostgreSQL conectado',
                    'status' => 'Online',
                    'icon' => 'database',
                ],
                [
                    'label' => 'Segurança',
                    'description' => 'Autenticação protegida',
                    'status' => 'Ativa',
                    'icon' => 'shield',
                ],
            ],
        ]);
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
