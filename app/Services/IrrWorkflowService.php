<?php

namespace App\Services;

use App\Models\IrrWorkflow;
use App\Models\IrrWorkflowStep;
use App\Models\IrrWorkflowPrefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IrrWorkflowService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): IrrWorkflow
    {
        return DB::transaction(function () use ($data): IrrWorkflow {
            $data['status'] = IrrWorkflow::STATUS_IN_PROGRESS;
            $data['current_step'] = 1;
            $data['started_at'] = now();

            $workflow = IrrWorkflow::query()->create($data);

            foreach ($this->definitions() as $definition) {
                $workflow->steps()->create([
                    ...$definition,
                    'status' => $definition['step_number'] === 1
                        ? IrrWorkflowStep::STATUS_READY
                        : IrrWorkflowStep::STATUS_LOCKED,
                ]);
            }

            $this->syncWorkflowPrefixes($workflow);

            $this->prepareStep($workflow->fresh('steps'), 1);

            return $workflow->fresh([
                'client',
                'autonomousSystem',
                'steps',
            ]);
        });
    }

    public function prepareStep(
        IrrWorkflow $workflow,
        int $stepNumber
    ): IrrWorkflowStep {
        $workflow->loadMissing([
            'client',
            'autonomousSystem',
            'autonomousSystem.prefixes',
            'prefixes',
            'steps',
        ]);

        $step = $workflow->steps
            ->firstWhere('step_number', $stepNumber);

        if (! $step instanceof IrrWorkflowStep) {
            throw ValidationException::withMessages([
                'step' => 'Etapa do processo IRR não encontrada.',
            ]);
        }

        if ($step->status === IrrWorkflowStep::STATUS_LOCKED) {
            throw ValidationException::withMessages([
                'step' => 'A etapa anterior precisa ser concluída primeiro.',
            ]);
        }

        $content = match ($step->step_key) {
            'identity_maintainer' => $this->identityMaintainerContent($workflow),
            'aut_num' => $this->autNumContent($workflow),
            'route_set' => $this->routeSetContent($workflow),
            'routes' => $this->routesContent($workflow),
            'as_set' => $this->asSetContent($workflow),
            'review' => $this->reviewContent($workflow),
            default => [
                'subject' => null,
                'body' => null,
                'rpsl' => null,
            ],
        };

        $step->update([
            'status' => IrrWorkflowStep::STATUS_READY,
            'email_to' => $content['email_to']
                ?? $workflow->destination_email,
            'email_subject' => $content['subject'],
            'email_body' => $content['body'],
            'rpsl_content' => $content['rpsl'],
            'prepared_at' => now(),
        ]);

        return $step->fresh();
    }

    public function markSent(
        IrrWorkflow $workflow,
        IrrWorkflowStep $step,
        ?string $notes = null
    ): void {
        $this->ensureBelongsToWorkflow($workflow, $step);

        if ($step->status !== IrrWorkflowStep::STATUS_READY) {
            throw ValidationException::withMessages([
                'step' => 'Somente etapas prontas podem ser marcadas como enviadas.',
            ]);
        }

        $step->update([
            'status' => IrrWorkflowStep::STATUS_WAITING_CONFIRMATION,
            'sent_at' => now(),
            'operator_notes' => $notes,
        ]);
    }

    public function confirm(
        IrrWorkflow $workflow,
        IrrWorkflowStep $step,
        ?string $notes = null
    ): void {
        $this->ensureBelongsToWorkflow($workflow, $step);

        if (
            $step->status
            !== IrrWorkflowStep::STATUS_WAITING_CONFIRMATION
        ) {
            throw ValidationException::withMessages([
                'step' => 'A etapa precisa estar aguardando confirmação.',
            ]);
        }

        DB::transaction(function () use (
            $workflow,
            $step,
            $notes
        ): void {
            $step->update([
                'status' => IrrWorkflowStep::STATUS_COMPLETED,
                'confirmed_at' => now(),
                'completed_at' => now(),
                'operator_notes' => $notes ?: $step->operator_notes,
            ]);

            $nextStep = $workflow->steps()
                ->where('step_number', '>', $step->step_number)
                ->orderBy('step_number')
                ->first();

            if (! $nextStep instanceof IrrWorkflowStep) {
                $workflow->update([
                    'status' => IrrWorkflow::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                return;
            }

            $nextStep->update([
                'status' => IrrWorkflowStep::STATUS_READY,
            ]);

            $workflow->update([
                'current_step' => $nextStep->step_number,
            ]);

            $this->prepareStep(
                $workflow->fresh([
                    'client',
                    'autonomousSystem',
                    'autonomousSystem.prefixes',
                    'steps',
                ]),
                $nextStep->step_number
            );
        });
    }

    private function syncWorkflowPrefixes(
        IrrWorkflow $workflow
    ): void {
        $workflow->loadMissing('autonomousSystem.prefixes');

        $profile = config(
            'irr.profiles.'.$workflow->profile_key,
            config('irr.profiles.manual', [])
        );

        foreach ($workflow->autonomousSystem->prefixes as $prefix) {
            if (! $prefix->active) {
                continue;
            }

            $baseLength = (int) str($prefix->prefix)->afterLast('/')->toString();

            $defaultMaximumLength = $prefix->ip_version === 6
                ? (int) ($profile['ipv6_default_max_length'] ?? 48)
                : (int) ($profile['ipv4_default_max_length'] ?? 24);

            $allowsMoreSpecifics = $baseLength < $defaultMaximumLength;

            $workflow->prefixes()->updateOrCreate(
                ['prefix_id' => $prefix->id],
                [
                    'ip_version' => $prefix->ip_version,
                    'prefix' => $prefix->prefix,
                    'route_set_mode' => $allowsMoreSpecifics
                        ? IrrWorkflowPrefix::MODE_MORE_SPECIFICS
                        : IrrWorkflowPrefix::MODE_EXACT,
                    'maximum_length' => $allowsMoreSpecifics
                        ? $defaultMaximumLength
                        : null,
                    'generate_route_object' => true,
                    'active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'step_number' => 1,
                'step_key' => 'identity_maintainer',
                'title' => 'Contato e Maintainer',
                'instructions' => 'Envie o contato e o maintainer no mesmo pacote inicial e aguarde a confirmação.',
            ],
            [
                'step_number' => 2,
                'step_key' => 'aut_num',
                'title' => 'Aut-num',
                'instructions' => 'Publique o objeto do sistema autônomo usando o contato e o maintainer já aprovados.',
            ],
            [
                'step_number' => 3,
                'step_key' => 'route_set',
                'title' => 'Route-set',
                'instructions' => 'Crie o conjunto que reunirá os prefixos IPv4 e IPv6.',
            ],
            [
                'step_number' => 4,
                'step_key' => 'routes',
                'title' => 'Route e Route6',
                'instructions' => 'Envie os objetos individuais de origem para cada prefixo.',
            ],
            [
                'step_number' => 5,
                'step_key' => 'as_set',
                'title' => 'AS-set',
                'instructions' => 'Crie o conjunto do ASN e registre os membros do cone de roteamento.',
            ],
            [
                'step_number' => 6,
                'step_key' => 'review',
                'title' => 'Revisão final',
                'instructions' => 'Revise os objetos preparados, registre pendências e conclua a implantação.',
            ],
        ];
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function identityMaintainerContent(
        IrrWorkflow $workflow
    ): array {
        $person = implode("\n", array_filter([
            "person: {$workflow->contact_name}",
            $workflow->contact_address
                ? "address: {$workflow->contact_address}"
                : null,
            $workflow->contact_phone
                ? "phone: {$workflow->contact_phone}"
                : null,
            "e-mail: {$workflow->contact_email}",
            "nic-hdl: {$workflow->contact_handle}",
            "mnt-by: {$workflow->maintainer}",
            "source: {$workflow->irr_source}",
        ]));

        $maintainer = implode("\n", [
            "mntner: {$workflow->maintainer}",
            "descr: Maintainer de {$workflow->client->displayName()}",
            "admin-c: {$workflow->contact_handle}",
            "tech-c: {$workflow->contact_handle}",
            "upd-to: {$workflow->contact_email}",
            "mnt-nfy: {$workflow->contact_email}",
            "mnt-by: {$workflow->maintainer}",
            "source: {$workflow->irr_source}",
        ]);

        return $this->email(
            $workflow,
            'Cadastro inicial de contato e maintainer IRR',
            $person."\n\n".$maintainer
        );
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function autNumContent(IrrWorkflow $workflow): array
    {
        $asn = $workflow->autonomousSystem;

        $rpsl = implode("\n", [
            "aut-num: {$asn->formattedAsn()}",
            "as-name: ".$this->normalizeName($asn->name),
            "descr: {$workflow->client->displayName()}",
            "admin-c: {$workflow->contact_handle}",
            "tech-c: {$workflow->contact_handle}",
            "mnt-by: {$workflow->maintainer}",
            "source: {$workflow->irr_source}",
        ]);

        return $this->email(
            $workflow,
            'Cadastro do objeto aut-num',
            $rpsl
        );
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function routeSetContent(IrrWorkflow $workflow): array
    {
        $members = $workflow->prefixes
            ->where('active', true)
            ->sortBy([
                ['ip_version', 'asc'],
                ['prefix', 'asc'],
            ])
            ->map(
                fn (IrrWorkflowPrefix $prefix): string =>
                    'mp-members: '.$prefix->routeSetMember()
            )
            ->implode("\n");

        $rpsl = implode("\n", array_filter([
            "route-set: {$workflow->route_set}",
            "descr: Prefixos de {$workflow->client->displayName()}",
            $members !== '' ? $members : null,
            "mnt-by: {$workflow->maintainer}",
            "source: {$workflow->irr_source}",
        ]));

        return $this->email(
            $workflow,
            'Cadastro do route-set IRR',
            $rpsl
        );
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function routesContent(IrrWorkflow $workflow): array
    {
        $asn = $workflow->autonomousSystem->formattedAsn();

        $objects = $workflow->prefixes
            ->where('active', true)
            ->where('generate_route_object', true)
            ->sortBy([
                ['ip_version', 'asc'],
                ['prefix', 'asc'],
            ])
            ->map(function (IrrWorkflowPrefix $prefix) use (
                $workflow,
                $asn
            ): string {
                $attribute = $prefix->ip_version === 6
                    ? 'route6'
                    : 'route';

                return implode("\n", [
                    "{$attribute}: {$prefix->prefix}",
                    "origin: {$asn}",
                    "member-of: {$workflow->route_set}",
                    "descr: Prefixo de {$workflow->client->displayName()}",
                    "mnt-by: {$workflow->maintainer}",
                    "source: {$workflow->irr_source}",
                ]);
            })
            ->implode("\n\n");

        if ($objects === '') {
            $objects = '# Nenhum prefixo vinculado ao ASN.';
        }

        return $this->email(
            $workflow,
            'Cadastro dos objetos route e route6',
            $objects
        );
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function asSetContent(IrrWorkflow $workflow): array
    {
        $rpsl = implode("\n", [
            "as-set: {$workflow->as_set}",
            "descr: Cone de ASNs de {$workflow->client->displayName()}",
            "members: {$workflow->autonomousSystem->formattedAsn()}",
            "mnt-by: {$workflow->maintainer}",
            "source: {$workflow->irr_source}",
        ]);

        return $this->email(
            $workflow,
            'Cadastro do AS-set IRR',
            $rpsl
        );
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function reviewContent(IrrWorkflow $workflow): array
    {
        $completed = $workflow->steps
            ->where('status', IrrWorkflowStep::STATUS_COMPLETED)
            ->sortBy('step_number');

        $summary = [
            'REVISÃO FINAL DA IMPLANTAÇÃO IRR',
            '',
            "Cliente: {$workflow->client->displayName()}",
            "ASN: {$workflow->autonomousSystem->formattedAsn()}",
            "Fonte: {$workflow->irr_source}",
            "Maintainer: {$workflow->maintainer}",
            "Route-set: {$workflow->route_set}",
            "AS-set: {$workflow->as_set}",
            '',
            'Etapas confirmadas:',
        ];

        foreach ($completed as $step) {
            $confirmedAt = $step->confirmed_at?->format('d/m/Y H:i')
                ?? 'sem data';

            $summary[] = "- {$step->title}: {$confirmedAt}";
        }

        $summary[] = '';
        $summary[] = 'Revise os dados publicados na base IRR antes de concluir.';

        $content = implode("\n", $summary);

        return [
            'email_to' => null,
            'subject' => 'Revisão final — '
                .$workflow->autonomousSystem->formattedAsn(),
            'body' => $content,
            'rpsl' => $content,
        ];
    }

    /**
     * @return array{subject: string, body: string, rpsl: string}
     */
    private function email(
        IrrWorkflow $workflow,
        string $subject,
        string $rpsl
    ): array {
        return [
            'subject' => $subject.' — '
                .$workflow->autonomousSystem->formattedAsn(),
            'body' => implode("\n", [
                'Olá,',
                '',
                'Solicito o cadastro/atualização dos objetos IRR abaixo:',
                '',
                $rpsl,
                '',
                'Atenciosamente,',
                $workflow->contact_name,
                $workflow->contact_email,
            ]),
            'rpsl' => $rpsl,
        ];
    }

    private function ensureBelongsToWorkflow(
        IrrWorkflow $workflow,
        IrrWorkflowStep $step
    ): void {
        abort_unless(
            $step->irr_workflow_id === $workflow->id,
            404
        );
    }

    private function normalizeName(string $value): string
    {
        $value = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        ) ?: $value;

        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value)
            ?: 'AS-LOCAL';

        return trim($value, '-');
    }
}
