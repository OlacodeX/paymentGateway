<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('authorizes a payment against the bank', function () {
    Http::fake([
        '*/api/v1/authorizations' => Http::response([
            'authorization_id' => 'auth_550e8400-e29b-41d4-a716-446655440000',
            'status' => 'approved',
            'amount' => 5000,
            'currency' => 'USD',
            'expires_at' => now()->addDays(7)->toISOString(),
            'created_at' => now()->toISOString(),
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/payments/authorize', [
        'order_id' => 'ord_100',
        'customer_id' => 'cus_200',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ], [
        'Idempotency-Key' => 'idem_authorize_ord_100',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Payment authorized successfully')
        ->assertJsonPath('data.order_id', 'ord_100')
        ->assertJsonPath('data.customer_id', 'cus_200')
        ->assertJsonPath('data.amount', 5000)
        ->assertJsonPath('data.status.value', PaymentStatus::AUTHORIZED->value);

    $payment = Payment::where('order_id', 'ord_100')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::AUTHORIZED)
        ->and($payment->bank_authorization_id)->toBe('auth_550e8400-e29b-41d4-a716-446655440000')
        ->and($payment->authorized_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'idem_authorize_ord_100'));
});

it('returns 502 when the bank responds with a server error', function () {
    config([
        'services.bank.retry_times' => 1,
        'services.bank.retry_sleep_ms' => 0,
    ]);

    Http::fake([
        '*/api/v1/authorizations' => Http::response([
            'error' => 'internal_error',
            'message' => 'Bank unavailable',
        ], 500),
    ]);

    $this->postJson('/api/v1/payments/authorize', [
        'order_id' => 'ord_502',
        'customer_id' => 'cus_502',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ], [
        'Idempotency-Key' => 'idem_authorize_ord_502',
    ])
        ->assertStatus(502)
        ->assertJsonPath('error', 'internal_error');

    expect(Payment::where('order_id', 'ord_502')->exists())->toBeFalse();
});

it('returns the bank error when authorization is declined', function () {
    Http::fake([
        '*/api/v1/authorizations' => Http::response([
            'error' => 'insufficient_funds',
            'message' => 'Available balance is less than requested amount',
        ], 402),
    ]);

    $response = $this->postJson('/api/v1/payments/authorize', [
        'order_id' => 'ord_101',
        'customer_id' => 'cus_201',
        'amount' => 5000,
        'card_number' => '5555555555554444',
        'cvv' => '789',
        'expiry_month' => 9,
        'expiry_year' => 2030,
    ], [
        'Idempotency-Key' => 'idem_authorize_ord_101',
    ]);

    $response->assertStatus(402)
        ->assertJsonPath('error', 'insufficient_funds');

    expect(Payment::where('order_id', 'ord_101')->exists())->toBeFalse();
});

it('returns 422 when the payload is invalid', function () {
    $this->postJson('/api/v1/payments/authorize', [], [
        'Idempotency-Key' => 'idem_authorize_invalid',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order_id', 'customer_id', 'amount', 'card_number', 'cvv', 'expiry_month', 'expiry_year']);
});
