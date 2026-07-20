<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'metrics' => [
                [
                    'label' => 'Clientes',
                    'value' => 0,
                    'description' => 'Organizações cadastradas',
                    'icon' => 'clients',
                    'tone' => 'cyan',
                    'state' => 'Ativo',
                ],
                [
                    'label' => 'ASNs',
                    'value' => 0,
                    'description' => 'Sistemas autônomos',
                    'icon' => 'asn',
                    'tone' => 'violet',
                    'state' => 'Ativo',
                ],
                [
                    'label' => 'Prefixos IPv4',
                    'value' => 0,
                    'description' => 'Blocos IPv4 gerenciados',
                    'icon' => 'ipv4',
                    'tone' => 'amber',
                    'state' => 'Ativo',
                ],
                [
                    'label' => 'Prefixos IPv6',
                    'value' => 0,
                    'description' => 'Blocos IPv6 gerenciados',
                    'icon' => 'ipv6',
                    'tone' => 'green',
                    'state' => 'Ativo',
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
}
