<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\HasPaymentState;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'order_id',
    'customer_id',
    'amount',
    'currency',
    'status',
    'bank_authorization_id',
    'bank_capture_id',
    'bank_void_id',
    'bank_refund_id',
    'card_last_four',
    'authorized_at',
    'captured_at',
    'voided_at',
    'refunded_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasPaymentState, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'USD',
        'status' => PaymentStatus::DEFAULT->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'card_last_four' => 'encrypted',
            'authorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'voided_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
