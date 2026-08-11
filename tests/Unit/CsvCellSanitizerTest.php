<?php

namespace Tests\Unit;

use App\Support\CsvCellSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellSanitizerTest extends TestCase
{
    #[DataProvider('dangerousValues')]
    public function test_it_neutralizes_formula_prefixes(string $value): void
    {
        $this->assertSame("'".$value, (new CsvCellSanitizer())->sanitize($value));
    }

    public static function dangerousValues(): array
    {
        return [
            ['=1+1'], ['+1+1'], ['-1+1'], ['@SUM(A1:A2)'],
            ["\t=1+1"], ["\r=1+1"], ["\n=1+1"], ['  =1+1'],
        ];
    }

    #[DataProvider('safeValues')]
    public function test_it_preserves_safe_values(mixed $value): void
    {
        $this->assertSame($value, (new CsvCellSanitizer())->sanitize($value));
    }

    public static function safeValues(): array
    {
        return [['Empresa ABC'], ['12345'], ['João & Companhia'], ['texto UTF-8'], [12345]];
    }
}
