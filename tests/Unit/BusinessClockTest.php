<?php

namespace Tests\Unit;

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BusinessClockTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('calendarBoundaries')]
    public function test_business_calendar_uses_local_boundaries(
        string $utcInstant,
        string $localInstant,
        string $monthStart,
        string $monthEnd,
    ): void {
        CarbonImmutable::setTestNow($utcInstant);
        $clock = $this->clock();

        $this->assertSame(
            $localInstant,
            $clock->now()->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $monthStart,
            $clock->startOfMonth()->toDateString()
        );
        $this->assertSame(
            $monthEnd,
            $clock->endOfMonth()->toDateString()
        );
    }

    public static function calendarBoundaries(): array
    {
        return [
            '23:59:59 no ultimo dia' => [
                '2026-09-01 02:59:59 UTC',
                '2026-08-31 23:59:59',
                '2026-08-01',
                '2026-08-31',
            ],
            '00:00:00 no primeiro dia' => [
                '2026-09-01 03:00:00 UTC',
                '2026-09-01 00:00:00',
                '2026-09-01',
                '2026-09-30',
            ],
            '31 dezembro local' => [
                '2027-01-01 02:59:59 UTC',
                '2026-12-31 23:59:59',
                '2026-12-01',
                '2026-12-31',
            ],
            'primeiro de janeiro local' => [
                '2027-01-01 03:00:00 UTC',
                '2027-01-01 00:00:00',
                '2027-01-01',
                '2027-01-31',
            ],
            'fevereiro bissexto' => [
                '2028-02-29 15:00:00 UTC',
                '2028-02-29 12:00:00',
                '2028-02-01',
                '2028-02-29',
            ],
        ];
    }

    public function test_round_trip_preserves_the_utc_instant(): void
    {
        $clock = $this->clock();
        $utc = CarbonImmutable::parse('2026-08-20T15:00:00Z');
        $local = $clock->toBusinessTimezone($utc);

        $this->assertSame('2026-08-20 12:00:00', $local->format('Y-m-d H:i:s'));
        $this->assertSame('America/Sao_Paulo', $local->timezoneName);
        $this->assertSame(
            '2026-08-20T15:00:00+00:00',
            $local->utc()->toIso8601String()
        );
    }

    public function test_historical_dst_uses_iana_rules(): void
    {
        $local = $this->clock()->toBusinessTimezone(
            '2018-12-01T12:00:00Z'
        );

        $this->assertSame('2018-12-01 10:00:00', $local->format('Y-m-d H:i:s'));
        $this->assertSame('-02:00', $local->format('P'));
    }

    public function test_today_is_a_date_boundary_not_a_utc_conversion(): void
    {
        CarbonImmutable::setTestNow('2026-09-01 02:30:00 UTC');

        $this->assertSame(
            '2026-08-31 00:00:00',
            $this->clock()->today()->format('Y-m-d H:i:s')
        );
    }

    public function test_invalid_timezone_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BusinessClock('Brazil/Invalid');
    }

    private function clock(): BusinessClock
    {
        return new BusinessClock('America/Sao_Paulo');
    }
}
