<?php

namespace App\Modules\Fiscal\Enums;

enum FiscalDocumentStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Ready, self::Cancelled],
            self::Ready => [self::Draft, self::Processing, self::Authorized],
            self::Processing => [self::Authorized, self::Rejected],
            self::Rejected => [self::Ready],
            self::Authorized => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
