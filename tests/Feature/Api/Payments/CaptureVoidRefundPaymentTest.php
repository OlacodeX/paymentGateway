<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeAuthorizedPayment(): Payment
{
    return Payment::factory()->authorized()->create([
        'amount' => 5000,
        'bank_authorization_id' => 'auth_550e8400-e29b-41d4-a716-446655440000',
    ]);
}

it('captures an authorized payment', function () {
    $payment = fakeAuthorizedPayment();

    Http::fake([
        '*/api/v1/captures' => Http::response([
            'capture_id' => 'cap_550e8400-e29b-41d4-a716-446655440001',
            'authorization_id' => $payment->bank_authorization_id,
            'status' => 'captured',
            'amount' => 5000,
            'currency' => 'USD',
            'captured_at' => now()->toISOString(),
        ], 200),
    ]);

    $this->postJson("/api/v1/payments/{$payment->id}/capture")
        ->assertOk()
        ->assertJsonPath('message', 'Payment captured successfully')
        ->assertJsonPath('data.status.value', PaymentStatus::CAPTURED->value);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::CAPTURED)
        ->and($payment->bank_capture_id)->toBe('cap_550e8400-e29b-41d4-a716-446655440001')
        ->and($payment->captured_at)->not->toBeNull();
});

it('voids an authorized payment', function () {
    $payment = fakeAuthorizedPayment();

    Http::fake([
        '*/api/v1/voids' => Http::response([
            'void_id' => 'void_550e8400-e29b-41d4-a716-446655440002',
            'authorization_id' => $payment->bank_authorization_id,
            'status' => 'voided',
            'voided_at' => now()->toISOString(),
        ], 200),
    ]);

    $this->postJson("/api/v1/payments/{$payment->id}/void")
        ->assertOk()
        ->assertJsonPath('data.status.value', PaymentStatus::VOIDED->value);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::VOIDED)
        ->and($payment->bank_void_id)->toBe('void_550e8400-e29b-41d4-a716-446655440002');
});

it('refunds a captured payment', function () {
    $payment = Payment::factory()->captured()->create([
        'amount' => 5000,
        'bank_authorization_id' => 'auth_550e8400-e29b-41d4-a716-446655440000',
        'bank_capture_id' => 'cap_550e8400-e29b-41d4-a716-446655440001',
    ]);

    Http::fake([
        '*/api/v1/refunds' => Http::response([
            'refund_id' => 'ref_550e8400-e29b-41d4-a716-446655440003',
            'capture_id' => $payment->bank_capture_id,
            'status' => 'refunded',
            'amount' => 5000,
            'currency' => 'USD',
            'refunded_at' => now()->toISOString(),
        ], 200),
    ]);

    $this->postJson("/api/v1/payments/{$payment->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status.value', PaymentStatus::REFUNDED->value);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::REFUNDED)
        ->and($payment->bank_refund_id)->toBe('ref_550e8400-e29b-41d4-a716-446655440003');
});

it('returns 409 when capturing a voided payment', function () {
    $payment = Payment::factory()->voided()->create();

    $this->postJson("/api/v1/payments/{$payment->id}/capture")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_transition');

    expect($payment->fresh()->status)->toBe(PaymentStatus::VOIDED);
});

it('returns 409 when voiding a captured payment', function () {
    $payment = Payment::factory()->captured()->create();

    $this->postJson("/api/v1/payments/{$payment->id}/void")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_transition');

    expect($payment->fresh()->status)->toBe(PaymentStatus::CAPTURED);
});
