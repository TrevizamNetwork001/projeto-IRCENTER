<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class BusinessClock
{
    private readonly DateTimeZone $timezone;

    public function __construct(string $timezone)
    {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException(
                'BUSINESS_TIMEZONE deve ser um timezone IANA válido.',
                previous: $exception,
            );
        }
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    public function startOfMonth(): CarbonImmutable
    {
        return $this->now()->startOfMonth();
    }

    public function endOfMonth(): CarbonImmutable
    {
        return $this->now()->endOfMonth();
    }

    public function toBusinessTimezone(
        DateTimeInterface|string $instant,
    ): CarbonImmutable {
        return CarbonImmutable::parse($instant)
            ->setTimezone($this->timezone);
    }
}
