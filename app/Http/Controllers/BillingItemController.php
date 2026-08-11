<?php

namespace App\Http\Controllers;

use App\Modules\Finance\Models\BillingItem;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BillingItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $items = BillingItem::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->orderByDesc('active')->orderBy('name')->paginate(20)->withQueryString();

        return view('finance.items.index', compact('items', 'search'));
    }

    public function store(Request $request, DomainAudit $audit): RedirectResponse
    {
        $this->authorizeWrite();
        $item = BillingItem::query()->create($this->validated($request));
        $audit->record('finance', 'billing_item.created', auth()->id(), 'billing_item', $item->id, ['name' => $item->name]);
        return back()->with('success', 'Item de cobrança criado.');
    }

    public function update(Request $request, BillingItem $billingItem, DomainAudit $audit): RedirectResponse
    {
        $this->authorizeWrite();
        $billingItem->update($this->validated($request));
        $audit->record('finance', 'billing_item.updated', auth()->id(), 'billing_item', $billingItem->id, ['name' => $billingItem->name]);
        return back()->with('success', 'Item de cobrança atualizado.');
    }

    public function toggle(BillingItem $billingItem, DomainAudit $audit): RedirectResponse
    {
        $this->authorizeWrite();
        $billingItem->update(['active' => ! $billingItem->active]);
        $audit->record('finance', 'billing_item.status_changed', auth()->id(), 'billing_item', $billingItem->id, ['active' => $billingItem->active]);
        return back()->with('success', $billingItem->active ? 'Item ativado.' : 'Item inativado.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['default_amount' => str_replace(',', '.', trim((string) $request->input('default_amount')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        return $data;
    }

    private function authorizeWrite(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        abort_unless(config('finance_fiscal.finance.enabled'), 503);
    }
}
