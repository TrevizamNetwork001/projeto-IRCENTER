<?php

namespace App\Services;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Notification;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Sincroniza as notificações operacionais reais do usuário.
     *
     * @return Collection<int, Notification>
     */
    public function syncFor(User $user): Collection
    {
        $definitions = $this->definitionsFor($user);
        $activeKeys = [];

        foreach ($definitions as $definition) {
            if ($definition['count'] < 1) {
                continue;
            }

            $activeKeys[] = $definition['unique_key'];

            $notification = Notification::query()->firstOrNew([
                'user_id' => $user->id,
                'unique_key' => $definition['unique_key'],
            ]);

            $wasResolved = $notification->exists
                && $notification->resolved_at !== null;

            $notification->fill([
                'type' => 'operational',
                'priority' => $definition['priority'],
                'title' => $definition['title'],
                'message' => $definition['message'],
                'action_url' => $definition['action_url'],
                'resolved_at' => null,
            ]);

            if ($wasResolved) {
                $notification->read_at = null;
            }

            if (! $notification->exists || $notification->isDirty()) {
                $notification->save();
            }
        }

        $staleQuery = Notification::query()
            ->where('user_id', $user->id)
            ->where('type', 'operational')
            ->whereNull('resolved_at');

        if ($activeKeys === []) {
            $staleQuery->update([
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $staleQuery
                ->whereNotIn('unique_key', $activeKeys)
                ->update([
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->latest()
            ->get();
    }

    /**
     * @return array<int, array{
     *     unique_key: string,
     *     count: int,
     *     priority: string,
     *     title: string,
     *     message: string,
     *     action_url: string
     * }>
     */
    private function definitionsFor(User $user): array
    {
        $clientsWithoutAsn = Client::query()
            ->where('active', true)
            ->whereDoesntHave(
                'autonomousSystems',
                fn ($query) => $query->where('active', true)
            )
            ->count();

        $asnsWithoutPrefixes = AutonomousSystem::query()
            ->where('active', true)
            ->whereDoesntHave(
                'prefixes',
                fn ($query) => $query->where('active', true)
            )
            ->count();

        $inactivePrefixes = Prefix::query()
            ->where('active', false)
            ->count();

        $definitions = [
            [
                'unique_key' => 'clients-without-asn',
                'count' => $clientsWithoutAsn,
                'priority' => Notification::PRIORITY_WARNING,
                'title' => 'Clientes sem ASN ativo',
                'message' => $this->quantityMessage(
                    $clientsWithoutAsn,
                    'cliente ativo está sem ASN ativo vinculado.',
                    'clientes ativos estão sem ASN ativo vinculado.'
                ),
                'action_url' => route('clients.index'),
            ],
            [
                'unique_key' => 'asns-without-prefixes',
                'count' => $asnsWithoutPrefixes,
                'priority' => Notification::PRIORITY_WARNING,
                'title' => 'ASNs sem prefixos ativos',
                'message' => $this->quantityMessage(
                    $asnsWithoutPrefixes,
                    'ASN ativo está sem prefixo ativo.',
                    'ASNs ativos estão sem prefixos ativos.'
                ),
                'action_url' => route('autonomous-systems.index'),
            ],
            [
                'unique_key' => 'inactive-prefixes',
                'count' => $inactivePrefixes,
                'priority' => Notification::PRIORITY_INFO,
                'title' => 'Prefixos inativos no inventário',
                'message' => $this->quantityMessage(
                    $inactivePrefixes,
                    'prefixo está desativado no inventário.',
                    'prefixos estão desativados no inventário.'
                ),
                'action_url' => route('prefixes.index', [
                    'status' => 'inactive',
                ]),
            ],
        ];

        if ($user->canOperate()) {
            $criticalIncidents = RoutingIncident::query()
                ->where('severity', RoutingIncident::SEVERITY_CRITICAL)
                ->whereNotIn('status', [
                    RoutingIncident::STATUS_RESOLVED,
                    RoutingIncident::STATUS_CLOSED,
                ])
                ->count();

            $highIncidents = RoutingIncident::query()
                ->where('severity', RoutingIncident::SEVERITY_HIGH)
                ->whereNotIn('status', [
                    RoutingIncident::STATUS_RESOLVED,
                    RoutingIncident::STATUS_CLOSED,
                ])
                ->count();

            $definitions[] = [
                'unique_key' => 'critical-routing-incidents',
                'count' => $criticalIncidents,
                'priority' => Notification::PRIORITY_CRITICAL,
                'title' => 'Incidentes críticos em aberto',
                'message' => $this->quantityMessage(
                    $criticalIncidents,
                    'incidente crítico exige atuação imediata.',
                    'incidentes críticos exigem atuação imediata.'
                ),
                'action_url' => route('routing-incidents.index', [
                    'severity' => RoutingIncident::SEVERITY_CRITICAL,
                    'status' => 'open',
                ]),
            ];

            $definitions[] = [
                'unique_key' => 'high-routing-incidents',
                'count' => $highIncidents,
                'priority' => Notification::PRIORITY_WARNING,
                'title' => 'Incidentes de alta severidade',
                'message' => $this->quantityMessage(
                    $highIncidents,
                    'incidente de alta severidade está em aberto.',
                    'incidentes de alta severidade estão em aberto.'
                ),
                'action_url' => route('routing-incidents.index', [
                    'severity' => RoutingIncident::SEVERITY_HIGH,
                    'status' => 'open',
                ]),
            ];
        }

        if ($user->isAdministrator()) {
            $blockedUsers = User::query()
                ->where('active', false)
                ->count();

            $passwordChanges = User::query()
                ->where('active', true)
                ->where('must_change_password', true)
                ->count();

            $definitions[] = [
                'unique_key' => 'blocked-users',
                'count' => $blockedUsers,
                'priority' => Notification::PRIORITY_WARNING,
                'title' => 'Usuários bloqueados',
                'message' => $this->quantityMessage(
                    $blockedUsers,
                    'conta está sem acesso à plataforma.',
                    'contas estão sem acesso à plataforma.'
                ),
                'action_url' => route('users.index', [
                    'status' => 'inactive',
                ]),
            ];

            $definitions[] = [
                'unique_key' => 'pending-password-changes',
                'count' => $passwordChanges,
                'priority' => Notification::PRIORITY_INFO,
                'title' => 'Troca obrigatória de senha pendente',
                'message' => $this->quantityMessage(
                    $passwordChanges,
                    'usuário ainda precisa definir uma nova senha.',
                    'usuários ainda precisam definir uma nova senha.'
                ),
                'action_url' => route('users.index'),
            ];
        }

        return $definitions;
    }

    private function quantityMessage(
        int $count,
        string $singular,
        string $plural
    ): string {
        return sprintf(
            '%d %s',
            $count,
            $count === 1 ? $singular : $plural
        );
    }
}
