<?php

namespace Tests\Unit\Services\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrRoute;
use App\Services\Irr\RpslBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RpslBuilderTest extends TestCase
{
    private function maintainer(string $mntner = 'MAINT-AS64500'): IrrMaintainer
    {
        return new IrrMaintainer(['mntner' => $mntner]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function asSet(array $attributes = []): IrrAsSet
    {
        $asSet = new IrrAsSet(array_merge([
            'name' => 'AS64500:AS-CLIENTES',
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
        ], $attributes));
        $asSet->setRelation('maintainer', $this->maintainer());

        return $asSet;
    }

    /**
     * @return list<string>
     */
    private function lines(string $rpsl): array
    {
        return explode("\n", rtrim($rpsl, "\n"));
    }

    public function test_route_ends_with_source_tc_and_has_no_blank_lines(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'descr' => 'Rede de teste',
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);
        $lines = $this->lines($rpsl);

        $this->assertSame('route: 192.0.2.0/24', $lines[0]);
        $this->assertContains('origin: AS64500', $lines);
        $this->assertContains('mnt-by: MAINT-AS64500', $lines);
        $this->assertSame('source: TC', $lines[count($lines) - 1]);
        $this->assertNotContains('', $lines, 'não deve haver linha em branco dentro do objeto');
    }

    public function test_route_ends_with_a_trailing_newline(): void
    {
        $route = new IrrRoute(['prefix' => '192.0.2.0/24', 'version' => 4, 'origin_asn' => 64500]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertStringEndsWith("source: TC\n", $rpsl);
    }

    public function test_route6_uses_route6_attribute(): void
    {
        $route = new IrrRoute([
            'prefix' => '2001:db8::/32',
            'version' => 6,
            'origin_asn' => 64500,
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertStringStartsWith('route6: 2001:db8::/32', $rpsl);
    }

    public function test_route_never_emits_forbidden_attributes(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        foreach (['changed:', 'last-modified:', 'rpki-ov-state:', 'geoidx:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $rpsl);
        }
    }

    public function test_route_repeats_remarks_line_per_line_of_input(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'remarks' => "Primeira observação\nSegunda observação",
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);
        $lines = $this->lines($rpsl);

        $this->assertContains('remarks: Primeira observação', $lines);
        $this->assertContains('remarks: Segunda observação', $lines);
    }

    public function test_as_set_repeats_members_line_per_member(): void
    {
        $asSet = $this->asSet(['members' => ['AS64501', 'AS64502', 'AS64500:AS-EDGE']]);

        $rpsl = (new RpslBuilder)->asSet($asSet);
        $lines = $this->lines($rpsl);

        $this->assertSame('as-set: AS64500:AS-CLIENTES', $lines[0]);
        $this->assertContains('members: AS64501', $lines);
        $this->assertContains('members: AS64502', $lines);
        $this->assertContains('members: AS64500:AS-EDGE', $lines);
        $this->assertContains('admin-c: JD1-TC', $lines);
        $this->assertContains('tech-c: JD1-TC', $lines);
        $this->assertSame('source: TC', $lines[count($lines) - 1]);
    }

    public function test_as_set_attributes_follow_the_real_object_order(): void
    {
        $asSet = $this->asSet([
            'descr' => 'Cone de clientes',
            'members' => ['AS64501'],
            'notify' => ['noc@example.test'],
        ]);

        $lines = $this->lines((new RpslBuilder)->asSet($asSet));

        $this->assertSame([
            'as-set: AS64500:AS-CLIENTES',
            'descr: Cone de clientes',
            'members: AS64501',
            'admin-c: JD1-TC',
            'tech-c: JD1-TC',
            'notify: noc@example.test',
            'mnt-by: MAINT-AS64500',
            'source: TC',
        ], $lines);
    }

    public function test_aut_num_repeats_import_export_and_mnt_by_lines(): void
    {
        $rpsl = (new RpslBuilder)->autNum([
            'aut_num' => 'AS64500',
            'as_name' => 'EXAMPLE-AS',
            'descr' => 'Exemplo',
            'imports' => ['from AS64501 accept ANY', 'from AS64502 accept ANY'],
            'exports' => ['to AS64501 announce AS64500'],
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
            'mnt_by' => ['MAINT-AS64500', 'MAINT-SECONDARY'],
        ]);

        $lines = $this->lines($rpsl);

        $this->assertSame('aut-num: AS64500', $lines[0]);
        $this->assertContains('import: from AS64501 accept ANY', $lines);
        $this->assertContains('import: from AS64502 accept ANY', $lines);
        $this->assertContains('export: to AS64501 announce AS64500', $lines);
        $this->assertContains('mnt-by: MAINT-AS64500', $lines);
        $this->assertContains('mnt-by: MAINT-SECONDARY', $lines);
        $this->assertSame('source: TC', $lines[count($lines) - 1]);
    }

    public function test_route_omits_optional_blank_fields_without_leaving_gaps(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertSame(
            "route: 192.0.2.0/24\norigin: AS64500\nmnt-by: MAINT-AS64500\nsource: TC\n",
            $rpsl
        );
    }

    // --- Injeção de RPSL (\r/\n em valores) ---------------------------

    public function test_descr_with_embedded_newline_throws(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'descr' => "Cliente X\nmnt-by: MAINT-AS99999",
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->route($route);
    }

    public function test_members_with_embedded_newline_throws(): void
    {
        $asSet = $this->asSet(['members' => ["AS64501\nmnt-by: MAINT-AS99999"]]);

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->asSet($asSet);
    }

    public function test_member_of_with_embedded_newline_throws(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'member_of' => ["AS268359:RS-ROUTES\nmnt-by: MAINT-AS99999"],
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->route($route);
    }

    public function test_route_notify_with_embedded_newline_throws(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'notify' => ["noc@example.test\nmnt-by: MAINT-AS99999"],
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->route($route);
    }

    public function test_as_set_notify_with_embedded_newline_throws(): void
    {
        $asSet = $this->asSet(['notify' => ["noc@example.test\nmnt-by: MAINT-AS99999"]]);

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->asSet($asSet);
    }

    public function test_remarks_lines_are_not_affected_by_the_newline_check(): void
    {
        // remarkLines() já separa por \n antes de chegar em build() —
        // cada linha resultante passa limpa pela checagem.
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'remarks' => "Linha 1\nLinha 2\nLinha 3",
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertStringContainsString('remarks: Linha 1', $rpsl);
        $this->assertStringContainsString('remarks: Linha 3', $rpsl);
    }

    // --- Atributos obrigatórios ----------------------------------------

    public function test_route_without_prefix_throws(): void
    {
        $route = new IrrRoute(['prefix' => '', 'version' => 4, 'origin_asn' => 64500]);
        $route->setRelation('maintainer', $this->maintainer());

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->route($route);
    }

    public function test_route_without_maintainer_throws(): void
    {
        $route = new IrrRoute(['prefix' => '192.0.2.0/24', 'version' => 4, 'origin_asn' => 64500]);
        $route->setRelation('maintainer', null);

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->route($route);
    }

    public function test_as_set_without_maintainer_throws(): void
    {
        $asSet = new IrrAsSet(['name' => 'AS64500:AS-CLIENTES']);
        $asSet->setRelation('maintainer', null);

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->asSet($asSet);
    }

    public function test_as_set_without_name_throws(): void
    {
        $asSet = new IrrAsSet(['name' => '']);
        $asSet->setRelation('maintainer', $this->maintainer());

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->asSet($asSet);
    }

    public function test_as_set_without_admin_c_throws(): void
    {
        $asSet = $this->asSet(['admin_c' => '']);

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->asSet($asSet);
    }

    public function test_as_set_without_tech_c_throws(): void
    {
        $asSet = $this->asSet(['tech_c' => '']);

        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->asSet($asSet);
    }

    public function test_aut_num_without_mnt_by_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->autNum([
            'aut_num' => 'AS64500',
            'as_name' => 'EXAMPLE-AS',
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
            'mnt_by' => [],
        ]);
    }

    public function test_aut_num_without_admin_c_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RpslBuilder)->autNum([
            'aut_num' => 'AS64500',
            'as_name' => 'EXAMPLE-AS',
            'admin_c' => '',
            'tech_c' => 'JD1-TC',
            'mnt_by' => 'MAINT-AS64500',
        ]);
    }

    // --- Normalização de origin_asn -------------------------------------

    /**
     * origin_asn é castado para "integer" pelo model (IrrRoute::casts()),
     * então uma string "AS64500" nunca chega intacta em route() por esse
     * caminho — o cast do model já é a primeira camada de defesa. Ainda
     * assim, normalizeAsn() aceita ambos os formatos por conta própria
     * (ex.: uso futuro fora do model, dado sujo em leitura direta do
     * banco), e é isso que testamos aqui via reflexão, direto no método.
     */
    public function test_origin_asn_as_prefixed_string_and_as_integer_normalize_to_the_same_value(): void
    {
        $builder = new RpslBuilder;
        $normalizeAsn = new \ReflectionMethod($builder, 'normalizeAsn');
        $normalizeAsn->setAccessible(true);

        $this->assertSame('AS64500', $normalizeAsn->invoke($builder, 64500));
        $this->assertSame('AS64500', $normalizeAsn->invoke($builder, 'AS64500'));
        $this->assertSame('AS64500', $normalizeAsn->invoke($builder, 'as64500'));
        $this->assertSame('AS64500', $normalizeAsn->invoke($builder, '64500'));
    }

    public function test_origin_asn_invalid_value_throws(): void
    {
        $builder = new RpslBuilder;
        $normalizeAsn = new \ReflectionMethod($builder, 'normalizeAsn');
        $normalizeAsn->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);

        $normalizeAsn->invoke($builder, 'not-an-asn');
    }

    public function test_route_with_origin_asn_integer_emits_normalized_origin_line(): void
    {
        $route = new IrrRoute(['prefix' => '192.0.2.0/24', 'version' => 4, 'origin_asn' => 64500]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertStringContainsString('origin: AS64500', $rpsl);
        $this->assertStringNotContainsString('ASAS', $rpsl);
    }

    // --- member-of / notify (route) -------------------------------------

    public function test_route_with_member_of_and_notify_present(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 268359,
            'member_of' => ['AS268359:RS-ROUTES', 'AS268359:RS-CLIENTES'],
            'notify' => ['noc@example.test', 'peering@example.test'],
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $lines = $this->lines((new RpslBuilder)->route($route));

        $this->assertContains('member-of: AS268359:RS-ROUTES', $lines);
        $this->assertContains('member-of: AS268359:RS-CLIENTES', $lines);
        $this->assertContains('notify: noc@example.test', $lines);
        $this->assertContains('notify: peering@example.test', $lines);
    }

    public function test_route_without_member_of_and_notify_omits_them_without_gaps(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 64500,
            'member_of' => null,
            'notify' => null,
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertStringNotContainsString('member-of', $rpsl);
        $this->assertStringNotContainsString('notify', $rpsl);
        $this->assertSame(
            "route: 192.0.2.0/24\norigin: AS64500\nmnt-by: MAINT-AS64500\nsource: TC\n",
            $rpsl
        );
    }

    public function test_route_attributes_follow_the_real_object_order(): void
    {
        $route = new IrrRoute([
            'prefix' => '192.0.2.0/24',
            'version' => 4,
            'origin_asn' => 268359,
            'descr' => 'Rede de teste',
            'member_of' => ['AS268359:RS-ROUTES'],
            'remarks' => 'Observação única',
            'notify' => ['noc@example.test'],
        ]);
        $route->setRelation('maintainer', $this->maintainer());

        $lines = $this->lines((new RpslBuilder)->route($route));

        $this->assertSame([
            'route: 192.0.2.0/24',
            'descr: Rede de teste',
            'origin: AS268359',
            'member-of: AS268359:RS-ROUTES',
            'remarks: Observação única',
            'notify: noc@example.test',
            'mnt-by: MAINT-AS64500',
            'source: TC',
        ], $lines);
    }
}
