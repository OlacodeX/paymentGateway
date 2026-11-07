<?php

namespace App\Services\Payments\Providers;

abstract class Provider
{
    /**
     * @param  array{card_number: string, cvv: string, expiry_month: int, expiry_year: int, amount: int}  $payload
     * @return array<string, mixed>
     */
    abstract public function authorize(array $payload, string $idempotencyKey): array;

    /**
     * @param  array{authorization_id: string, amount: int}  $payload
     * @return array<string, mixed>
     */
    abstract public function capture(array $payload, string $idempotencyKey): array;

    /**
     * @param  array{authorization_id: string}  $payload
     * @return array<string, mixed>
     */
    abstract public function void(array $payload, string $idempotencyKey): array;

    /**
     * @param  array{capture_id: string, amount: int}  $payload
     * @return array<string, mixed>
     */
    abstract public function refund(array $payload, string $idempotencyKey): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function getAuthorization(string $authorizationId): array;
}
