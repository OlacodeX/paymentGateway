<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';

    const DEFAULT = self::PENDING;

    public function title(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::AUTHORIZED => 'Authorized',
            self::CAPTURED => 'Captured',
            self::VOIDED => 'Voided',
            self::REFUNDED => 'Refunded',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Payment created, waiting for bank authorization',
            self::AUTHORIZED => 'Funds held on the card, not yet captured',
            self::CAPTURED => 'Funds charged after authorization',
            self::VOIDED => 'Authorization cancelled before capture',
            self::REFUNDED => 'Captured funds returned to the customer',
        };
    }
}
