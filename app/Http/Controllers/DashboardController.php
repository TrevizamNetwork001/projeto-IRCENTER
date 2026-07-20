<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'metrics' => [
                'clients' => 0,
                'asns' => 0,
                'ipv4_prefixes' => 0,
                'ipv6_prefixes' => 0,
            ],
        ]);
    }
}
