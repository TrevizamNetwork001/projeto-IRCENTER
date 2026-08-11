<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientContactController extends Controller
{
    public function create(Client $client): View
    {
        $this->authorizeAdministrator();

        return view('client-contacts.create', [
            'client' => $client,
            'contact' => new ClientContact([
                'active' => true,
                'is_primary' => false,
            ]),
        ]);
    }

    public function store(
        ClientContactRequest $request,
        Client $client
    ): RedirectResponse {
        $contact = $this->persist($client, null, $request->validated());

        $this->audit('client_contact.created', $contact);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Contato cadastrado com sucesso.');
    }

    public function edit(
        Client $client,
        ClientContact $contact
    ): View {
        $this->authorizeAdministrator();
        $this->ensureBelongsToClient($client, $contact);

        return view('client-contacts.edit', [
            'client' => $client,
            'contact' => $contact,
        ]);
    }

    public function update(
        ClientContactRequest $request,
        Client $client,
        ClientContact $contact
    ): RedirectResponse {
        $this->ensureBelongsToClient($client, $contact);
        $oldState = $this->auditState($contact);
        $wasActive = $contact->active;
        $wasPrimary = $contact->is_primary;

        $contact = $this->persist(
            $client,
            $contact,
            $request->validated()
        );

        if ($wasActive !== $contact->active) {
            $action = $contact->active
                ? 'client_contact.activated'
                : 'client_contact.deactivated';
        } elseif (
            $wasPrimary !== $contact->is_primary
            || (
                $oldState['type'] !== $contact->type
                && $contact->is_primary
            )
        ) {
            $action = 'client_contact.primary_changed';
        } else {
            $action = 'client_contact.updated';
        }

        $this->audit($action, $contact, $oldState);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Contato atualizado com sucesso.');
    }

    public function toggleActive(
        Client $client,
        ClientContact $contact
    ): RedirectResponse {
        $this->authorizeAdministrator();
        $this->ensureBelongsToClient($client, $contact);
        $oldState = $this->auditState($contact);

        DB::transaction(function () use ($client, $contact): void {
            Client::query()->lockForUpdate()->findOrFail($client->id);
            $contact->refresh();
            $contact->update([
                'active' => ! $contact->active,
                'is_primary' => false,
            ]);
        });

        $contact->refresh();
        $this->audit(
            $contact->active
                ? 'client_contact.activated'
                : 'client_contact.deactivated',
            $contact,
            $oldState
        );

        return back()->with(
            'success',
            $contact->active
                ? 'Contato ativado com sucesso.'
                : 'Contato desativado com sucesso.'
        );
    }

    public function togglePrimary(
        Client $client,
        ClientContact $contact
    ): RedirectResponse {
        $this->authorizeAdministrator();
        $this->ensureBelongsToClient($client, $contact);

        if (! $contact->active) {
            return back()->withErrors([
                'contact' => 'Ative o contato antes de marcá-lo como principal.',
            ]);
        }

        $oldState = $this->auditState($contact);

        DB::transaction(function () use ($client, $contact): void {
            Client::query()->lockForUpdate()->findOrFail($client->id);
            $contact->refresh();

            if (! $contact->is_primary) {
                ClientContact::query()
                    ->where('client_id', $client->id)
                    ->where('type', $contact->type)
                    ->whereKeyNot($contact->id)
                    ->update(['is_primary' => false]);
            }

            $contact->update([
                'is_primary' => ! $contact->is_primary,
            ]);
        });

        $contact->refresh();
        $this->audit(
            'client_contact.primary_changed',
            $contact,
            $oldState
        );

        return back()->with(
            'success',
            $contact->is_primary
                ? 'Contato marcado como principal.'
                : 'Contato desmarcado como principal.'
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persist(
        Client $client,
        ?ClientContact $contact,
        array $data
    ): ClientContact {
        return DB::transaction(function () use (
            $client,
            $contact,
            $data
        ): ClientContact {
            Client::query()->lockForUpdate()->findOrFail($client->id);

            if (! $data['active']) {
                $data['is_primary'] = false;
            }

            if ($data['is_primary']) {
                ClientContact::query()
                    ->where('client_id', $client->id)
                    ->where('type', $data['type'])
                    ->when(
                        $contact !== null,
                        fn ($query) => $query->whereKeyNot($contact->id)
                    )
                    ->update(['is_primary' => false]);
            }

            if ($contact === null) {
                return $client->contacts()->create($data);
            }

            $contact->update($data);

            return $contact->refresh();
        });
    }

    private function ensureBelongsToClient(
        Client $client,
        ClientContact $contact
    ): void {
        abort_unless($contact->client_id === $client->id, 404);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(ClientContact $contact): array
    {
        return [
            'client_id' => $contact->client_id,
            'type' => $contact->type,
            'active' => $contact->active,
            'is_primary' => $contact->is_primary,
        ];
    }

    /**
     * @param array<string, mixed>|null $oldState
     */
    private function audit(
        string $action,
        ClientContact $contact,
        ?array $oldState = null
    ): void {
        app(AuditService::class)->record(
            $action,
            $contact,
            $oldState,
            $this->auditState($contact),
            'Contato '.$contact->type.' do cliente #'.$contact->client_id
        );
    }
}
