<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Invoice;
use Illuminate\View\View;

final class InvoiceController extends Controller
{
    public function index(): View
    {
        $invoices = Invoice::query()
            ->orderByDesc('due_on')
            ->paginate(20);

        return view('portal.invoices.index', [
            'invoices' => $invoices,
        ]);
    }
}
