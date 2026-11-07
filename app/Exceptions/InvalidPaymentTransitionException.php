<?php

namespace App\Exceptions;

use App\Enums\PaymentStatus;
use Exception;

class InvalidPaymentTransitionException extends Exception
{
    public function __construct(
        public readonly PaymentStatus $from,
        public readonly PaymentStatus $to,
    ) {
        parent::__construct(
            "Cannot transition payment from [{$from->value}] to [{$to->value}]."
        );
    }
}
