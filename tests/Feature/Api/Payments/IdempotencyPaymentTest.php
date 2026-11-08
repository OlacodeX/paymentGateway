<?php

use App\Enums\IdempotencyRecoveryPoint;
use App\Enums\PaymentStatus;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns 400 when the Idempotency-Key header is missing', function () {
    $this->postJson('/api/v1/payments/authorize', [
        'order_id' => 'ord_200',
        'customer_id' => 'cus_200',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'missing_idempotency_key');
});

it('replays the original authorize response for the same Idempotency-Key', function () {
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

    $payload = [
        'order_id' => 'ord_201',
        'customer_id' => 'cus_201',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ];

    $headers = [
        'Idempotency-Key' => 'idem_replay_authorize_ord_201',
    ];

    $first = $this->postJson('/api/v1/payments/authorize', $payload, $headers)
        ->assertCreated();

    $second = $this->postJson('/api/v1/payments/authorize', $payload, $headers)
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replayed', 'true');

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Payment::query()->where('order_id', 'ord_201')->count())->toBe(1);

    Http::assertSentCount(1);
});

it('replays a capture response without calling the bank again', function () {
    $payment = Payment::factory()->authorized()->create([
        'amount' => 5000,
        'bank_authorization_id' => 'auth_550e8400-e29b-41d4-a716-446655440000',
    ]);

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

    $headers = [
        'Idempotency-Key' => 'idem_replay_capture_'.$payment->id,
    ];

    $this->postJson("/api/v1/payments/{$payment->id}/capture", [], $headers)
        ->assertOk();

    $this->postJson("/api/v1/payments/{$payment->id}/capture", [], $headers)
        ->assertOk()
        ->assertHeader('X-Idempotent-Replayed', 'true')
        ->assertJsonPath('data.status.value', PaymentStatus::CAPTURED->value);

    Http::assertSentCount(1);
});

it('returns 409 when the Idempotency-Key is still in progress', function () {
    IdempotencyKey::query()->create([
        'idempotency_key' => 'idem_in_progress',
        'request_method' => 'POST',
        'request_path' => 'api/v1/payments/authorize',
        'recovery_point' => IdempotencyRecoveryPoint::STARTED,
        'locked_at' => now(),
    ]);

    $this->postJson('/api/v1/payments/authorize', [
        'order_id' => 'ord_202',
        'customer_id' => 'cus_202',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ], [
        'Idempotency-Key' => 'idem_in_progress',
    ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_key_in_progress');
});
