<?php

namespace App\Services\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrRoute;
use InvalidArgumentException;

/**
 * Monta o texto RPSL de objetos destinados à base TC (bgp.net.br).
 *
 * Regras obrigatórias em todos os métodos:
 * - todo objeto termina com "source: TC";
 * - changed/last-modified/rpki-ov-state/geoidx nunca são emitidos
 *   (gerenciados pelo servidor do TC — enviá-los é rejeitado ou
 *   ignorado pela API);
 * - atributos multivalorados (mnt-by, members, remarks, notify,
 *   member-of, import, export) repetem a linha em vez de concatenar
 *   valores numa só;
 * - nenhuma linha em branco dentro do objeto;
 * - nenhum valor pode conter \r ou \n — permitir isso injetaria
 *   atributos RPSL arbitrários (ver build());
 * - atributos obrigatórios ausentes lançam exceção em vez de
 *   desaparecer silenciosamente do objeto gerado.
 */
final class RpslBuilder
{
    private const FORBIDDEN_ATTRIBUTES = [
        'changed',
        'last-modified',
        'rpki-ov-state',
        'geoidx',
    ];

    public function route(IrrRoute $route): string
    {
        $prefix = trim((string) $route->prefix);

        if ($prefix === '') {
            throw new InvalidArgumentException("Objeto {$route->attributeName()} sem prefixo — atributo obrigatório ausente.");
        }

        if ($route->origin_asn === null || trim((string) $route->origin_asn) === '') {
            throw new InvalidArgumentException("Objeto {$route->attributeName()} sem origin — atributo obrigatório ausente.");
        }

        $maintainer = $route->maintainer;

        if ($maintainer === null) {
            throw new InvalidArgumentException("Objeto {$route->attributeName()} sem maintainer — mnt-by é obrigatório em RPSL.");
        }

        $lines = [
            [$route->attributeName(), $prefix],
        ];

        if (filled($route->descr)) {
            $lines[] = ['descr', $route->descr];
        }

        $lines[] = ['origin', $this->normalizeAsn($route->origin_asn)];

        foreach ($this->listValues($route->member_of) as $memberOf) {
            $lines[] = ['member-of', $memberOf];
        }

        foreach ($this->remarkLines($route->remarks) as $remark) {
            $lines[] = ['remarks', $remark];
        }

        foreach ($this->listValues($route->notify) as $notify) {
            $lines[] = ['notify', $notify];
        }

        foreach ($this->mntBy($maintainer) as $mntBy) {
            $lines[] = ['mnt-by', $mntBy];
        }

        return $this->build($lines);
    }

    public function asSet(IrrAsSet $asSet): string
    {
        $name = trim((string) $asSet->name);

        if ($name === '') {
            throw new InvalidArgumentException('Objeto as-set sem nome — atributo obrigatório ausente.');
        }

        $maintainer = $asSet->maintainer;

        if ($maintainer === null) {
            throw new InvalidArgumentException('Objeto as-set sem maintainer — mnt-by é obrigatório em RPSL.');
        }

        $adminC = trim((string) $asSet->admin_c);

        if ($adminC === '') {
            throw new InvalidArgumentException('Objeto as-set sem admin-c — atributo obrigatório ausente.');
        }

        $techC = trim((string) $asSet->tech_c);

        if ($techC === '') {
            throw new InvalidArgumentException('Objeto as-set sem tech-c — atributo obrigatório ausente.');
        }

        $lines = [
            ['as-set', $name],
        ];

        if (filled($asSet->descr)) {
            $lines[] = ['descr', $asSet->descr];
        }

        foreach ($this->listValues($asSet->members) as $member) {
            $lines[] = ['members', $member];
        }

        $lines[] = ['admin-c', $adminC];
        $lines[] = ['tech-c', $techC];

        foreach ($this->listValues($asSet->notify) as $notify) {
            $lines[] = ['notify', $notify];
        }

        foreach ($this->mntBy($maintainer) as $mntBy) {
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
        foreach ([
            'aut_num' => 'aut-num',
            'as_name' => 'as-name',
            'admin_c' => 'admin-c',
            'tech_c' => 'tech-c',
        ] as $key => $label) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                throw new InvalidArgumentException("Objeto aut-num sem {$label} — atributo obrigatório ausente.");
            }
        }

        $mntByList = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), (array) ($data['mnt_by'] ?? [])),
            static fn (string $value): bool => $value !== ''
        ));

        if ($mntByList === []) {
            throw new InvalidArgumentException('Objeto aut-num sem mnt-by — atributo obrigatório ausente.');
        }

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

        foreach ($mntByList as $mntBy) {
            $lines[] = ['mnt-by', $mntBy];
        }

        return $this->build($lines);
    }

    /**
     * Aceita tanto o inteiro (esperado, cast do model) quanto uma string
     * já prefixada como "AS64500" (dado sujo, digitação manual etc.) e
     * sempre emite "AS<numero>" uma única vez.
     */
    private function normalizeAsn(int|string $value): string
    {
        $digits = preg_replace('/^AS/i', '', trim((string) $value));

        if ($digits === null || $digits === '' || ! ctype_digit($digits)) {
            throw new InvalidArgumentException("origin_asn inválido: '{$value}'.");
        }

        return 'AS'.(int) $digits;
    }

    /**
     * @return list<string>
     */
    private function mntBy(IrrMaintainer $maintainer): array
    {
        return [$maintainer->mntner];
    }

    /**
     * @param array<int, mixed>|null $values
     * @return list<string>
     */
    private function listValues(?array $values): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values ?? []),
            static fn (string $value): bool => $value !== ''
        ));
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

            // Um \n embutido (ex.: descr = "Cliente X\nmnt-by: MAINT-X")
            // injetaria um atributo RPSL não autorizado ao ser juntado
            // com implode("\n", ...) — rejeitar é a única opção segura,
            // truncar ou trocar por espaço mascararia o problema.
            if (preg_match('/[\r\n]/', $value)) {
                throw new InvalidArgumentException(
                    "Valor de '{$attribute}' contém quebra de linha: não é permitido em atributo RPSL."
                );
            }

            if ($value === '') {
                continue;
            }

            $text[] = "{$attribute}: {$value}";
        }

        $text[] = 'source: TC';

        return implode("\n", $text)."\n";
    }
}
