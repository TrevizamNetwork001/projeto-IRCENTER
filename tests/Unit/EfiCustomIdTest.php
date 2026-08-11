<?php

namespace Tests\Unit;

use App\Modules\Finance\Support\EfiCustomId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EfiCustomIdTest extends TestCase
{
    #[Test]
    public function it_converts_internal_correlation_deterministically(): void
    {
        $internal =
            'invoice:01TEST:provider:efi:method:boleto_pix';
        $expected =
            'invoice_01TEST_provider_efi_method_boleto_pix';

        $first = EfiCustomId::fromCorrelationId($internal);
        $second = EfiCustomId::fromCorrelationId($internal);

        $this->assertSame($expected, $first);
        $this->assertSame($first, $second);
        $this->assertStringNotContainsString(':', $first);
    }

    #[Test]
    public function it_rejects_unexpected_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EfiCustomId::fromCorrelationId(
            'invoice:01.TEST:provider:efi:method:boleto_pix'
        );
    }
}
