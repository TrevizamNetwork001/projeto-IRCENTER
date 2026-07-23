<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Prefix;
use App\Models\RpkiValidation;
use App\Services\RpkiValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RpkiValidationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $version = $request->integer('version');
        $clientId = $request->integer('client');

        if (! in_array($version, [4, 6], true)) {
            $version = 0;
        }

        $prefixes = Prefix::query()
            ->with([
                'client',
                'autonomousSystem',
                'latestRpkiValidation.roa',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('prefix', 'ilike', "%{$search}%")
                            ->orWhere('description', 'ilike', "%{$search}%")
                            ->orWhereHas(
                                'client',
                                function ($query) use ($search): void {
                                    $query
                                        ->where(
                                            'legal_name',
                                            'ilike',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'trade_name',
                                            'ilike',
                                            "%{$search}%"
                                        );
                                }
                            )
                            ->orWhereHas(
                                'autonomousSystem',
                                function ($query) use ($search): void {
                                    $asn = preg_replace(
                                        '/^AS/i',
                                        '',
                                        $search
                                    );

                                    $query->where(
                                        'name',
                                        'ilike',
                                        "%{$search}%"
                                    );

                                    if (ctype_digit((string) $asn)) {
                                        $query->orWhere('asn', (int) $asn);
                                    }
                                }
                            );
                    });
                }
            )
            ->when(
                $version > 0,
                fn ($query) => $query->where('ip_version', $version)
            )
            ->when(
                $clientId > 0,
                fn ($query) => $query->where('client_id', $clientId)
            )
            ->when(
                $status === 'unchecked',
                fn ($query) => $query->doesntHave('rpkiValidations')
            )
            ->when(
                in_array(
                    $status,
                    [
                        RpkiValidation::STATUS_VALID,
                        RpkiValidation::STATUS_INVALID,
                        RpkiValidation::STATUS_NOT_FOUND,
                        RpkiValidation::STATUS_ERROR,
                    ],
                    true
                ),
                fn ($query) => $query->whereHas(
                    'latestRpkiValidation',
                    fn ($query) => $query->where('status', $status)
                )
            )
            ->orderBy('ip_version')
            ->orderBy('prefix')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total' => Prefix::query()->count(),
            'valid' => $this->latestStatusCount(
                RpkiValidation::STATUS_VALID
            ),
            'invalid' => $this->latestStatusCount(
                RpkiValidation::STATUS_INVALID
            ),
            'not_found' => $this->latestStatusCount(
                RpkiValidation::STATUS_NOT_FOUND
            ),
            'error' => $this->latestStatusCount(
                RpkiValidation::STATUS_ERROR
            ),
            'unchecked' => Prefix::query()
                ->doesntHave('rpkiValidations')
                ->count(),
        ];

        return view('rpki.index', [
            'prefixes' => $prefixes,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'summary' => $summary,
            'search' => $search,
            'status' => $status,
            'version' => $version,
            'clientId' => $clientId,
        ]);
    }

    public function store(
        Prefix $prefix,
        RpkiValidationService $service
    ): RedirectResponse {
        $this->authorizeAdministrator();

        $validation = $service->validate($prefix);

        return back()->with(
            'success',
            'Validação RPKI concluída: '
            .$validation->displayStatus().'.'
        );
    }

    public function history(Prefix $prefix): View
    {
        $prefix->load([
            'client',
            'autonomousSystem',
        ]);

        $validations = $prefix->rpkiValidations()
            ->with('roa')
            ->latest('checked_at')
            ->paginate(30);

        return view('rpki.history', [
            'prefix' => $prefix,
            'validations' => $validations,
        ]);
    }

    private function latestStatusCount(string $status): int
    {
        return Prefix::query()
            ->whereHas(
                'latestRpkiValidation',
                fn ($query) => $query->where('status', $status)
            )
            ->count();
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
