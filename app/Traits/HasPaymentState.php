<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentTransitionException;

trait HasPaymentState
{
    /**
     * @return array<string, list<PaymentStatus>>
     */
    protected function allowedTransitions(): array
    {
        return [
            PaymentStatus::PENDING->value => [PaymentStatus::AUTHORIZED],
            PaymentStatus::AUTHORIZED->value => [PaymentStatus::CAPTURED, PaymentStatus::VOIDED],
            PaymentStatus::CAPTURED->value => [PaymentStatus::REFUNDED],
            PaymentStatus::VOIDED->value => [],
            PaymentStatus::REFUNDED->value => [],
        ];
    }

    public function canTransitionTo(PaymentStatus $next): bool
    {
        $allowed = $this->allowedTransitions()[$this->status->value] ?? [];

        return in_array($next, $allowed, true);
    }

    public function transitionTo(PaymentStatus $next): static
    {
        if (! $this->canTransitionTo($next)) {
            throw new InvalidPaymentTransitionException($this->status, $next);
        }

        $this->status = $next;
        $this->markTransitionTimestamp($next);

        return $this;
    }

    protected function markTransitionTimestamp(PaymentStatus $status): void
    {
        match ($status) {
            PaymentStatus::AUTHORIZED => $this->authorized_at = now(),
            PaymentStatus::CAPTURED => $this->captured_at = now(),
            PaymentStatus::VOIDED => $this->voided_at = now(),
            PaymentStatus::REFUNDED => $this->refunded_at = now(), 
            PaymentStatus::PENDING => null,
        };
    }
}
