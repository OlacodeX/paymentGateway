<?php

namespace App\Services\Payments;

use App\Services\Payments\Providers\Provider;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class Payment
{
    protected Provider $providerInstance;

    public function __construct(?string $provider = null)
    {
        $this->providerInstance = $this->createProvider(
            $provider ?? config('services.bank.provider', 'mock_bank')
        );
    }

    protected function createProvider(string $name): Provider
    {
        $class = sprintf(
            '%s\\Providers\\%sProvider',
            __NAMESPACE__,
            Str::studly($name)
        );

        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) {
            throw new RuntimeException("Payment provider [{$name}] is not supported.", 0, $exception);
        }

        if (! $reflection->isInstantiable() || ! $reflection->isSubclassOf(Provider::class)) {
            throw new RuntimeException("Payment provider [{$name}] is not supported.");
        }

        return $reflection->newInstance();
    }

    /**
     * @param  array{card_number: string, cvv: string, expiry_month: int, expiry_year: int, amount: int}  $payload
     * @return array<string, mixed>
     */
    public function authorize(array $payload, string $idempotencyKey): array
    {
        return $this->providerInstance->authorize($payload, $idempotencyKey);
    }

    /**
     * @param  array{authorization_id: string, amount: int}  $payload
     * @return array<string, mixed>
     */
    public function capture(array $payload, string $idempotencyKey): array
    {
        return $this->providerInstance->capture($payload, $idempotencyKey);
    }

    /**
     * @param  array{authorization_id: string}  $payload
     * @return array<string, mixed>
     */
    public function void(array $payload, string $idempotencyKey): array
    {
        return $this->providerInstance->void($payload, $idempotencyKey);
    }

    /**
     * @param  array{capture_id: string, amount: int}  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload, string $idempotencyKey): array
    {
        return $this->providerInstance->refund($payload, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuthorization(string $authorizationId): array
    {
        return $this->providerInstance->getAuthorization($authorizationId);
    }
}
