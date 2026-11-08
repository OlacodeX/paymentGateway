<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the payment for an order id', function () {
    $payment = Payment::factory()->authorized()->create([
        'order_id' => 'ord_query_100',
        'customer_id' => 'cus_query_100',
    ]);

    Payment::factory()->authorized()->create([
        'order_id' => 'ord_other',
        'customer_id' => 'cus_other',
    ]);

    $this->getJson('/api/v1/payments/by-order/ord_query_100')
        ->assertOk()
        ->assertJsonPath('message', 'Payment retrieved successfully')
        ->assertJsonPath('data.id', $payment->id)
        ->assertJsonPath('data.status.value', PaymentStatus::AUTHORIZED->value);
});

it('returns 404 when no payments exist for an order id', function () {
    $this->getJson('/api/v1/payments/by-order/ord_missing')
        ->assertNotFound()
        ->assertJsonPath('error', 'payment_not_found');
});

it('returns payment history for a customer id', function () {
    Payment::factory()->authorized()->create([
        'order_id' => 'ord_cus_1',
        'customer_id' => 'cus_history',
    ]);
    Payment::factory()->captured()->create([
        'order_id' => 'ord_cus_2',
        'customer_id' => 'cus_history',
    ]);
    Payment::factory()->authorized()->create([
        'order_id' => 'ord_other',
        'customer_id' => 'cus_other',
    ]);

    $this->getJson('/api/v1/payments/by-customer/cus_history')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns an empty list when a customer has no payments', function () {
    $this->getJson('/api/v1/payments/by-customer/cus_empty')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
