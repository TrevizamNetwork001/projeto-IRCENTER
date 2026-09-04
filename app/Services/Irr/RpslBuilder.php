<?php

namespace App\Services\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrRoute;

/**
 * Monta o texto RPSL de objetos destinados à base TC (bgp.net.br).
 *
 * Regras obrigatórias em todos os métodos:
 * - todo objeto termina com "source: TC";
 * - changed/last-modified/rpki-ov-state nunca são emitidos (gerenciados
 *   pelo servidor do TC — enviá-los é rejeitado ou ignorado pela API);
 * - atributos multivalorados (mnt-by, members, remarks, import, export)
 *   repetem a linha em vez de concatenar valores numa só;
 * - nenhuma linha em branco dentro do objeto.
 */
final class RpslBuilder
{
    private const FORBIDDEN_ATTRIBUTES = [
        'changed',
        'last-modified',
        'rpki-ov-state',
    ];

    public function route(IrrRoute $route): string
    {
        $lines = [
            [$route->attributeName(), $route->prefix],
        ];

        if (filled($route->descr)) {
            $lines[] = ['descr', $route->descr];
        }

        $lines[] = ['origin', 'AS'.$route->origin_asn];

        foreach ($this->remarkLines($route->remarks) as $remark) {
            $lines[] = ['remarks', $remark];
        }

        foreach ($this->mntBy($route->maintainer) as $mntBy) {
            $lines[] = ['mnt-by', $mntBy];
        }

        return $this->build($lines);
    }

    public function asSet(IrrAsSet $asSet): string
    {
        $lines = [
            ['as-set', $asSet->name],
        ];

        if (filled($asSet->descr)) {
            $lines[] = ['descr', $asSet->descr];
        }

        foreach ((array) ($asSet->members ?? []) as $member) {
            $lines[] = ['members', (string) $member];
        }

        foreach ($this->mntBy($asSet->maintainer) as $mntBy) {
            $lines[] = ['mnt-by', $mntBy];
        }

        return $this->build($lines);
    }

    /**
     * Não há tabela dedicada a aut-num (cadastro inicial do AS continua
     * pelo wizard bgp.net.br — ver regra da Tarefa 5), então este método
     * recebe os dados já resolvidos em vez de um model.
     *
     * @param array{
     *     aut_num: string,
     *     as_name: string,
     *     descr?: string|null,
     *     imports?: list<string>,
     *     exports?: list<string>,
     *     admin_c: string,
     *     tech_c: string,
     *     mnt_by: string|list<string>,
     * } $data
     */
    public function autNum(array $data): string
    {
        $lines = [
            ['aut-num', $data['aut_num']],
            ['as-name', $data['as_name']],
        ];

        if (filled($data['descr'] ?? null)) {
            $lines[] = ['descr', $data['descr']];
        }

        foreach ($data['imports'] ?? [] as $import) {
            $lines[] = ['import', $import];
        }

        foreach ($data['exports'] ?? [] as $export) {
            $lines[] = ['export', $export];
        }

        $lines[] = ['admin-c', $data['admin_c']];
        $lines[] = ['tech-c', $data['tech_c']];

        foreach ((array) $data['mnt_by'] as $mntBy) {
            $lines[] = ['mnt-by', $mntBy];
        }

        return $this->build($lines);
    }

    /**
     * @return list<string>
     */
    private function mntBy(?IrrMaintainer $maintainer): array
    {
        return $maintainer !== null ? [$maintainer->mntner] : [];
    }

    /**
     * @return list<string>
     */
    private function remarkLines(?string $remarks): array
    {
        if ($remarks === null || trim($remarks) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $remarks)),
            static fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * @param list<array{0: string, 1: string}> $lines
     */
    private function build(array $lines): string
    {
        $text = [];

        foreach ($lines as [$attribute, $value]) {
            if (in_array($attribute, self::FORBIDDEN_ATTRIBUTES, true)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $text[] = "{$attribute}: {$value}";
        }

        $text[] = 'source: TC';

        return implode("\n", $text);
    }
}
