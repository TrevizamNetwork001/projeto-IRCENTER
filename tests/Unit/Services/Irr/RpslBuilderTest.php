<?php

namespace Tests\Unit\Services\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrRoute;
use App\Services\Irr\RpslBuilder;
use PHPUnit\Framework\TestCase;

class RpslBuilderTest extends TestCase
{
    private function maintainer(): IrrMaintainer
    {
        return new IrrMaintainer(['mntner' => 'MAINT-AS64500']);
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
        $lines = explode("\n", $rpsl);

        $this->assertSame('route: 192.0.2.0/24', $lines[0]);
        $this->assertContains('origin: AS64500', $lines);
        $this->assertContains('mnt-by: MAINT-AS64500', $lines);
        $this->assertSame('source: TC', $lines[count($lines) - 1]);
        $this->assertNotContains('', $lines, 'não deve haver linha em branco dentro do objeto');
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

        foreach (['changed:', 'last-modified:', 'rpki-ov-state:'] as $forbidden) {
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
        $lines = explode("\n", $rpsl);

        $this->assertContains('remarks: Primeira observação', $lines);
        $this->assertContains('remarks: Segunda observação', $lines);
    }

    public function test_as_set_repeats_members_line_per_member(): void
    {
        $asSet = new IrrAsSet([
            'name' => 'AS64500:AS-CLIENTES',
            'members' => ['AS64501', 'AS64502', 'AS64500:AS-EDGE'],
        ]);
        $asSet->setRelation('maintainer', $this->maintainer());

        $rpsl = (new RpslBuilder)->asSet($asSet);
        $lines = explode("\n", $rpsl);

        $this->assertSame('as-set: AS64500:AS-CLIENTES', $lines[0]);
        $this->assertContains('members: AS64501', $lines);
        $this->assertContains('members: AS64502', $lines);
        $this->assertContains('members: AS64500:AS-EDGE', $lines);
        $this->assertSame('source: TC', $lines[count($lines) - 1]);
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

        $lines = explode("\n", $rpsl);

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
        $route->setRelation('maintainer', null);

        $rpsl = (new RpslBuilder)->route($route);

        $this->assertSame(
            "route: 192.0.2.0/24\norigin: AS64500\nsource: TC",
            $rpsl
        );
    }
}
