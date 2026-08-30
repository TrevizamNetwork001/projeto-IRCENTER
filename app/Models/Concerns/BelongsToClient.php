<?php

namespace App\Models\Concerns;

use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe automaticamente as queries do model a um único cliente,
 * cobrindo os dois casos de acesso restrito do sistema:
 *
 * - staff interno (guard "web") com `client_id` preenchido;
 * - contato de cliente logado no portal (guard "client").
 *
 * Sem nenhum dos dois casos (staff irrestrito, comportamento atual), a
 * query não é filtrada — nenhum comportamento existente muda.
 *
 * Por padrão assume que a coluna que referencia `clients.id` se chama
 * "client_id". Models cuja coluna tem outro nome (ex: "core_client_id")
 * devem sobrescrever `clientForeignKeyColumn()`.
 */
trait BelongsToClient
{
    protected static function bootBelongsToClient(): void
    {
        static::addGlobalScope('client', function (Builder $query): void {
            $clientId = static::resolveScopedClientId();

            if ($clientId === null) {
                return;
            }

            $query->where(
                $query->getModel()->clientForeignKeyColumn(),
                $clientId
            );
        });
    }

    protected static function resolveScopedClientId(): ?int
    {
        $staff = Auth::guard('web')->user();

        if ($staff instanceof User && $staff->client_id !== null) {
            return $staff->client_id;
        }

        $contact = Auth::guard('client')->user();

        if ($contact instanceof ClientContact) {
            return $contact->client_id;
        }

        return null;
    }

    public function clientForeignKeyColumn(): string
    {
        return 'client_id';
    }
}
