<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe automaticamente as queries do model ao cliente do usuário
 * autenticado (guard "web"), quando esse usuário tiver `client_id`
 * preenchido. Usuários sem `client_id` (o padrão hoje) continuam
 * enxergando todos os registros — comportamento atual preservado.
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
            $user = Auth::guard('web')->user();

            if (! $user instanceof User || $user->client_id === null) {
                return;
            }

            $query->where(
                $query->getModel()->clientForeignKeyColumn(),
                $user->client_id
            );
        });
    }

    public function clientForeignKeyColumn(): string
    {
        return 'client_id';
    }
}
